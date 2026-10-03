<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\TreatmentRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCalendarAndRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role, 'contact_no' => '09170000000']);
    }

    private function makePatient(): User
    {
        $patient = $this->makeUser(User::ROLE_PATIENT);
        PatientProfile::create([
            'user_id' => $patient->id,
            'age' => 30,
            'address' => '123 Test St.',
            'medical_history' => 'None.',
        ]);

        return $patient;
    }

    /** First N non-Sunday days of a month, returned as Y-m-d strings. */
    private function nonSundayDays(string $month, int $count): array
    {
        $days = [];
        $day = Carbon::parse($month.'-01');

        while (count($days) < $count) {
            if (! $day->isSunday()) {
                $days[] = $day->format('Y-m-d');
            }
            $day->addDay();
        }

        return $days;
    }

    // ------------------------------------------------------------------
    // Month availability (passport-style booking calendar)
    // ------------------------------------------------------------------

    public function test_month_availability_marks_available_and_full_days(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $month = now()->addMonths(1)->format('Y-m');
        [$fullDate, $freeDate] = $this->nonSundayDays($month, 2);

        // Book every slot on one day so it is fully booked.
        foreach (Appointment::slots() as $slot) {
            Appointment::create([
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'appointment_date' => $fullDate,
                'time_slot' => $slot,
                'service_type' => 'General Checkup',
                'status' => Appointment::STATUS_CONFIRMED,
            ]);
        }

        $response = $this->actingAs($patient)
            ->getJson("/appointments/month-availability?dentist_id={$dentist->id}&month={$month}");

        $response->assertOk();
        $days = collect($response->json('days'))->keyBy('date');

        $this->assertSame('full', $days[$fullDate]['status']);
        $this->assertSame(0, $days[$fullDate]['free_slots']);
        $this->assertSame('available', $days[$freeDate]['status']);
        $this->assertGreaterThan(0, $days[$freeDate]['free_slots']);
        $this->assertSame(count(Appointment::slots()), $days[$freeDate]['total_slots']);
    }

    public function test_month_availability_flags_sundays_past_and_out_of_window_days(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $month = now()->addMonths(1)->format('Y-m');

        $sunday = Carbon::parse($month.'-01');
        while (! $sunday->isSunday()) {
            $sunday->addDay();
        }

        // Current month: the first of the month is in the past once we are past day 1.
        $pastMonth = now()->subMonths(1)->format('Y-m');
        $beyondWindow = now()->addMonths(3)->format('Y-m');

        $response = $this->actingAs($patient)->getJson("/appointments/month-availability?dentist_id={$dentist->id}&month={$month}");
        $days = collect($response->json('days'))->keyBy('date');
        $this->assertSame('closed', $days[$sunday->format('Y-m-d')]['status']);

        $past = $this->actingAs($patient)->getJson("/appointments/month-availability?dentist_id={$dentist->id}&month={$pastMonth}");
        $this->assertSame('past', $past->json('days.0.status'));

        $window = $this->actingAs($patient)->getJson("/appointments/month-availability?dentist_id={$dentist->id}&month={$beyondWindow}");
        $this->assertSame('unavailable', $window->json('days.0.status'));
    }

    public function test_month_availability_returns_earliest_bookable_date(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $response = $this->actingAs($patient)
            ->getJson("/appointments/month-availability?dentist_id={$dentist->id}&month=".now()->format('Y-m'));

        $response->assertOk();
        $earliest = $response->json('earliest_available');
        $this->assertNotNull($earliest);

        $parsed = Carbon::parse($earliest);
        $this->assertTrue($parsed->isToday() || $parsed->isFuture(), "Expected today or a future date, got {$earliest}.");
        $this->assertFalse($parsed->isSunday(), 'Earliest available date must not be a Sunday.');
    }

    public function test_month_availability_rejects_invalid_month_and_dentist(): void
    {
        $patient = $this->makePatient();

        $this->actingAs($patient)->getJson('/appointments/month-availability?dentist_id=1&month=2027-13')->assertStatus(422);
        $this->actingAs($patient)->getJson('/appointments/month-availability?dentist_id=999999&month=2027-02')->assertStatus(422);
    }

    public function test_month_availability_requires_authentication(): void
    {
        $this->getJson('/appointments/month-availability?month=2027-02')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Patient My Records page
    // ------------------------------------------------------------------

    private function makeCompletedVisit(User $patient, User $dentist, string $drugName = 'Amoxicillin 500mg'): Appointment
    {
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => now()->subDays(7)->toDateString(),
            'time_slot' => '09:00',
            'service_type' => 'Tooth Extraction',
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        TreatmentRecord::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'treatment_details' => 'Extraction of lower right first molar.',
            'clinical_notes' => 'Advise rest and avoid hot food.',
        ]);

        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_id' => $appointment->id,
            'diagnosis' => 'Non-restorable molar.',
            'notes' => 'Return if bleeding persists.',
            'date_issued' => $appointment->appointment_date,
        ]);
        $prescription->items()->create([
            'drug_name' => $drugName,
            'dosage' => '500 mg',
            'frequency' => '3x a day',
            'duration' => '7 days',
            'quantity' => '21 capsules',
            'instructions' => 'Take after meals.',
        ]);

        return $appointment;
    }

    public function test_patient_can_view_own_records_with_diagnostics_and_prescriptions(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $appointment = $this->makeCompletedVisit($patient, $dentist);
        $prescription = $appointment->prescriptions()->first();

        $this->actingAs($patient)->get('/my-records')
            ->assertOk()
            ->assertSee('Extraction of lower right first molar.')
            ->assertSee('Advise rest and avoid hot food.')
            ->assertSee('Amoxicillin 500mg')
            ->assertSee('Non-restorable molar.');

        $this->actingAs($patient)->get("/my-records/prescriptions/{$prescription->id}/print")
            ->assertOk()
            ->assertSee('Amoxicillin 500mg')
            ->assertSee('Attending Dentist');
    }

    public function test_patient_cannot_print_another_patients_prescription(): void
    {
        $patientA = $this->makePatient();
        $patientB = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $appointment = $this->makeCompletedVisit($patientB, $dentist, 'Ibuprofen 400mg');
        $prescription = $appointment->prescriptions()->first();

        $this->actingAs($patientA)->get("/my-records/prescriptions/{$prescription->id}/print")->assertForbidden();
    }

    public function test_patient_my_records_never_leaks_other_patients_data(): void
    {
        $patientA = $this->makePatient();
        $patientB = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $this->makeCompletedVisit($patientB, $dentist, 'Ibuprofen 400mg');

        $this->actingAs($patientA)->get('/my-records')
            ->assertOk()
            ->assertDontSee('Ibuprofen 400mg')
            ->assertDontSee('Extraction of lower right first molar.');
    }

    public function test_staff_cannot_access_patient_my_records(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_DENTIST))->get('/my-records')->assertForbidden();
        $this->actingAs($this->makeUser(User::ROLE_SECRETARY))->get('/my-records')->assertForbidden();
        $this->actingAs($this->makeUser(User::ROLE_OWNER))->get('/my-records')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Regression: prescription form must carry the issuing dentist
    // ------------------------------------------------------------------

    public function test_prescription_form_includes_issuing_dentist_hidden_field(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->actingAs($dentist)->get('/prescriptions/create')
            ->assertOk()
            ->assertSee('name="dentist_id"', false)
            ->assertSee('value="'.$dentist->id.'"', false);
    }
}
