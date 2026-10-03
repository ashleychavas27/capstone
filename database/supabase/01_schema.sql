-- ===========================================================================
--  Dental Clinic — Supabase (PostgreSQL) schema
-- ===========================================================================
--  This file is the authoritative PostgreSQL schema for the clinic system.
--  It is a faithful, hand-written mirror of what `php artisan migrate`
--  produces on a PostgreSQL connection, plus the Supabase-specific hardening
--  (dedicated schema + RLS) described at the bottom.
--
--  HOW TO APPLY
--  ------------
--  Supabase Dashboard -> SQL Editor -> paste this whole file -> Run.
--  It is written to be safe to run once on an empty project.
--
--  After running it, Laravel already considers every migration applied
--  (see section 8), so `php artisan migrate` will report "Nothing to migrate"
--  instead of trying to re-create the tables.
--
--  WHY A DEDICATED SCHEMA AND NOT `public`
--  ---------------------------------------
--  Supabase publishes the `public` schema through its Data API (PostgREST),
--  and Supabase's own Laravel guide recommends moving application tables out
--  of `public`. This system stores patient records and medical history, so
--  keeping those tables out of the published schema is the safe default.
--  Laravel reads the schema from DB_SCHEMA (default: `dental`).
--  Prefer `public` instead? Create the schema as `public` and set
--  DB_SCHEMA=public — no other change is needed.
--
--  Requires PostgreSQL 15+ (for `unique nulls not distinct`). Supabase
--  projects run 15 or newer. A fallback for older servers is in section 7.
-- ===========================================================================

create schema if not exists dental;

-- Keep unqualified lookups predictable while running this script.
set search_path to dental, public;


-- ===========================================================================
--  1. Accounts and authentication
-- ===========================================================================

-- Application accounts. Roles: Owner, Secretary, Dentist, Patient.
-- NOTE: this app uses Laravel's own auth, NOT Supabase Auth. Supabase's
-- internal `auth.users` table is deliberately untouched.
create table if not exists dental.users (
    id                 bigserial       primary key,
    name               varchar(255)    not null,
    email              varchar(255)    not null,
    email_verified_at  timestamp(0) without time zone,
    password           varchar(255)    not null,
    contact_no         varchar(20),
    role               varchar(20)     not null default 'Patient',
    -- Dentists only: PRC licence number.
    license_no         varchar(50),
    -- Dentists only: duty days as ISO weekdays, e.g. "1,2,3,5" (Mon, Tue, Wed, Fri).
    -- Empty/absent means "available every open day". Bookings are only offered
    -- on these days; see config/clinic.php and App\Models\User::dutyDays().
    duty_days          varchar(20),
    -- Deactivated accounts are refused at login.
    is_active          boolean         not null default true,
    remember_token     varchar(100),
    created_at         timestamp(0) without time zone,
    updated_at         timestamp(0) without time zone,
    constraint users_email_unique unique (email)
);

create index if not exists users_role_index on dental.users (role);

-- Laravel password broker. `email` is the primary key.
create table if not exists dental.password_reset_tokens (
    email       varchar(255) primary key,
    token       varchar(255) not null,
    created_at  timestamp(0) without time zone
);

-- Session store (used only when SESSION_DRIVER=database).
-- No foreign key on user_id: the original migration only indexes it.
create table if not exists dental.sessions (
    id            varchar(255) primary key,
    user_id       bigint,
    ip_address    varchar(45),
    user_agent    text,
    payload       text    not null,
    last_activity integer not null
);

create index if not exists sessions_user_id_index on dental.sessions (user_id);
create index if not exists sessions_last_activity_index on dental.sessions (last_activity);


-- ===========================================================================
--  2. Laravel cache and queue infrastructure
-- ===========================================================================

create table if not exists dental.cache (
    key        varchar(255) primary key,
    value      text    not null,
    expiration integer not null
);

create index if not exists cache_expiration_index on dental.cache (expiration);

create table if not exists dental.cache_locks (
    key        varchar(255) primary key,
    owner      varchar(255) not null,
    expiration integer not null
);

create index if not exists cache_locks_expiration_index on dental.cache_locks (expiration);

-- QUEUE_CONNECTION=database uses these three tables.
create table if not exists dental.jobs (
    id           bigserial primary key,
    queue        varchar(255) not null,
    payload      text     not null,
    -- unsignedTinyInteger maps to smallint on PostgreSQL.
    attempts     smallint not null,
    reserved_at  integer,
    available_at integer  not null,
    created_at   integer  not null
);

create index if not exists jobs_queue_index on dental.jobs (queue);

create table if not exists dental.job_batches (
    id             varchar(255) primary key,
    name           varchar(255) not null,
    total_jobs     integer not null,
    pending_jobs   integer not null,
    failed_jobs    integer not null,
    failed_job_ids text    not null,
    options        text,
    cancelled_at   integer,
    created_at     integer not null,
    finished_at    integer
);

create table if not exists dental.failed_jobs (
    id         bigserial primary key,
    uuid       varchar(255) not null,
    connection text    not null,
    queue      text    not null,
    payload    text    not null,
    exception  text    not null,
    failed_at  timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    constraint failed_jobs_uuid_unique unique (uuid)
);


-- ===========================================================================
--  3. Patient records
-- ===========================================================================

-- Clinical profile. Exactly one row per patient account.
create table if not exists dental.patient_profiles (
    id              bigserial primary key,
    user_id         bigint  not null,
    age             smallint,
    address         text,
    medical_history text,
    created_at      timestamp(0) without time zone,
    updated_at      timestamp(0) without time zone,
    constraint patient_profiles_user_id_unique unique (user_id),
    constraint patient_profiles_user_id_foreign foreign key (user_id)
        references dental.users (id) on delete cascade
);


-- ===========================================================================
--  4. Appointments (the real-time scheduling core)
-- ===========================================================================

create table if not exists dental.appointments (
    id               bigserial primary key,
    patient_id       bigint      not null,
    -- A booking may be unassigned until a dentist picks it up.
    dentist_id       bigint,
    appointment_date date        not null,
    -- 24-hour "HH:MM" slot, e.g. "09:00". Rendered as "09:00 AM" in the UI.
    time_slot        varchar(10) not null,
    service_type     varchar(100) not null,
    -- Minutes reserved for this visit, taken from the procedure's estimated
    -- duration when it was booked (config/clinic.php). Null = derive from service_type.
    duration_minutes smallint,
    -- Pending | Confirmed | Completed | Cancelled
    status           varchar(20) not null default 'Pending',
    created_at       timestamp(0) without time zone,
    updated_at       timestamp(0) without time zone,

    constraint appointments_patient_id_foreign foreign key (patient_id)
        references dental.users (id) on delete cascade,
    constraint appointments_dentist_id_foreign foreign key (dentist_id)
        references dental.users (id) on delete set null,

    -- Double-booking guard.
    --
    -- PostgreSQL treats NULLs as distinct in a plain unique index, so the
    -- original MySQL-style constraint silently allows unlimited duplicate
    -- bookings whenever dentist_id IS NULL. `nulls not distinct` makes the
    -- constraint enforce what the application actually promises: at most one
    -- live booking per (dentist, date, slot), including the unassigned case.
    constraint appt_unique_slot unique nulls not distinct
        (dentist_id, appointment_date, time_slot)
);

-- Drives the dashboard "upcoming / today" filters.
create index if not exists appointments_appointment_date_status_index
    on dental.appointments (appointment_date, status);


-- ===========================================================================
--  5. Treatment records, prescriptions, SMS log, chat
-- ===========================================================================

-- One treatment record per appointment.
create table if not exists dental.treatment_records (
    id                bigserial primary key,
    appointment_id    bigint not null,
    patient_id        bigint not null,
    dentist_id        bigint,
    treatment_details text   not null,
    clinical_notes    text,
    created_at        timestamp(0) without time zone,
    updated_at        timestamp(0) without time zone,
    constraint treatment_records_appointment_id_unique unique (appointment_id),
    constraint treatment_records_appointment_id_foreign foreign key (appointment_id)
        references dental.appointments (id) on delete cascade,
    constraint treatment_records_patient_id_foreign foreign key (patient_id)
        references dental.users (id) on delete cascade,
    constraint treatment_records_dentist_id_foreign foreign key (dentist_id)
        references dental.users (id) on delete set null
);

-- e-Prescription header.
create table if not exists dental.prescriptions (
    id             bigserial primary key,
    patient_id     bigint       not null,
    dentist_id     bigint       not null,
    appointment_id bigint,
    diagnosis      varchar(500) not null,
    notes          text,
    date_issued    date         not null,
    created_at     timestamp(0) without time zone,
    updated_at     timestamp(0) without time zone,
    constraint prescriptions_patient_id_foreign foreign key (patient_id)
        references dental.users (id) on delete cascade,
    constraint prescriptions_dentist_id_foreign foreign key (dentist_id)
        references dental.users (id) on delete cascade,
    constraint prescriptions_appointment_id_foreign foreign key (appointment_id)
        references dental.appointments (id) on delete set null
);

-- e-Prescription line items (one medication each).
create table if not exists dental.prescription_items (
    id              bigserial primary key,
    prescription_id bigint       not null,
    drug_name       varchar(200) not null,
    dosage          varchar(100),
    frequency       varchar(100),
    duration        varchar(100),
    quantity        varchar(100),
    -- The "sig." free-text directions.
    instructions    varchar(500),
    created_at      timestamp(0) without time zone,
    updated_at      timestamp(0) without time zone,
    constraint prescription_items_prescription_id_foreign foreign key (prescription_id)
        references dental.prescriptions (id) on delete cascade
);

-- Audit trail of every SMS attempt. Status: Sent | Failed | Mocked.
create table if not exists dental.sms_logs (
    id              bigserial primary key,
    recipient_phone varchar(20) not null,
    message         text        not null,
    status          varchar(30) not null default 'Sent',
    sent_at         timestamp(0) without time zone,
    created_at      timestamp(0) without time zone,
    updated_at      timestamp(0) without time zone
);

-- Chat bot transcript. Sender: "user" | "bot".
create table if not exists dental.chat_messages (
    id         bigserial primary key,
    user_id    bigint,
    sender     varchar(10) not null,
    message    text        not null,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    constraint chat_messages_user_id_foreign foreign key (user_id)
        references dental.users (id) on delete set null
);


-- ===========================================================================
--  6. Laravel migration bookkeeping
-- ===========================================================================

-- Laravel needs this table to exist before it can decide what to run.
create table if not exists dental.migrations (
    id        serial primary key,
    migration varchar(255) not null,
    batch     integer not null
);


-- ===========================================================================
--  7. Server-version fallback (PostgreSQL < 15 only)
-- ===========================================================================
--  If `unique nulls not distinct` above is rejected, your server is older
--  than PostgreSQL 15. Replace that one constraint with this expression
--  index, which achieves the same "one live booking per slot" guarantee by
--  substituting a sentinel for unassigned bookings:
--
--    alter table dental.appointments drop constraint appt_unique_slot;
--    create unique index appt_unique_slot on dental.appointments (
--        coalesce(dentist_id, 0), appointment_date, time_slot
--    );
--
--  The index name matches the constraint name, so the application and the
--  Laravel migration (which now also uses nullsNotDistinct) stay in step on
--  15+. Prefer upgrading to 15+.


-- ===========================================================================
--  8. Mark the existing migrations as already applied
-- ===========================================================================
--  The tables above already contain everything these migrations would build,
--  so recording them here prevents `php artisan migrate` from re-creating
--  them (which would fail with "relation already exists").
--
--  Prefer to let Laravel own the schema instead? Delete everything between
--  the BEGIN and COMMIT below, then run `php artisan migrate` — but only
--  against an empty database.

begin;

insert into dental.migrations (migration, batch) values
    ('0001_01_01_000000_create_users_table',                                1),
    ('0001_01_01_000001_create_cache_table',                                1),
    ('0001_01_01_000002_create_jobs_table',                                 1),
    ('2026_08_12_000001_create_patient_profiles_table',                     1),
    ('2026_08_12_000002_create_appointments_table',                         1),
    ('2026_08_12_000003_create_treatment_records_table',                    1),
    ('2026_08_12_000004_create_sms_logs_table',                             1),
    ('2026_08_12_000005_create_prescriptions_table',                        1),
    ('2026_08_12_000006_create_chat_messages_table',                        1),
    ('2026_08_23_000001_add_license_no_and_is_active_to_users_table',       1),
    ('2026_10_03_000001_add_duration_minutes_to_appointments_table',        1),
    ('2026_10_03_000002_add_duty_days_to_users_table',                      1)
on conflict do nothing;

commit;


-- ===========================================================================
--  9. Supabase hardening — keep clinical data off the Data API
-- ===========================================================================
--  Supabase grants the `anon` and `authenticated` roles access to new tables
--  in exposed schemas and publishes them over PostgREST. Laravel connects as
--  the project owner role (`postgres`), which is unaffected by RLS because it
--  owns these tables.
--
--  This section therefore:
--    * revokes every privilege on this schema from the API roles, and
--    * enables RLS on all clinical tables with NO policies, which in Supabase
--      means "deny every request".
--
--  Net effect: the patient records are reachable only through the Laravel
--  application. If a later step moves the frontend onto supabase-js, that
--  step must explicitly grant access and add RLS policies — see
--  database/supabase/README.md for the exact procedure.
--
--  Wrapped in a role-existence check so the file also runs on a plain
--  self-hosted PostgreSQL where the Supabase roles do not exist.

do $do$
begin
    if exists (select 1 from pg_roles where rolname = 'anon') then
        execute 'revoke all on schema dental from anon, authenticated';
        execute 'revoke all on all tables in schema dental from anon, authenticated';
        execute 'revoke all on all sequences in schema dental from anon, authenticated';
    end if;
end
$do$;

do $do$
declare
    t text;
begin
    foreach t in array array[
        'users', 'patient_profiles', 'appointments', 'treatment_records',
        'prescriptions', 'prescription_items', 'sms_logs', 'chat_messages',
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens'
    ]
    loop
        execute format('alter table dental.%I enable row level security', t);
    end loop;
end
$do$;


-- ===========================================================================
--  10. Documentation of the live schema
-- ===========================================================================

comment on schema dental is
    'Application schema for the Dental Clinic system. Excluded from the Supabase Data API.';

comment on table dental.appointments is
    'Bookings. See constraint appt_unique_slot for the double-booking guard.';

comment on column dental.appointments.time_slot is
    '24-hour HH:MM clinic slot; valid values are Appointment::SLOTS in the app.';

comment on column dental.appointments.status is
    'Pending | Confirmed | Completed | Cancelled (Appointment::STATUSES).';

comment on column dental.users.role is
    'Owner | Secretary | Dentist | Patient (User::ROLES).';

comment on column dental.users.is_active is
    'false blocks login for staff accounts (checked in AuthController::login).';

comment on column dental.sms_logs.status is
    'Sent | Failed | Mocked (SmsLog constants).';

comment on column dental.chat_messages.sender is
    'user | bot (ChatMessage constants).';
