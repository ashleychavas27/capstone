# Supabase (PostgreSQL) migration

This directory holds the PostgreSQL schema for the clinic system. The
application no longer uses MySQL or SQLite: `config/database.php` declares
`pgsql` as the only application connection, and every `DB_*` setting in `.env`
points at Supabase.

---

## 1. Setup (one time)

1. Create a project at <https://database.new>.
2. In the dashboard click **Connect** and choose **Session pooler**.
   Copy the host, port, database, username and password into `.env`:

   ```dotenv
   DB_CONNECTION=pgsql
   DB_HOST=aws-0-<region>.pooler.supabase.com   # copy it, do not infer it
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres.<project-ref>
   DB_PASSWORD=<your database password>
   DB_SCHEMA=dental
   DB_SSLMODE=require
   ```

3. Apply the schema — either in the dashboard **SQL Editor** (paste
   [`01_schema.sql`](01_schema.sql), then optionally [`02_seed.sql`](02_seed.sql)
   for demo data), or from the terminal without installing `psql`:

   ```bash
   php database/supabase/apply.php            # 01 -> 02 -> 03, in order
   php database/supabase/apply.php --dry-run  # connect + list the files only
   ```

   `apply.php` reads the `DB_*` values from `.env` and connects through the
   session pooler with PDO — the same path Laravel uses. It exits `2` when
   `DB_PASSWORD` is empty and reports the failing file otherwise.

   The Supabase CLI works too, but only over the database password: it reads
   migrations from `supabase/migrations/`, so copy these files there first
   (`mkdir -p supabase/migrations`), then run
   `supabase db push --db-url "postgresql://…" -p "$DB_PASSWORD"`.
   `supabase login` cannot help here — it needs a personal access token
   (`sbp_…`), which is a different credential from the publishable/secret keys.
4. Confirm: `php artisan db:show`
5. Start the app: `php artisan serve`

   Exposure note: with `VITE_SUPABASE_READS=off` the browser goes through
   Laravel, so `dental` does **not** need to be added to the dashboard's
   exposed schemas — and the verified end-to-end run below uses exactly that
   configuration. Switching direct browser reads on (`VITE_SUPABASE_READS=on`)
   needs three more things, all deliberate: add `dental` to the dashboard's
   exposed schemas, uncomment `appointment_feed` in
   [`03_realtime.sql`](03_realtime.sql) (it is off because the view publishes
   patient names to the anon key), and rebuild with `npm run build`.

### Which connection to use, and why it matters

| Supabase option | Port | Use here? | Why |
| --- | --- | --- | --- |
| **Session pooler** (Supavisor) | 5432 | **Yes** | IPv4 reachable, and it preserves the `search_path` and prepared statements Eloquent needs. |
| Direct connection | 5432 | Only if you have IPv6 | Free-plan direct connections are IPv6-only, so they usually fail from a home/office IPv4 network. |
| Transaction pooler | 6543 | **No** | It does not support prepared statements. Eloquent relies on them, so queries fail intermittently. Supabase's own Laravel guide warns against using it as the main data source. |

`DB_SSLMODE=require` is not optional in practice. Laravel defaults to `prefer`,
which silently falls back to an unencrypted connection if TLS fails — so
credentials and patient data could cross the network in plaintext.

### Never run these against Supabase

`php artisan migrate:fresh`, `migrate:refresh`, `db:wipe` and
`db:seed --class=DatabaseSeeder` after real data exists are all destructive.
The seeder and `02_seed.sql` both refuse to run when accounts already exist.

### Verify the connection

```bash
php artisan config:show database | sed -n '1,30p'
php artisan serve
# then sign in — the login form is the first thing that touches the database
```

If `DB_PASSWORD` is not set yet, the login POST fails with a message that
names every relevant field, which makes the fix obvious:

```
SQLSTATE[08006] [7] fe_sendauth: no password supplied
(Connection: pgsql, Host: aws-0-ap-northeast-2.pooler.supabase.com,
 Port: 5432, Database: postgres, ...)
```

That is the connection path working and reaching real Postgres — only the
password is missing. Set `DB_PASSWORD` to the **database password** from
Dashboard → Connect → Session pooler. Neither API key will work here:
`sb_publishable_*` and `sb_secret_*` are Data API keys, not a database password.

### Two credentials that are often confused

| Value | Prefix | Used by | Where it goes |
| --- | --- | --- | --- |
| Publishable (browser) key | `sb_publishable_` | `supabase-js` in the browser | `VITE_SUPABASE_ANON_KEY` |
| Secret key | `sb_secret_` | Server-side Data API calls | `SUPABASE_SECRET_KEY` |
| **Database password** | *(none)* | **Laravel's PDO connection** | **`DB_PASSWORD`** |

`.env` is gitignored, so the secret key is safe there — but never prefix it
`VITE_`: Vite inlines every `VITE_*` value into the public browser bundle.

---

## 2. Schema design decisions

**A dedicated `dental` schema, not `public`.**
Supabase publishes the `public` schema through its Data API (PostgREST).
This system stores patient records, medical history and prescriptions, so the
tables are deliberately kept out of that published surface. Laravel reads the
schema name from `DB_SCHEMA`. To use `public` instead, create the schema as
`public` and set `DB_SCHEMA=public`; nothing else changes.

**RLS enabled, with no policies.**
Section 9 of `01_schema.sql` revokes the `anon` / `authenticated` roles on
`schema dental` and enables row level security on every clinical table. In
Supabase, RLS with no policies denies all API access, so clinical data is
reachable only through Laravel. That is intentional — see §6 before relaxing it.

**`appt_unique_slot` uses `unique nulls not distinct`.**
PostgreSQL treats every NULL as distinct inside a plain unique index, so the
original constraint silently allowed unlimited duplicate bookings whenever
`dentist_id` was NULL. `nulls not distinct` (PostgreSQL 15+) makes the
constraint enforce what the app promises: at most one live booking per
dentist/date/slot, including unassigned bookings. Section 7 of the file has a
fallback expression index for older servers.

**`DB_TIMEZONE=Asia/Manila`.**
Supabase runs its server clock in UTC while the clinic operates in
`Asia/Manila`. Laravel's Postgres connector issues
`set time zone '<DB_TIMEZONE>'` per connection, which keeps `CURRENT_TIMESTAMP`
defaults (`failed_jobs.failed_at`) aligned with the application clock.

**Laravel auth, not Supabase Auth.**
Accounts live in `dental.users` and are handled by Laravel's own guards, so
Supabase's internal `auth.users` table is untouched.

**Clinic schedule lives in the database where it is per-clinic data.**
`users.duty_days` ("1,2,3,5") carries each dentist's working week and
`appointments.duration_minutes` records how much time a booking reserved, taken
from the procedure's estimated duration when it was booked. Policies that apply
to the whole clinic — opening hours, the lunch break, the procedure list and
their durations — stay in `config/clinic.php`. Storing the duration (rather than
re-deriving it) means editing the config can never silently move an appointment
that already exists.

---

## 3. Keeping migrations and the SQL file in step

`01_schema.sql` is a mirror of what `php artisan migrate` produces on
PostgreSQL, with two deliberate extras that a migration run would not create:

- the `migrations` table itself (Laravel creates that outside migrations), and
- the Supabase hardening and `comment on` statements in sections 9 and 10.

It was verified by compiling every migration through Laravel's own PostgreSQL
grammar and comparing the result against the file — all 15 tables, every
column, and all 12 index names matched the database the original migrations
actually built. Two things therefore changed in the migrations themselves:

| File | Change | Reason |
| --- | --- | --- |
| `2026_08_23_000001_add_license_no_and_is_active_to_users_table.php` | Dropped `->after()` | Column placement is a MySQL-only clause; PostgreSQL ignores it. |
| `2026_10_03_000001_add_duration_minutes_to_appointments_table.php` | Kept `->after()`, backfills existing rows | The backfill maps old service names onto the configured procedure durations; PostgreSQL treats `->after()` as a no-op. |
| `2026_10_03_000002_add_duty_days_to_users_table.php` | Seeds the two dentist accounts | A fresh database must enforce the same duty schedule the clinic told us about (Mon/Tue/Wed/Fri and Thu/Sat). |
| `2026_08_12_000002_create_appointments_table.php` | `->nullsNotDistinct()` on `appt_unique_slot` | Makes the double-booking guard work for unassigned bookings on PostgreSQL. |

Column *order* differs slightly between the two paths: because PostgreSQL
ignores `after()`, running the migrations puts `license_no` and `is_active` at
the end of `users`, while the SQL file places them next to `role`. This is
cosmetic — Eloquent selects columns by name.

---

## 4. Application code changed for PostgreSQL

| File | Change | Reason |
| --- | --- | --- |
| `config/database.php` | `pgsql` is the only application connection; default is `pgsql`; MySQL/MariaDB/SQL Server connections removed; `sqlite` retained for tests only | Removes every MySQL path |
| `config/database.php` | `search_path` from `DB_SCHEMA`, `sslmode` defaulting to `require`, new `timezone` | Supabase-specific connection correctness |
| `app/Http/Controllers/AppointmentController.php` | New `isUniqueConstraintViolation()` used by both booking paths | The slot-conflict guard tested for SQLSTATE `23000` (MySQL/SQLite). PostgreSQL reports `23505`, so the friendly "slot just taken" message would never have appeared — the exception would have propagated as a 500 |
| `app/Http/Controllers/PatientController.php` | Search now uses `LOWER(...) LIKE` | PostgreSQL's `LIKE` is case-sensitive, unlike MySQL's default collation, so searching "ana" would no longer find "Ana Villanueva" |
| `database/seeders/DatabaseSeeder.php` | Returns early when accounts exist | Seeding a persistent remote database twice would fail on the `users.email` unique index |
| `.env`, `.env.example` | Supabase connection block and Data API keys | — |

#### The framework re-adds MySQL unless you stop it explicitly

Laravel merges its own default `database.connections` into the application's
(`LoadConfiguration` treats `database.connections` as a *mergeable option*).
Deleting `mysql` / `mariadb` / `sqlsrv` from `config/database.php` is therefore
not enough — they came back, and because those stock definitions read
`env('DB_HOST')` / `env('DB_USERNAME')`, the **Supabase pooler credentials were
appearing under a MySQL connection**.

`config/database.php` now declares all three as `null`, which overrides the
merged defaults. **Do not remove those three lines** — `php artisan config:show
database` should keep showing `connections ⇁ mysql | null`.

---

## 5. Tests

The suite runs against in-memory SQLite so it needs no network or credentials:

```bash
php artisan test
```

58 tests / 179 assertions pass after this migration. To run the suite against
real PostgreSQL instead, change `DB_CONNECTION` to `pgsql` in `phpunit.xml` and
point `DB_*` at a Supabase **branch** or a disposable database. Never point the
test suite at production: it truncates tables.

> Note: because the suite runs on SQLite, it does **not** exercise PostgreSQL
> behaviour. `nulls not distinct`, `23505` and `ilike`/`LOWER` semantics are
> only truly covered once the suite runs on PostgreSQL.

---

## 6. Remaining work

### Backend

**Highest priority — the existing data has not moved.** The 13 accounts,
26 appointments, 3 prescriptions, 5 treatment records and 10 SMS logs currently
live only in `database/database.sqlite`, which nothing reads any more. No
automated port exists yet. Add a one-off command that reads that SQLite file and
inserts into the `pgsql` connection in dependency order (users →
patient_profiles → appointments → treatment_records → prescriptions →
prescription_items → sms_logs), preserving primary keys and then resetting each
sequence with `setval`. `database/supabase/02_seed.sql` covers only fresh demo
data.

| Area | File | Work |
| --- | --- | --- |
| Data port | new `app/Console/Commands/ImportSqliteToSupabase.php` | One-off SQLite → Supabase transfer described above |
| Storage | `config/filesystems.php`, `FILESYSTEM_DISK` | Still `local`. Patient documents should move to Supabase Storage, which needs a new disk plus bucket policies |
| Queue | `config/queue.php` | `QUEUE_CONNECTION=database` works against the new `dental.jobs` table, but `composer dev` runs a local `queue:listen`. Remote deployment needs a worker |
| Sessions | `config/session.php` | `SESSION_DRIVER=file` will not survive horizontal scaling; `dental.sessions` already exists for `database` |
| SMS | `app/Services/SmsService.php` | Unchanged and driver-independent, but writes to `dental.sms_logs`. With `SMS_MOCK=true` every row is recorded as `Mocked` |
| Chat bot | `app/Services/ChatBotService.php` | Calls Gemini directly. Optional Supabase Edge Function candidate |
| Scheduler | `routes/console.php` | `sms:send-reminders` still needs an OS cron entry; Postgres does not provide scheduling |

### Frontend

The browser now has exactly two owners for Supabase, and nothing else may touch
the SDK:

| File | Owns |
| --- | --- |
| `resources/js/supabase.js` | The only `createClient()` call. Reads the `VITE_SUPABASE_*` values, detects placeholder credentials, and disables itself rather than pointing at a host that does not exist. |
| `resources/js/data.js` | The only module that queries tables or opens a Realtime channel. Exposes `fetchCalendarEvents()`, `subscribeToAppointmentChanges()` and the route registry `apiFetch` consults. |
| `resources/js/app.js` | Vite entry point. Publishes the layer as `window.clinicData` so the server-rendered Blade pages can use it without importing modules. |

The server-rendered pages stay server-rendered. `apiFetch()` in
`resources/views/layouts/app.blade.php` is still the single AJAX choke point,
and it now asks the data layer first; whatever the layer does not answer keeps
going to Laravel unchanged.

`vite.config.js` already listed `resources/js/app.js` as an input, but no view
ever loaded it — the bundle was dead code. `resources/views/layouts/app.blade.php`
now emits `@vite('resources/js/app.js')` behind a manifest check, so a checkout
that has not been built yet still boots instead of throwing on a missing
manifest. **Building is required for the frontend layer to exist at all:**
`npm install && npm run build` (already part of `composer setup`).

`resources/js/bootstrap.js` and the `axios` dependency were removed at the same
time. Nothing in the app ever imported axios — every request goes through
`fetch` — so putting the bundle on the page for the first time would have
shipped an unused HTTP client. Removing it took the bundle from 278 kB to
226 kB (78.6 kB to 59.7 kB gzip). If a future page wants axios, add it back
explicitly.

#### What now goes through Supabase

| Path | Via | Needs |
| --- | --- | --- |
| `GET /appointments/calendar-events` | `data.js` → `dental.appointment_feed` view | `VITE_SUPABASE_READS=on`, exposed `dental` schema, the grant + view in `03_realtime.sql` Part B |
| Live schedule updates (calendar page) | Realtime Broadcast channel `clinic-appointments` | `03_realtime.sql` Part A, then rebuild |
| Live availability updates (booking page) | Same channel; the page re-reads availability after a signal | Same as above |

#### What deliberately does **not** go through Supabase

These still work exactly as before, through Laravel, and are not half-converted:

| Path | Why it stays |
| --- | --- |
| `available-slots`, `month-availability` | Deciding what is bookable depends on `now()`, the fixed clinic slot list, the 2-month window and the unassigned-dentist rule. Re-implementing that in the browser would create a second source of truth for a double-booking decision. The booking page instead re-reads the authoritative answer when Realtime says something changed. |
| `appointments.data`, `patients.data`, `prescriptions.data`, `sms.data`, `accounts.data` | Every one of these joins `dental.users` for names or contact details. Serving them from the browser would expose `users` — including `password` hashes — to the public anon key. |
| All writes (`book.store`, status `PATCH`, `DELETE`, `PUT profile`, `POST sms`, `POST chatbot`) | Writes must stay server-side: validation, role checks, SMS triggering in `updateStatus`, and the gateway/Gemini API keys all live in PHP. |
| `auth/login`, `auth/register` | Laravel session auth. Moving it to Supabase Auth is a separate, much larger change. |

#### Verification performed

The app was run locally and driven in a browser with placeholder credentials:

- The bundle builds and loads; the layout's manifest guard works.
- The data layer detects the placeholders and reports `Supabase: not configured
  (running fully on Laravel).` — no request is ever sent to a fabricated host.
- Calendar: renders, and events load through the new `apiFetch` route (4 events
  in August 2026).
- Booking: month grid (25 available days, earliest 3 Oct 2026) and slots
  (14 slots, 9:00 AM) both load.
- Patients table: 8 rows, and case-insensitive search returns
  "Ana Villanueva" for `ANA`.
- Every page returns 200; the only non-200s are the role middleware correctly
  refusing `/my-records` and `/prescriptions/create` for an Owner account.
- No JavaScript errors. `php artisan test`: 58 passed.

#### To make the migrated paths run

1. Fill in `VITE_SUPABASE_URL` and `VITE_SUPABASE_ANON_KEY` in `.env` from
   **Project Settings → API**. Put the *anon* key there — never the
   `service_role` key, which bypasses RLS and must never reach a browser.
2. Run `database/supabase/03_realtime.sql` (Part A) for live updates. No table
   access and no publication change is needed — Realtime Broadcast is used
   precisely so that appointments do not have to be exposed.
3. Add `dental` to **Project Settings → API → Exposed schemas**, then uncomment
   Part B of the same file for the calendar read. Read its security note first:
   it grants the public anon role read access to appointment data.
4. `npm run build` — Vite inlines `VITE_*` at build time, so new values are not
   visible to the browser until you rebuild.

If any of this is skipped the frontend degrades cleanly to Laravel. With
`VITE_SUPABASE_READS` left at `off` (the default) the calendar read stays on
Laravel too, and the only Supabase capability in play is Realtime.

### Not yet covered by the schema

- **Reference data.** Clinic services and time slots are PHP constants
  (`Appointment::SERVICES`, `Appointment::SLOTS`) and are duplicated in the
  booking views, the chat bot's context, and the seed file. A Supabase-driven
  frontend cannot query them. Promote them to `dental.services` and
  `dental.time_slots` tables and have the views read from them.
- **No `updated_by` / audit columns.** Nothing records who confirmed, cancelled
  or edited a record, which matters for clinical data.
- **No per-user identity for RLS.** `dental.users` still holds the passwords and
  the role model, so Postgres has nothing to scope policies by for a browser
  request. This is the single blocker behind every read that still runs through
  Laravel. The recommended fix is Supabase's third-party JWT support: mint a
  short-lived JWT for the signed-in Laravel user, signed with the project's JWT
  secret, and let RLS policies read `auth.jwt()` claims (role, dentist id). That
  unlocks browser-side reads with the same scoping Laravel applies today, without
  migrating authentication.
- **No soft deletes.** Deleting an appointment destroys its treatment record via
  `on delete cascade`.
- **No `sms_logs.appointment_id`.** SMS history cannot be traced back to the
  appointment that triggered it.
- **No index on `users.email` beyond the unique constraint**, and no trigram
  index for the typo-friendly search the DataTables UI advertises. `pg_trgm`
  with a GIN index would let the search scale.
