# Dental Clinic — Online Records & Appointment Scheduling System

A 3-page web application for a university capstone: **Dental Clinic Records Management + Real-Time Appointment Scheduling with SMS Notification**.

Built with **Laravel 12 (PHP 8.2+)**, **Supabase PostgreSQL**, **Blade**, **Bootstrap 5 (CDN)**, **DataTables (CDN)**, and **vanilla JavaScript** with a **Vite** bundle for the Supabase client.

---

## Features (3-Page MVP)

| Page | What it does |
|------|--------------|
| **1. Dashboard & Reports** | Metric cards (Total Patients, Upcoming Appointments, Pending Bookings, Today), daily schedule table (date-pickable), recent SMS feed, and a **print-friendly daily report** (`/dashboard/print`) with dedicated print CSS. |
| **2. Patient Records & Treatment History** | Searchable/sortable/paginated DataTable of patient profiles, a patient detail view with medical history + consultation history, and a **modal for the Dentist** to add/update treatment details & clinical notes (which auto-completes the appointment). |
| **3. Real-Time Appointment Scheduling** | **Passport-style booking calendar** (green = available, red = fully booked, with an "Earliest available appointment" hint and legend — like the DFA passport appointment app) plus **live slot availability**, **double-booking conflict detection** (controller check + DB unique index), and an **automatic SMS reminder** sent & logged whenever a Secretary/Owner confirms an appointment. |

### Roles & Access Control
- **Owner / Secretary** — full management: dashboard, patients, schedule, SMS logs.
- **Dentist** — dashboard (own schedule), patient records, add treatment records. No schedule management.
- **Patient** — dashboard, online booking, **My Records** (own diagnostic history + e-prescriptions, printable). No staff pages.

---

## Demo Accounts (seeded, password `password` for all)

| Role      | Email                    |
|-----------|--------------------------|
| Owner     | owner@clinic.test        |
| Secretary | secretary@clinic.test    |
| Dentist   | dentist1@clinic.test     |
| Patient   | ana.villanueva@clinic.test |

The seeder also creates 8 patients, 11 appointments (completed/confirmed/pending/cancelled), 4 treatment records, 2 e-prescriptions, and SMS logs.

---

## Quick Start (local)

Requirements: **PHP 8.2+**, **Composer**, **Node 18+ / npm**, and a **Supabase**
project. The database is Supabase PostgreSQL only — there is no MySQL or SQLite
configuration anymore.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Create the Supabase project
#    https://database.new  ->  dashboard -> Connect -> Session pooler
#    Copy the host, port, username and password into .env:
#      DB_CONNECTION=pgsql
#      DB_HOST=aws-0-<region>.pooler.supabase.com
#      DB_PORT=5432
#      DB_DATABASE=postgres
#      DB_USERNAME=postgres.<project-ref>
#      DB_PASSWORD=<your database password>

# 4. Apply the schema (Supabase Dashboard -> SQL Editor -> paste -> Run)
#    database/supabase/01_schema.sql
#    optional demo data:  database/supabase/02_seed.sql
#
#    Do NOT run `php artisan migrate` instead — 01_schema.sql already creates
#    every table and records the migrations as applied.

# 5. Build the frontend bundle (Supabase client lives in here)
npm run build

# 6. Run
php artisan serve
# → http://localhost:8000
```

> Full setup detail, connection-pooler choice, schema decisions and the list of
> remaining work: see [`database/supabase/README.md`](database/supabase/README.md).

> The frontend needs `npm run build` because the Supabase client is bundled by
> Vite. Without it the app still boots and runs entirely on Laravel — the
> layout skips the bundle when `public/build` is missing.

### Run the tests
```bash
php artisan test    # feature + unit tests: auth, RBAC, conflict detection, SMS trigger,
                    # treatments, month availability, patient My Records
```
Tests run against an **in-memory SQLite** database so they need no network and
no Supabase credentials. This is the one place SQLite is still used — it is test
infrastructure, not the application. See `phpunit.xml`.

---

## SMS Notification (mock eTextMo gateway)

The app ships with an **eTextMo-compatible mock service** (`app/Services/SmsService.php`) so the feature works offline and is easy to demo:

- `SMS_MOCK=true` (default) → the HTTP call is simulated and every attempt is recorded in `sms_logs` with status `Mocked`.
- `SMS_MOCK=false` → a real `POST` is made to `SMS_ENDPOINT` (`https://api.etextmo.com/api/send-message`) with `api_key`, `sender_id`, `recipients`, `message`; the result is logged as `Sent` or `Failed`.

**Trigger:** when an appointment status is updated to `Confirmed`, `SmsService::sendAppointmentConfirmation()` builds the reminder, sends it to the patient's `contact_no`, and writes a row to `sms_logs`. The owner/secretary can also send manual SMS from **SMS Logs → Send SMS**.

---

## Database Schema (Supabase PostgreSQL)

The authoritative schema lives in [`database/supabase/01_schema.sql`](database/supabase/01_schema.sql)
— 16 tables in the `dental` schema, deliberately kept out of Supabase's
published `public` schema. Summary:

| Table | Columns |
|-------|---------|
| `users` | id, name, email, password, contact_no, role (`Owner`/`Secretary`/`Dentist`/`Patient`), license_no, is_active, remember_token, timestamps |
| `patient_profiles` | id, user_id (FK, unique), age, address, medical_history |
| `appointments` | id, patient_id (FK→users), dentist_id (FK→users, nullable), appointment_date, time_slot, service_type, status (`Pending`/`Confirmed`/`Completed`/`Cancelled`), **unique nulls not distinct (dentist_id, appointment_date, time_slot)** |
| `treatment_records` | id, appointment_id (FK, unique), patient_id (FK), dentist_id (FK), treatment_details, clinical_notes |
| `prescriptions` / `prescription_items` | e-prescription header + its medication lines |
| `sms_logs` | id, recipient_phone, message, status, sent_at |

**Double-booking guard** is enforced twice:
1. **Database**: `UNIQUE NULLS NOT DISTINCT (dentist_id, appointment_date, time_slot)`
   (PostgreSQL 15+). The `NULLS NOT DISTINCT` matters: a plain unique index
   treats every NULL as distinct, so it would otherwise let unlimited
   unassigned (`NULL` dentist) bookings take the same slot.
2. **Application**: `Appointment::isSlotTaken()` also treats unassigned (`NULL` dentist) slots as occupied, ignores `Cancelled` appointments, and re-checks before insert — the booking endpoint returns **HTTP 409** with a friendly message if the slot was just taken (SQLSTATE `23505` on PostgreSQL).

**Booking calendar** — `GET /appointments/month-availability?dentist_id=X&month=YYYY-MM` powers the passport-style calendar on the booking page. It returns per-day status (`available` / `full` / `past` / `closed` / `unavailable`) plus the earliest bookable date in the window (today → +2 months).

**Clinic hours:** Monday to Saturday, 9:00 AM – 4:00 PM (lunch 12:00–1:00 PM), closed Sundays, holidays open. Slots are 30 minutes (`Appointment::slots()`); past slots for today are excluded in real time.

**Procedures & duty schedule:** every procedure carries an estimated duration (`config/clinic.php`), and a booking reserves that much time — a 1.5-hour cleaning blocks 09:00–10:30, so nothing can be booked inside it. Procedures marked *By appointment* (surgery, braces installation, root canal) are arranged by the clinic instead of self-booked. Dentist duty days live on the account (`users.duty_days`) and are enforced on the booking calendar, the slot list and the booking endpoint.

---

## Project Structure (key files)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/AuthController.php      # login / register (creates patient profile)
│   │   ├── DashboardController.php      # metrics, daily schedule, print report
│   │   ├── PatientController.php        # patient DataTable, detail, treatment records
│   │   ├── AppointmentController.php    # booking, availability, status+SMS trigger
│   │   └── SmsController.php            # sms_logs history + manual send
│   └── Middleware/EnsureRole.php        # role:Owner,Secretary middleware
├── Models/                              # User, PatientProfile, Appointment, TreatmentRecord, SmsLog
└── Services/SmsService.php              # mock eTextMo client
database/
├── migrations/                          # users, patient_profiles, appointments, treatment_records, sms_logs
└── seeders/DatabaseSeeder.php           # demo data for every role
resources/views/
├── layouts/app.blade.php                # Bootstrap 5 layout, print CSS, AJAX helpers
├── dashboard/{index,print}.blade.php
├── patients/{index,show}.blade.php
├── appointments/{index,book}.blade.php
└── sms/index.blade.php
routes/web.php                           # auth + role-gated route groups
tests/Feature/ClinicSystemTest.php       # acceptance-criteria tests
```

---

## Extending (ideas for the capstone write-up)

- **True real-time**: swap the AJAX availability polling for Laravel Reverb / Pusher channels — the booking + confirmation flow already has the events points where a broadcast could hook in.
- **Queued SMS**: set `QUEUE_CONNECTION=database` (already in `.env`) and wrap `SmsService::send()` in a queued job for production-grade delivery.
- **Audit trail**: add `causer_id` columns or an activity-log table; the middleware + controller boundaries are the natural insertion points.
