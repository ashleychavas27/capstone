-- ===========================================================================
--  Dental Clinic — Supabase Realtime + the opt-in frontend read path
-- ===========================================================================
--  Apply this once, after 01_schema.sql:
--    Supabase Dashboard -> SQL Editor -> paste -> Run
--
--  Contains two independent parts:
--
--    PART A (safe, apply it): Realtime change notifications for the schedule.
--                             Needs no table read access and leaks no rows.
--
--    PART B (opt-in, deliberately commented out): serving frontend reads from
--                             Supabase. Read the security note in Part B before
--                             enabling it — it exposes clinic data to anyone who
--                             has the public anon key.
--
--  The frontend works fully with neither part applied: pages fall back to their
--  Laravel endpoints (see resources/js/data.js).
-- ===========================================================================


-- ===========================================================================
--  PART A — Realtime change notifications (safe; no row data, no read access)
-- ===========================================================================
--  The browser subscribes to a Broadcast channel and receives a small,
--  content-free "something changed" payload. On receiving it, the open page
--  re-reads its data through its normal authenticated path.
--
--  This uses Broadcast rather than Realtime's Postgres Changes on purpose.
--  Postgres Changes requires the subscribing role to hold SELECT on the table,
--  which for an unauthenticated browser session means granting the public anon
--  role read access to appointments — and Realtime would then stream the rows
--  themselves. Broadcast needs neither, so no schedule data is exposed and no
--  table needs adding to the supabase_realtime publication.

create or replace function dental.broadcast_appointment_change()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
    -- A notification must never be able to break a booking. If the realtime
    -- schema or its helper is unavailable, log and carry on: the write itself
    -- has already succeeded, and the page still works on a manual reload.
    begin
        perform realtime.send(
            jsonb_build_object(
                'table', 'appointments',
                'event', lower(tg_op),
                'at', to_char(now() at time zone 'utc', 'YYYY-MM-DD"T"HH24:MI:SS"Z"')
            ),
            'changed',
            'clinic-appointments',
            false  -- public channel; the payload carries no row contents
        );
    exception when others then
        raise warning 'appointment broadcast skipped: %', sqlerrm;
    end;

    return null;
end;
$$;

comment on function dental.broadcast_appointment_change() is
    'Broadcasts a content-free change signal to the clinic-appointments channel. Swallows errors so a notification can never fail a booking.';

drop trigger if exists appointments_broadcast_change on dental.appointments;
create trigger appointments_broadcast_change
    after insert or update or delete on dental.appointments
    for each row
    execute function dental.broadcast_appointment_change();


-- ===========================================================================
--  PART B — OPT-IN: serve frontend reads from Supabase
-- ===========================================================================
--  ============================ SECURITY NOTE ============================
--  Everything in this part is commented out. Enabling it lets the BROWSER read
--  clinic data directly, and that has consequences you must accept knowingly:
--
--  1. The browser authenticates with the public anon key. That key ships in the
--     JavaScript bundle, so it is not a secret. Anyone who has it can issue the
--     same queries.
--  2. Laravel scopes every query by role today: a Dentist sees only their own
--     schedule, a Patient only their own records. A browser client has no
--     identity, so Supabase cannot repeat that scoping — RLS has no
--     `auth.uid()` to key off. Enabling reads therefore removes the database's
--     ability to enforce those rules. The UI still filters by role, but a UI
--     filter is not enforcement.
--  3. `appointment_feed` exposes patient and dentist NAMES. For a clinic this is
--     personal data about identifiable people, published to anyone with the
--     anon key.
--
--  Do NOT enable this part for real patient data. Two sound ways to get the
--  same capability without the exposure:
--
--    (a) Adopt Supabase Auth so RLS policies can key off auth.uid() with a
--        real identity per request. Largest change: Laravel's session auth
--        would hand over to Supabase sessions.
--
--    (b) Keep reads on Laravel and mint a short-lived Supabase JWT for the
--        logged-in user (Supabase supports third-party JWTs signed with the
--        project's JWT secret). RLS policies then read the claims from
--        auth.jwt() — for example a `role` or `dentist_id` claim — and enforce
--        the same scoping Laravel does, with no Supabase Auth migration.
--
--  For demo or coursework data this part is fine to enable. Then set
--  VITE_SUPABASE_READS=on and rebuild (`npm run build`).
--  =======================================================================

-- -- 1. A read-only view exposing only what the calendar renders. Reading a view
-- --      instead of the tables is what keeps users.password, medical_history,
-- --      prescriptions and sms_logs out of reach.
-- create or replace view dental.appointment_feed as
-- select
--     a.id,
--     a.appointment_date,
--     a.time_slot,
--     a.service_type,
--     a.status,
--     a.dentist_id,
--     p.name as patient_name,
--     d.name as dentist_name
-- from dental.appointments a
-- left join dental.users p on p.id = a.patient_id
-- left join dental.users d on d.id = a.dentist_id;

-- -- 2. Publishing the schema: the view must be reachable through PostgREST.
-- --      Supabase Dashboard -> Project Settings -> API -> Exposed schemas,
-- --      add `dental`. Be aware that exposing a schema also exposes the
-- --      clinical tables in it, so pair it with the revokes in
-- --      schema section 9 and the explicit grant below.

-- -- 3. Grant read on the view only (never on dental.users or dental.appointments).
-- grant usage on schema dental to anon;
-- grant select on dental.appointment_feed to anon;

-- -- 4. RLS on the view. `security_invoker = on` would make the view respect the
-- --      caller's row security on the underlying tables — which for anon means
-- --      seeing nothing, since section 9 denies it. Leaving it off lets the
-- --      view's owner (the postgres role) read the rows. This is the step that
-- --      actually exposes the data.
-- alter view dental.appointment_feed set (security_invoker = off);

-- -- 5. Remove the blanket revokes that schema section 9 applied, if you want the
-- --      view to be the ONLY thing reachable:
-- --      (nothing further needed — the revoke covered tables, and the grant in
-- --      step 3 is specific to the view.)

-- -- 6. Verify from the SQL Editor as the anon role:
-- --      set role anon;
-- --      select * from dental.appointment_feed limit 5;   -- should succeed
-- --      select * from dental.users limit 1;              -- must fail
-- --      reset role;
