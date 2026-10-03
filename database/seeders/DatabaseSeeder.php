<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\SmsLog;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The clinic database now lives on Supabase and may already contain
        // real records. Seeding is therefore additive-only: it runs once, on an
        // empty database, and never duplicates or overwrites live data.
        // (User::create would otherwise fail on the users.email unique index.)
        if (User::query()->exists()) {
            $this->command?->warn('Demo data skipped: accounts already exist. Nothing was changed.');

            return;
        }

        $password = 'password';

        // ------------------------------------------------------------------
        // Staff accounts
        // ------------------------------------------------------------------
        $owner = User::create([
            'name' => 'Maria Santos',
            'email' => 'owner@clinic.test',
            'password' => $password,
            'contact_no' => '09171234567',
            'role' => User::ROLE_OWNER,
        ]);

        User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'secretary@clinic.test',
            'password' => $password,
            'contact_no' => '09179876543',
            'role' => User::ROLE_SECRETARY,
        ]);

        // Duty days are stored per dentist (users.duty_days) and enforced by the
        // booking rules: Dr. Gil works Mon/Tue/Wed/Fri, Dr. Ortizo Thu/Sat.
        $dentists = [];
        $dentistData = [
            ['Jana Gay Gil', '0072145', '1,2,3,5'],
            ['Wenca Louise Dajay Ortizo', '0089332', '4,6'],
        ];
        foreach ($dentistData as $i => [$name, $license, $dutyDays]) {
            $dentists[] = User::create([
                'name' => $name,
                'email' => 'dentist'.($i + 1).'@clinic.test',
                'password' => $password,
                'contact_no' => '0917'.str_pad((string) (3000000 + $i * 111111), 7, '0', STR_PAD_LEFT),
                'role' => User::ROLE_DENTIST,
                'license_no' => $license,
                'duty_days' => $dutyDays,
            ]);
        }

        // ------------------------------------------------------------------
        // Patients with profiles
        // ------------------------------------------------------------------
        $patientData = [
            ['Ana Villanueva', '09181234001', 29, '12 Mabini St., Quezon City', 'Asthma; allergic to penicillin.'],
            ['Bryan Reyes', '09181234002', 35, '88 P. Burgos St., Manila', 'Hypertension; takes maintenance meds.'],
            ['Carla Mendoza', '09181234003', 24, '3 Sampaguita Ave., Makati', 'None.'],
            ['Daniel Cruz', '09181234004', 41, '55 Rizal Ave., Pasig', 'Diabetes type 2.'],
            ['Erika Torres', '09181234005', 19, '77 Katipunan Rd., Marikina', 'Mild anemia.'],
            ['Francis Garcia', '09181234006', 52, '21 España Blvd., Manila', 'Heart condition; on aspirin.'],
            ['Gina Flores', '09181234007', 33, '9 Banawe St., Quezon City', 'Pregnant (2nd trimester).'],
            ['Hector Ramos', '09181234008', 47, '101 Ortigas Ave., Pasig', 'None.'],
        ];

        $patients = [];
        foreach ($patientData as [$name, $phone, $age, $address, $history]) {
            $patients[] = User::create([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@clinic.test',
                'password' => $password,
                'contact_no' => $phone,
                'role' => User::ROLE_PATIENT,
            ]);
        }

        foreach ($patients as $i => $patient) {
            PatientProfile::create([
                'user_id' => $patient->id,
                'age' => $patientData[$i][2],
                'address' => $patientData[$i][3],
                'medical_history' => $patientData[$i][4],
            ]);
        }

        // ------------------------------------------------------------------
        // Appointments (past + upcoming)
        // ------------------------------------------------------------------
        // Demo dates slide to the nearest day the clinic is open AND the dentist
        // is on duty, so the seeded schedule matches what the booking rules allow.
        $alignDate = function (User $dentist, int $offsetDays): string {
            $date = now()->copy()->addDays($offsetDays);
            $step = $offsetDays < 0 ? -1 : 1;

            while (! Appointment::isOpenOn($date) || ! Appointment::isDentistOnDuty($dentist->id, $date)) {
                $date = $date->copy()->addDays($step);
            }

            return $date->toDateString();
        };

        $appointments = [
            // Past completed appointments (with treatment records)
            ['patient' => $patients[0], 'dentist' => $dentists[0], 'days' => -14, 'slot' => '09:00', 'service' => 'Dental Cleaning', 'status' => Appointment::STATUS_COMPLETED],
            ['patient' => $patients[1], 'dentist' => $dentists[1], 'days' => -10, 'slot' => '10:30', 'service' => 'Tooth Extraction', 'status' => Appointment::STATUS_COMPLETED],
            ['patient' => $patients[2], 'dentist' => $dentists[0], 'days' => -7, 'slot' => '14:00', 'service' => 'General Checkup', 'status' => Appointment::STATUS_COMPLETED],
            ['patient' => $patients[3], 'dentist' => $dentists[1], 'days' => -3, 'slot' => '14:30', 'service' => 'Dental Filling', 'status' => Appointment::STATUS_COMPLETED],
            // Confirmed upcoming (SMS reminders were sent)
            ['patient' => $patients[4], 'dentist' => $dentists[0], 'days' => 0, 'slot' => '11:00', 'service' => 'General Checkup', 'status' => Appointment::STATUS_CONFIRMED],
            ['patient' => $patients[5], 'dentist' => $dentists[1], 'days' => 0, 'slot' => '13:30', 'service' => 'Dental Cleaning', 'status' => Appointment::STATUS_CONFIRMED],
            ['patient' => $patients[6], 'dentist' => $dentists[0], 'days' => 1, 'slot' => '09:30', 'service' => 'Root Canal Treatment', 'status' => Appointment::STATUS_CONFIRMED],
            ['patient' => $patients[7], 'dentist' => $dentists[1], 'days' => 2, 'slot' => '10:00', 'service' => 'Braces / Orthodontic Consultation', 'status' => Appointment::STATUS_CONFIRMED],
            // Pending bookings
            ['patient' => $patients[0], 'dentist' => $dentists[0], 'days' => 3, 'slot' => '14:30', 'service' => 'Dental Filling', 'status' => Appointment::STATUS_PENDING],
            ['patient' => $patients[2], 'dentist' => $dentists[1], 'days' => 4, 'slot' => '15:30', 'service' => 'Teeth Whitening', 'status' => Appointment::STATUS_PENDING],
            // One cancelled
            ['patient' => $patients[1], 'dentist' => $dentists[0], 'days' => 5, 'slot' => '11:30', 'service' => 'General Checkup', 'status' => Appointment::STATUS_CANCELLED],
        ];

        $createdAppointments = [];
        foreach ($appointments as $a) {
            $createdAppointments[] = Appointment::create([
                'patient_id' => $a['patient']->id,
                'dentist_id' => $a['dentist']->id,
                'appointment_date' => $alignDate($a['dentist'], $a['days']),
                'time_slot' => $a['slot'],
                'service_type' => $a['service'],
                'duration_minutes' => Appointment::durationFor($a['service']),
                'status' => $a['status'],
            ]);
        }

        // ------------------------------------------------------------------
        // Treatment records for completed consultations
        // ------------------------------------------------------------------
        $treatmentData = [
            0 => ['Scaling and polishing of all teeth. Mild gingivitis noted.', 'Patient advised to floss daily and return in 6 months.'],
            1 => ['Extraction of lower right first molar under local anesthesia.', 'Prescribed analgesics. Gauze should remain for 30 minutes.'],
            2 => ['Full oral examination and X-ray review. No caries detected.', 'Recommended fluoride varnish application next visit.'],
            3 => ['Composite filling on tooth #14. Cavity cleaned and restored.', 'Avoid chewing on the affected side for 24 hours.'],
        ];

        foreach ($treatmentData as $index => [$details, $notes]) {
            $appt = $createdAppointments[$index];
            TreatmentRecord::create([
                'appointment_id' => $appt->id,
                'patient_id' => $appt->patient_id,
                'dentist_id' => $appt->dentist_id,
                'treatment_details' => $details,
                'clinical_notes' => $notes,
            ]);
        }

        // ------------------------------------------------------------------
        // e-Prescriptions for two of the completed consultations
        // ------------------------------------------------------------------
        $prescriptionData = [
            1 => [ // Bryan Reyes — post-extraction medication
                'diagnosis' => 'Dental caries; non-restorable lower right first molar.',
                'notes' => 'Return immediately if persistent bleeding or fever occurs.',
                'items' => [
                    ['Amoxicillin 500mg', '500 mg', '3x a day', '7 days', '21 capsules', 'Take 1 capsule three times daily after meals.'],
                    ['Mefenamic Acid 500mg', '500 mg', 'every 6 hours, as needed', '5 days', '15 tablets', 'Take only when pain is felt. Take after meals.'],
                ],
            ],
            0 => [ // Ana Villanueva — mild gingivitis
                'diagnosis' => 'Mild chronic gingivitis.',
                'notes' => 'Follow-up checkup after 2 weeks. Observe proper oral hygiene.',
                'items' => [
                    ['Chlorhexidine Mouthwash', '10 mL', '2x a day', '14 days', '1 bottle', 'Rinse for 30 seconds after brushing, morning and evening. Do not swallow.'],
                ],
            ],
        ];

        foreach ($prescriptionData as $index => $rx) {
            $appt = $createdAppointments[$index];

            $prescription = Prescription::create([
                'patient_id' => $appt->patient_id,
                'dentist_id' => $appt->dentist_id,
                'appointment_id' => $appt->id,
                'diagnosis' => $rx['diagnosis'],
                'notes' => $rx['notes'],
                'date_issued' => $appt->appointment_date,
            ]);

            foreach ($rx['items'] as [$drug, $dosage, $frequency, $duration, $quantity, $instructions]) {
                $prescription->items()->create([
                    'drug_name' => $drug,
                    'dosage' => $dosage,
                    'frequency' => $frequency,
                    'duration' => $duration,
                    'quantity' => $quantity,
                    'instructions' => $instructions,
                ]);
            }
        }

        // ------------------------------------------------------------------
        // SMS logs: confirmation reminders for the Confirmed appointments
        // ------------------------------------------------------------------
        $sms = app(SmsService::class);
        foreach ($createdAppointments as $appt) {
            if ($appt->status === Appointment::STATUS_CONFIRMED) {
                $log = $sms->sendAppointmentConfirmation($appt);
                if ($log) {
                    // Backdate a couple of days so the log looks natural
                    $log->update(['sent_at' => $appt->appointment_date->copy()->subDays(2)->setTime(9, 0)]);
                }
            }
        }

        // One failed attempt for demonstration
        SmsLog::create([
            'recipient_phone' => '09181234009',
            'message' => 'Your appointment has been CONFIRMED. - Dental Clinic',
            'status' => SmsLog::STATUS_FAILED,
            'sent_at' => now()->subDays(1)->setTime(8, 30),
        ]);

        $this->command?->info('Demo data seeded. Password for all accounts is "password".');
        $this->command?->info('Owner: '.$owner->email.' | Secretary: secretary@clinic.test | Dentist: dentist1@clinic.test | Patient: ana.villanueva@clinic.test');
    }
}
