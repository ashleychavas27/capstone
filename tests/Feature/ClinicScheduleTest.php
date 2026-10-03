<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The clinic's own schedule (config/clinic.php + users.duty_days):
 * opening hours, procedure durations and dentist duty days.
 */
class ClinicScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'contact_no' => '09170000000',
        ], $attributes));
    }

    /** Next date (searching forward) that falls on one of the given ISO weekdays. */
    private function nextDateMatching(array $isoWeekdays, int $fromOffset = 1): string
    {
        $date = now()->copy()->addDays($fromOffset);

        while (! in_array((int) $date->isoWeekday(), $isoWeekdays, true)) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    // ------------------------------------------------------------------
    // Opening hours
    // ------------------------------------------------------------------

    public function test_clinic_hours_are_nine_to_four_with_a_lunch_break(): void
    {
        $slots = Appointment::slots();

        $this->assertContains('09:00', $slots);
        $this->assertContains('11:30', $slots);
        $this->assertContains('13:00', $slots);
        $this->assertContains('15:30', $slots);

        // Nothing starts after the 4:00 PM close, and the lunch hour is skipped.
        $this->assertNotContains('16:00', $slots);
        $this->assertNotContains('16:30', $slots);
        $this->assertNotContains('12:00', $slots);
        $this->assertNotContains('12:30', $slots);

        $this->assertSame('Monday to Saturday', Appointment::openDaysLabel());
        $this->assertSame('9:00 AM – 4:00 PM', Appointment::hoursLabel());
    }

    public function test_clinic_is_closed_on_sundays(): void
    {
        $sunday = $this->nextDateMatching([7]);
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->assertFalse(Appointment::isOpenOn($sunday));
        $this->assertSame([], Appointment::freeSlots($dentist->id, $sunday));

        // The month calendar reports the day as closed rather than simply full.
        $response = $this->actingAs($this->makeUser(User::ROLE_PATIENT))
            ->getJson('/appointments/month-availability?dentist_id='.$dentist->id.'&month='.substr($sunday, 0, 7))
            ->assertOk();

        $day = collect($response->json('days'))->firstWhere('date', $sunday);
        $this->assertSame('closed', $day['status']);
    }

    // ------------------------------------------------------------------
    // Procedure durations
    // ------------------------------------------------------------------

    public function test_a_long_procedure_blocks_every_slot_it_overlaps(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makeUser(User::ROLE_PATIENT);
        $date = $this->nextDateMatching([1, 2, 3, 4, 5, 6]);

        // Dental Cleaning reserves 90 minutes, so 09:00 runs to 10:30.
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => $date,
            'time_slot' => '09:00',
            'service_type' => 'Dental Cleaning',
            'duration_minutes' => Appointment::durationFor('Dental Cleaning'),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->assertSame(90, Appointment::durationFor('Dental Cleaning'));

        // A 30-minute procedure may not start inside the reserved window…
        foreach (['09:00', '09:30', '10:00'] as $blocked) {
            $this->assertTrue(
                Appointment::hasConflict($dentist->id, $date, $blocked, 30),
                "Expected $blocked to be blocked by the 90-minute cleaning."
            );
        }

        // …but may start once the window is over.
        $this->assertFalse(Appointment::hasConflict($dentist->id, $date, '10:30', 30));

        $free = Appointment::freeSlots($dentist->id, $date, 30);
        $this->assertNotContains('09:00', $free);
        $this->assertNotContains('10:00', $free);
        $this->assertContains('10:30', $free);

        // A 60-minute procedure cannot start at 15:30 — it would pass closing time.
        $this->assertFalse(Appointment::fitsOpeningHours('15:30', 60, $date));
        $this->assertTrue(Appointment::fitsOpeningHours('15:00', 60, $date));
    }

    public function test_availability_endpoint_reserves_the_selected_procedure_length(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makeUser(User::ROLE_PATIENT);
        $date = $this->nextDateMatching([1, 2, 3, 4, 5, 6]);

        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => $date,
            'time_slot' => '13:00',
            'service_type' => 'Dental Filling',
            'duration_minutes' => 60,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($patient)
            ->getJson('/appointments/available-slots?dentist_id='.$dentist->id.'&date='.$date.'&service_type='.urlencode('Dental Cleaning'))
            ->assertOk();

        $slots = $response->json('slots');

        $this->assertSame(90, $response->json('duration_minutes'));

        // The filling holds 13:00-14:00, so a 90-minute cleaning cannot start at
        // 13:00 or 13:30 (both would run into it), but 14:00 fits (14:00-15:30).
        $this->assertNotContains('13:00', $slots);
        $this->assertNotContains('13:30', $slots);
        $this->assertContains('14:00', $slots);
    }

    // ------------------------------------------------------------------
    // Dentist duty days
    // ------------------------------------------------------------------

    public function test_booking_is_refused_on_a_dentists_off_duty_day(): void
    {
        // Duty on Mondays only.
        $dentist = $this->makeUser(User::ROLE_DENTIST, ['duty_days' => '1']);
        $patient = $this->makeUser(User::ROLE_PATIENT);
        $tuesday = $this->nextDateMatching([2]);

        $this->assertFalse(Appointment::isDentistOnDuty($dentist->id, $tuesday));
        $this->assertSame([], Appointment::freeSlots($dentist->id, $tuesday));

        $this->actingAs($patient)
            ->postJson('/appointments/book', [
                'dentist_id' => $dentist->id,
                'appointment_date' => $tuesday,
                'time_slot' => '09:00',
                'service_type' => 'Dental X-ray',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('appointment_date');

        $this->assertDatabaseMissing('appointments', [
            'dentist_id' => $dentist->id,
            'appointment_date' => $tuesday,
        ]);
    }

    public function test_month_calendar_marks_off_duty_days_separately(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST, ['duty_days' => '1']); // Mondays
        $patient = $this->makeUser(User::ROLE_PATIENT);
        $tuesday = $this->nextDateMatching([2]);

        $response = $this->actingAs($patient)
            ->getJson('/appointments/month-availability?dentist_id='.$dentist->id.'&month='.substr($tuesday, 0, 7))
            ->assertOk();

        $day = collect($response->json('days'))->firstWhere('date', $tuesday);
        $this->assertSame('off_duty', $day['status']);
    }

    public function test_dentists_without_recorded_duty_days_work_every_open_day(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST); // duty_days is null
        $date = $this->nextDateMatching([1, 2, 3, 4, 5, 6]);

        $this->assertTrue(Appointment::isDentistOnDuty($dentist->id, $date));
        $this->assertNotEmpty(Appointment::freeSlots($dentist->id, $date));
    }

    public function test_owner_must_give_a_dentist_at_least_one_duty_day(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->post('/accounts', [
                'name' => 'New Dentist',
                'email' => 'new.dentist@clinic.test',
                'contact_no' => '09171234567',
                'role' => User::ROLE_DENTIST,
                'license_no' => '0099001',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors('duty_days');

        $this->assertDatabaseMissing('users', ['email' => 'new.dentist@clinic.test']);
    }

    // ------------------------------------------------------------------
    // "By appointment" procedures
    // ------------------------------------------------------------------

    public function test_patients_cannot_self_book_a_by_appointment_procedure(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makeUser(User::ROLE_PATIENT);
        $date = $this->nextDateMatching([1, 2, 3, 4, 5, 6]);

        $this->assertFalse(
            collect(Appointment::onlineProcedures())->contains(fn ($p) => $p['name'] === 'Root Canal Treatment')
        );

        $this->actingAs($patient)
            ->postJson('/appointments/book', [
                'dentist_id' => $dentist->id,
                'appointment_date' => $date,
                'time_slot' => '09:00',
                'service_type' => 'Root Canal Treatment',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_type');
    }

    public function test_staff_can_still_record_a_by_appointment_procedure(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makeUser(User::ROLE_PATIENT);
        $date = $this->nextDateMatching([1, 2, 3, 4, 5, 6]);

        $this->actingAs($this->makeUser(User::ROLE_SECRETARY))
            ->post('/appointments', [
                'patient_id' => $patient->id,
                'dentist_id' => $dentist->id,
                'appointment_date' => $date,
                'time_slot' => '09:00',
                'service_type' => 'Root Canal Treatment',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'service_type' => 'Root Canal Treatment',
        ]);
    }

    public function test_procedure_table_lists_the_clients_estimates(): void
    {
        $this->assertSame('1 – 1.5 hours', Appointment::durationLabel('Dental Cleaning'));
        $this->assertSame('30 minutes', Appointment::durationLabel('Dental X-ray'));
        $this->assertSame('1 hour', Appointment::durationLabel('Tooth Extraction'));
        $this->assertSame('By appointment', Appointment::durationLabel('Dental Surgery'));

        // Procedures with no estimate fall back to the configured default.
        $this->assertSame(30, Appointment::durationFor('General Checkup'));
    }
}
