<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSystemTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role, 'contact_no' => '09170000000']);
    }

    private function makePatient(array $profile = []): User
    {
        $patient = $this->makeUser(User::ROLE_PATIENT);
        PatientProfile::create(array_merge([
            'user_id' => $patient->id,
            'age' => 30,
            'address' => '123 Test St.',
            'medical_history' => 'No known allergies.',
        ], $profile));

        return $patient;
    }

    private function makeAppointment(User $patient, User $dentist, string $date, string $slot, string $status = Appointment::STATUS_PENDING): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => $date,
            'time_slot' => $slot,
            'service_type' => 'General Checkup',
            'status' => $status,
        ]);
    }

    // ------------------------------------------------------------------
    // Authentication
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/patients')->assertRedirect('/login');
    }

    public function test_registration_creates_patient_account_with_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Patient',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'contact_no' => '09171234567',
            'age' => 28,
            'address' => '456 Street',
            'medical_history' => 'Asthma',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertSame(User::ROLE_PATIENT, $user->role);
        $this->assertDatabaseHas('patient_profiles', [
            'user_id' => $user->id,
            'age' => 28,
            'medical_history' => 'Asthma',
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->makeUser(User::ROLE_PATIENT)->update(['email' => 'a@b.test', 'password' => 'password']);

        $this->post('/login', ['email' => 'a@b.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ------------------------------------------------------------------
    // Role-based access control
    // ------------------------------------------------------------------

    public function test_patient_cannot_access_staff_pages(): void
    {
        $patient = $this->makePatient();

        $this->actingAs($patient)->get('/patients')->assertForbidden();
        $this->actingAs($patient)->get('/appointments')->assertForbidden();
        $this->actingAs($patient)->get('/sms')->assertForbidden();
    }

    public function test_dentist_can_access_patients_but_not_schedule_management(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->actingAs($dentist)->get('/patients')->assertOk();
        $this->actingAs($dentist)->get('/appointments')->assertForbidden();
        $this->actingAs($dentist)->get('/sms')->assertForbidden();
    }

    public function test_owner_can_access_all_staff_pages(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($owner)->get('/patients')->assertOk();
        $this->actingAs($owner)->get('/appointments')->assertOk();
        $this->actingAs($owner)->get('/sms')->assertOk();
    }

    // ------------------------------------------------------------------
    // Conflict detection (double booking)
    // ------------------------------------------------------------------

    public function test_booking_an_already_taken_slot_is_rejected(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $this->makeAppointment($patient, $dentist, '2027-01-20', '09:00', Appointment::STATUS_CONFIRMED);

        $response = $this->actingAs($patient)->postJson('/appointments/book', [
            'dentist_id' => $dentist->id,
            'appointment_date' => '2027-01-20',
            'time_slot' => '09:00',
            'service_type' => 'General Checkup',
        ]);

        $response->assertStatus(409);
        $this->assertSame(1, Appointment::count());
    }

    public function test_booking_a_free_slot_succeeds(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $response = $this->actingAs($patient)->postJson('/appointments/book', [
            'dentist_id' => $dentist->id,
            'appointment_date' => '2027-01-20',
            'time_slot' => '10:00',
            'service_type' => 'General Checkup',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'time_slot' => '10:00',
            'status' => Appointment::STATUS_PENDING,
        ]);
    }

    public function test_available_slots_exclude_taken_and_past_slots(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $this->makeAppointment($patient, $dentist, '2027-01-20', '11:00');

        $response = $this->actingAs($patient)->getJson('/appointments/available-slots?dentist_id='.$dentist->id.'&date=2027-01-20');

        $response->assertOk();

        $slots = $response->json('slots');
        $this->assertNotContains('11:00', $slots);
        $this->assertSame(Appointment::freeSlots($dentist->id, '2027-01-20'), $slots);
    }

    // ------------------------------------------------------------------
    // SMS trigger on confirmation
    // ------------------------------------------------------------------

    public function test_confirming_an_appointment_triggers_sms_and_logs_it(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $appointment = $this->makeAppointment($patient, $dentist, '2027-01-20', '09:30');
        $owner = $this->makeUser(User::ROLE_OWNER);

        $response = $this->actingAs($owner)->patchJson("/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response->assertOk()
            ->assertJson(['sms_triggered' => true]);

        $this->assertDatabaseHas('sms_logs', [
            'recipient_phone' => $patient->contact_no,
            'status' => SmsLog::STATUS_MOCKED,
        ]);

        $log = SmsLog::first();
        $this->assertStringContainsString($patient->name, $log->message);
        $this->assertStringContainsString('CONFIRMED', $log->message);
    }

    public function test_updating_status_does_not_resend_sms_if_already_confirmed(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $appointment = $this->makeAppointment($patient, $dentist, '2027-01-20', '13:00', Appointment::STATUS_CONFIRMED);
        $owner = $this->makeUser(User::ROLE_OWNER);

        $response = $this->actingAs($owner)->patchJson("/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response->assertJson(['sms_triggered' => false]);
        $this->assertDatabaseCount('sms_logs', 0);
    }

    // ------------------------------------------------------------------
    // Treatment records
    // ------------------------------------------------------------------

    public function test_dentist_can_add_treatment_record_which_completes_appointment(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $appointment = $this->makeAppointment($patient, $dentist, '2027-01-20', '14:00', Appointment::STATUS_CONFIRMED);

        $response = $this->actingAs($dentist)->post("/patients/{$patient->id}/treatment", [
            'appointment_id' => $appointment->id,
            'treatment_details' => 'Scaling and polishing performed.',
            'clinical_notes' => 'Advise flossing daily.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('treatment_records', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'treatment_details' => 'Scaling and polishing performed.',
        ]);

        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
    }

    public function test_only_dentists_can_add_treatment_records(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $appointment = $this->makeAppointment($patient, $dentist, '2027-01-20', '14:30');
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $response = $this->actingAs($secretary)->post("/patients/{$patient->id}/treatment", [
            'appointment_id' => $appointment->id,
            'treatment_details' => 'Should not be saved.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('treatment_records', 0);
    }

    // ------------------------------------------------------------------
    // Privacy / authorization hardening
    // ------------------------------------------------------------------

    public function test_patient_dashboard_does_not_leak_other_patients_data(): void
    {
        $patient = $this->makePatient();
        $other = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $this->makeAppointment($other, $dentist, now()->toDateString(), '09:00', Appointment::STATUS_CONFIRMED);
        $this->makeAppointment($patient, $dentist, now()->toDateString(), '10:00', Appointment::STATUS_CONFIRMED);

        // Confirmation SMS for the other patient must not reach the patient dashboard.
        $this->makeAppointment($other, $dentist, now()->addDay()->toDateString(), '11:00', Appointment::STATUS_CONFIRMED);
        SmsLog::create([
            'recipient_phone' => $other->contact_no,
            'message' => 'Appointment CONFIRMED.',
            'status' => SmsLog::STATUS_MOCKED,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($patient)->get('/dashboard');

        $response->assertOk();
        $response->assertSee($patient->name);
        $response->assertDontSee($other->name);
        $response->assertDontSee($other->contact_no);
    }

    public function test_patient_cannot_access_print_report(): void
    {
        $patient = $this->makePatient();

        $this->actingAs($patient)->get('/dashboard/print')->assertForbidden();
        $this->actingAs($this->makeUser(User::ROLE_DENTIST))->get('/dashboard/print')->assertForbidden();
        $this->actingAs($this->makeUser(User::ROLE_OWNER))->get('/dashboard/print')->assertOk();
    }

    public function test_patient_data_endpoint_escapes_html(): void
    {
        $patient = $this->makePatient([
            'address' => '<img src=x onerror=alert(1)> Street',
        ]);
        $patient->update(['name' => '<script>alert(1)</script>', 'contact_no' => '0917" onclick="x']);

        $response = $this->actingAs($this->makeUser(User::ROLE_OWNER))->getJson('/patients/data');

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => '&lt;script&gt;alert(1)&lt;/script&gt;',
            'address' => '&lt;img src=x onerror=alert(1)&gt; Street',
        ]);
        $raw = $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $raw);
        $this->assertStringNotContainsString('onerror=alert(1)>', $raw);
    }

    public function test_appointment_data_endpoint_escapes_html(): void
    {
        $patient = $this->makePatient();
        $patient->update(['name' => 'Pat<img src=x onerror=x>']);
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $this->makeAppointment($patient, $dentist, '2027-01-25', '09:00');

        $response = $this->actingAs($this->makeUser(User::ROLE_OWNER))->getJson('/appointments/data');

        $response->assertOk();
        $this->assertStringNotContainsString('<img src=x onerror=x>', $response->getContent());
        $this->assertStringContainsString('&lt;img src=x onerror=x&gt;', $response->getContent());
    }

    public function test_staff_cannot_assign_non_dentist_as_dentist(): void
    {
        $patient = $this->makePatient();
        $owner = $this->makeUser(User::ROLE_OWNER);
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $response = $this->actingAs($secretary)->post('/appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $owner->id, // Owner is not a Dentist
            'appointment_date' => '2027-01-25',
            'time_slot' => '09:00',
            'service_type' => 'Filling',
        ]);

        $response->assertSessionHasErrors('dentist_id');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_booking_rejects_non_patient_recipient(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $response = $this->actingAs($secretary)->postJson('/appointments/book', [
            'dentist_id' => $dentist->id,
            'appointment_date' => '2027-01-25',
            'time_slot' => '09:00',
            'service_type' => 'General Checkup',
            'patient_id' => $dentist->id, // dentists cannot be booked as patients
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('appointments', 0);
    }

    // ------------------------------------------------------------------
    // Staff booking via appointment store
    // ------------------------------------------------------------------

    public function test_staff_cannot_double_book_the_same_slot(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $this->makeAppointment($patient, $dentist, '2027-01-21', '09:00');
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $response = $this->actingAs($secretary)->post('/appointments', [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => '2027-01-21',
            'time_slot' => '09:00',
            'service_type' => 'Filling',
        ]);

        $response->assertSessionHasErrors('time_slot');
        $this->assertSame(1, Appointment::count());
    }
}
