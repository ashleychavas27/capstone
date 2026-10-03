-- ===========================================================================
--  Dental Clinic — demo / reference seed data (PostgreSQL / Supabase)
-- ===========================================================================
--  An SQL port of database/seeders/DatabaseSeeder.php so a fresh Supabase
--  project can be filled from the SQL Editor without running PHP.
--
--  Both paths stay equivalent:
--    * this file            -> paste into the Supabase SQL Editor
--    * `php artisan db:seed` -> runs DatabaseSeeder.php
--  Do ONE of them, not both.
--
--  Safe to run twice: the whole seed is skipped when `users` already has rows,
--  so it can never overwrite data that a clinic has already entered.
--
--  All demo accounts use the password: password
--    owner@clinic.test          (Owner)
--    secretary@clinic.test      (Secretary)
--    dentist1@clinic.test       (Dentist — Jana Gay Gil, duty Mon/Tue/Wed/Fri)
--    dentist2@clinic.test       (Dentist — Wenca Louise Dajay Ortizo, duty Thu/Sat)
--    ana.villanueva@clinic.test (Patient)
-- ===========================================================================

set search_path to dental, public;

-- Helper used only by this seed: the nearest date to `start_date`, searching in
-- `direction` (1 = forwards, -1 = backwards), on which the clinic is open
-- (Monday-Saturday) and the dentist is on duty. Dropped again at the end.
create or replace function dental_seed_duty_date(start_date date, duty_days int[], direction int)
returns date
language plpgsql
as $$
declare
    candidate date := start_date;
begin
    loop
        if extract(isodow from candidate)::int <> 7
           and extract(isodow from candidate)::int = any(duty_days) then
            return candidate;
        end if;

        candidate := candidate + make_interval(days => direction);
    end loop;
end;
$$;

do $seed$
declare
    -- Single bcrypt hash (cost 12, matching BCRYPT_ROUNDS) of "password".
    pw     constant text := '$2y$12$iY/Iw2Wfbv7o5gb6lQMVxu7LdyYC9T2pi9ML5Fi032/mN9skybcK2';
    owner  bigint;
    d1     bigint;
    d2     bigint;
    p_ana  bigint;
    p_bry  bigint;
    p_car  bigint;
    p_dan  bigint;
    p_eri  bigint;
    p_fra  bigint;
    p_gin  bigint;
    p_hec  bigint;
    a1     bigint;
    a2     bigint;
    a3     bigint;
    a4     bigint;
    a5     bigint;
    a6     bigint;
    a7     bigint;
    a8     bigint;
    a9     bigint;
    a10    bigint;
    a11    bigint;
    rx     bigint;
begin
    if exists (select 1 from dental.users) then
        raise notice 'Demo seed skipped: dental.users already contains rows.';
        return;
    end if;

    -- -----------------------------------------------------------------------
    -- Staff accounts
    -- -----------------------------------------------------------------------
    insert into dental.users (name, email, password, contact_no, role, license_no)
        values ('Maria Santos', 'owner@clinic.test', pw, '09171234567', 'Owner', null)
        returning id into owner;

    insert into dental.users (name, email, password, contact_no, role)
        values ('Juan Dela Cruz', 'secretary@clinic.test', pw, '09179876543', 'Secretary');

    -- Duty days are kept on the account (users.duty_days) because the booking
    -- rules read them: Dr. Gil works Mon/Tue/Wed/Fri, Dr. Ortizo Thu/Sat.
    insert into dental.users (name, email, password, contact_no, role, license_no, duty_days)
        values ('Jana Gay Gil', 'dentist1@clinic.test', pw, '09173000000', 'Dentist', '0072145', '1,2,3,5')
        returning id into d1;

    insert into dental.users (name, email, password, contact_no, role, license_no, duty_days)
        values ('Wenca Louise Dajay Ortizo', 'dentist2@clinic.test', pw, '09174111111', 'Dentist', '0089332', '4,6')
        returning id into d2;

    -- -----------------------------------------------------------------------
    -- Patients + their clinical profiles
    -- -----------------------------------------------------------------------
    insert into dental.users (name, email, password, contact_no, role)
        values ('Ana Villanueva', 'ana.villanueva@clinic.test', pw, '09181234001', 'Patient')
        returning id into p_ana;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_ana, 29, '12 Mabini St., Quezon City', 'Asthma; allergic to penicillin.');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Bryan Reyes', 'bryan.reyes@clinic.test', pw, '09181234002', 'Patient')
        returning id into p_bry;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_bry, 35, '88 P. Burgos St., Manila', 'Hypertension; takes maintenance meds.');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Carla Mendoza', 'carla.mendoza@clinic.test', pw, '09181234003', 'Patient')
        returning id into p_car;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_car, 24, '3 Sampaguita Ave., Makati', 'None.');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Daniel Cruz', 'daniel.cruz@clinic.test', pw, '09181234004', 'Patient')
        returning id into p_dan;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_dan, 41, '55 Rizal Ave., Pasig', 'Diabetes type 2.');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Erika Torres', 'erika.torres@clinic.test', pw, '09181234005', 'Patient')
        returning id into p_eri;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_eri, 19, '77 Katipunan Rd., Marikina', 'Mild anemia.');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Francis Garcia', 'francis.garcia@clinic.test', pw, '09181234006', 'Patient')
        returning id into p_fra;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_fra, 52, '21 Espana Blvd., Manila', 'Heart condition; on aspirin.');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Gina Flores', 'gina.flores@clinic.test', pw, '09181234007', 'Patient')
        returning id into p_gin;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_gin, 33, '9 Banawe St., Quezon City', 'Pregnant (2nd trimester).');

    insert into dental.users (name, email, password, contact_no, role)
        values ('Hector Ramos', 'hector.ramos@clinic.test', pw, '09181234008', 'Patient')
        returning id into p_hec;
    insert into dental.patient_profiles (user_id, age, address, medical_history)
        values (p_hec, 47, '101 Ortigas Ave., Pasig', 'None.');

    -- -----------------------------------------------------------------------
    -- Appointments: 4 completed, 4 confirmed, 2 pending, 1 cancelled
    -- -----------------------------------------------------------------------
    -- Demo dates slide to the nearest day the clinic is open (Monday-Saturday)
    -- and the dentist is on duty, so the seeded schedule matches the booking
    -- rules. duration_minutes is the procedure's estimate from
    -- config/clinic.php (30 minutes when the clinic gave no estimate).
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_ana, d1, dental_seed_duty_date(current_date - 14, '{1,2,3,5}', -1), '09:00', 'Dental Cleaning', 90, 'Completed')
        returning id into a1;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_bry, d2, dental_seed_duty_date(current_date - 10, '{4,6}', -1), '10:30', 'Tooth Extraction', 60, 'Completed')
        returning id into a2;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_car, d1, dental_seed_duty_date(current_date - 7, '{1,2,3,5}', -1), '14:00', 'General Checkup', 30, 'Completed')
        returning id into a3;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_dan, d2, dental_seed_duty_date(current_date - 3, '{4,6}', -1), '14:30', 'Dental Filling', 60, 'Completed')
        returning id into a4;

    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_eri, d1, dental_seed_duty_date(current_date, '{1,2,3,5}', 1), '11:00', 'General Checkup', 30, 'Confirmed')
        returning id into a5;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_fra, d2, dental_seed_duty_date(current_date, '{4,6}', 1), '13:30', 'Dental Cleaning', 90, 'Confirmed')
        returning id into a6;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_gin, d1, dental_seed_duty_date(current_date + 1, '{1,2,3,5}', 1), '09:30', 'Root Canal Treatment', 30, 'Confirmed')
        returning id into a7;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_hec, d2, dental_seed_duty_date(current_date + 2, '{4,6}', 1), '10:00', 'Braces / Orthodontic Consultation', 30, 'Confirmed')
        returning id into a8;

    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_ana, d1, dental_seed_duty_date(current_date + 3, '{1,2,3,5}', 1), '14:30', 'Dental Filling', 60, 'Pending')
        returning id into a9;
    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_car, d2, dental_seed_duty_date(current_date + 4, '{4,6}', 1), '15:30', 'Teeth Whitening', 30, 'Pending')
        returning id into a10;

    insert into dental.appointments
        (patient_id, dentist_id, appointment_date, time_slot, service_type, duration_minutes, status)
        values (p_bry, d1, dental_seed_duty_date(current_date + 5, '{1,2,3,5}', 1), '11:30', 'General Checkup', 30, 'Cancelled')
        returning id into a11;

    -- -----------------------------------------------------------------------
    -- Treatment records for the completed consultations
    -- -----------------------------------------------------------------------
    insert into dental.treatment_records
        (appointment_id, patient_id, dentist_id, treatment_details, clinical_notes)
        values (a1, p_ana, d1,
                'Scaling and polishing of all teeth. Mild gingivitis noted.',
                'Patient advised to floss daily and return in 6 months.');
    insert into dental.treatment_records
        (appointment_id, patient_id, dentist_id, treatment_details, clinical_notes)
        values (a2, p_bry, d2,
                'Extraction of lower right first molar under local anesthesia.',
                'Prescribed analgesics. Gauze should remain for 30 minutes.');
    insert into dental.treatment_records
        (appointment_id, patient_id, dentist_id, treatment_details, clinical_notes)
        values (a3, p_car, d1,
                'Full oral examination and X-ray review. No caries detected.',
                'Recommended fluoride varnish application next visit.');
    insert into dental.treatment_records
        (appointment_id, patient_id, dentist_id, treatment_details, clinical_notes)
        values (a4, p_dan, d2,
                'Composite filling on tooth #14. Cavity cleaned and restored.',
                'Avoid chewing on the affected side for 24 hours.');

    -- -----------------------------------------------------------------------
    -- e-Prescriptions
    -- -----------------------------------------------------------------------
    insert into dental.prescriptions
        (patient_id, dentist_id, appointment_id, diagnosis, notes, date_issued)
        values (p_bry, d2, a2,
                'Dental caries; non-restorable lower right first molar.',
                'Return immediately if persistent bleeding or fever occurs.',
                current_date - 10)
        returning id into rx;

    insert into dental.prescription_items
        (prescription_id, drug_name, dosage, frequency, duration, quantity, instructions)
        values
        (rx, 'Amoxicillin 500mg', '500 mg', '3x a day', '7 days', '21 capsules',
             'Take 1 capsule three times daily after meals.'),
        (rx, 'Mefenamic Acid 500mg', '500 mg', 'every 6 hours, as needed', '5 days', '15 tablets',
             'Take only when pain is felt. Take after meals.');

    insert into dental.prescriptions
        (patient_id, dentist_id, appointment_id, diagnosis, notes, date_issued)
        values (p_ana, d1, a1,
                'Mild chronic gingivitis.',
                'Follow-up checkup after 2 weeks. Observe proper oral hygiene.',
                current_date - 14)
        returning id into rx;

    insert into dental.prescription_items
        (prescription_id, drug_name, dosage, frequency, duration, quantity, instructions)
        values
        (rx, 'Chlorhexidine Mouthwash', '10 mL', '2x a day', '14 days', '1 bottle',
             'Rinse for 30 seconds after brushing, morning and evening. Do not swallow.');

    -- -----------------------------------------------------------------------
    -- SMS log: one confirmation per Confirmed appointment (backdated 2 days),
    -- plus one failed attempt to show the failure state in the UI.
    -- -----------------------------------------------------------------------
    -- Argument order mirrors SmsService::sendAppointmentConfirmation():
    --   name, formatted slot, formatted date, service type.
    insert into dental.sms_logs (recipient_phone, message, status, sent_at)
    select u.contact_no,
           format(
               'Good day, %s! Your appointment at %s on %s for %s has been CONFIRMED. Please arrive 15 minutes early. - DentalClinic',
               u.name,
               to_char(to_timestamp(a.time_slot, 'HH24:MI'), 'HH12:MI AM'),
               to_char(a.appointment_date, 'Mon DD, YYYY'),
               a.service_type
           ),
           'Mocked',
           (a.appointment_date - 2) + time '09:00'
      from dental.appointments a
      join dental.users u on u.id = a.patient_id
     where a.status = 'Confirmed';

    insert into dental.sms_logs (recipient_phone, message, status, sent_at)
        values ('09181234009',
                'Your appointment has been CONFIRMED. - Dental Clinic',
                'Failed',
                (current_date - 1) + time '08:30');

    raise notice 'Demo seed complete. Password for every account is "password".';
end
$seed$;

-- The seed helper has done its job; leave no stray functions behind.
drop function if exists dental_seed_duty_date(date, int[], int);
