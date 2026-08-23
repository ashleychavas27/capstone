<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionCalendarChatTest extends TestCase
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

    private function makeAppointment(User $patient, User $dentist): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'time_slot' => '09:00',
            'service_type' => 'General Checkup',
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    private function prescriptionPayload(User $patient, User $dentist, ?Appointment $appointment = null): array
    {
        return [
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_id' => $appointment?->id,
            'diagnosis' => 'Dental caries #14',
            'notes' => 'Return after 7 days.',
            'date_issued' => now()->toDateString(),
            'drug_name' => ['Amoxicillin 500mg', 'Ibuprofen 400mg'],
            'dosage' => ['500 mg', '400 mg'],
            'frequency' => ['3x a day', 'every 6 hours'],
            'duration' => ['7 days', '5 days'],
            'quantity' => ['21 capsules', ''],
            'instructions' => ['Take after meals.', ''],
        ];
    }

    // ------------------------------------------------------------------
    // Calendar
    // ------------------------------------------------------------------

    public function test_calendar_is_accessible_to_staff_but_not_patients(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_DENTIST))->get('/appointments/calendar')->assertOk();
        $this->actingAs($this->makeUser(User::ROLE_SECRETARY))->get('/appointments/calendar')->assertOk();
        $this->actingAs($this->makePatient())->get('/appointments/calendar')->assertForbidden();
    }

    public function test_dentist_calendar_feed_only_contains_own_appointments(): void
    {
        $dentistA = $this->makeUser(User::ROLE_DENTIST);
        $dentistB = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();

        $this->makeAppointment($patient, $dentistA);
        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentistB->id,
            'appointment_date' => now()->addDays(4)->toDateString(),
            'time_slot' => '10:00',
            'service_type' => 'Tooth Extraction',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($dentistA)->getJson('/appointments/calendar-events');
        $response->assertOk();

        $events = $response->json();
        $this->assertCount(1, $events);
        $this->assertStringContainsString('09:00', $events[0]['title']);
    }

    public function test_calendar_feed_colors_events_by_status(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();
        $appointment = $this->makeAppointment($patient, $dentist);

        $event = $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->getJson('/appointments/calendar-events')
            ->json()[0];

        $this->assertSame(Appointment::STATUS_COMPLETED, $event['extendedProps']['status']);
        $this->assertSame('#B4B1B2', $event['backgroundColor']);
        $this->assertSame((int) $appointment->id, (int) $event['id']);
    }

    // ------------------------------------------------------------------
    // e-Prescription
    // ------------------------------------------------------------------

    public function test_dentist_can_issue_a_prescription_with_items(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();
        $appointment = $this->makeAppointment($patient, $dentist);

        $response = $this->actingAs($dentist)->post('/prescriptions', $this->prescriptionPayload($patient, $dentist, $appointment));

        $response->assertRedirect('/prescriptions');

        $prescription = Prescription::first();
        $this->assertNotNull($prescription);
        $this->assertSame($appointment->id, $prescription->appointment_id);
        $this->assertCount(2, $prescription->items);
        $this->assertSame('Amoxicillin 500mg', $prescription->items->first()->drug_name);
    }

    public function test_secretary_can_view_but_not_issue_prescriptions(): void
    {
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $this->actingAs($secretary)->get('/prescriptions')->assertOk();
        $this->actingAs($secretary)->post('/prescriptions', [])->assertForbidden();

        // Dentists can open the issue form.
        $this->actingAs($this->makeUser(User::ROLE_DENTIST))->get('/prescriptions/create')->assertOk();

        // Patients cannot access prescriptions at all.
        $this->actingAs($this->makePatient())->get('/prescriptions')->assertForbidden();
    }

    public function test_prescription_requires_at_least_one_medication(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();

        $payload = $this->prescriptionPayload($patient, $dentist);
        $payload['drug_name'] = [];

        $response = $this->actingAs($dentist)->post('/prescriptions', $payload);

        $response->assertSessionHasErrors('drug_name');
        $this->assertSame(0, Prescription::count());
    }

    public function test_prescription_print_view_renders_reseta_details(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();
        $appointment = $this->makeAppointment($patient, $dentist);

        $this->actingAs($dentist)->post('/prescriptions', $this->prescriptionPayload($patient, $dentist, $appointment));
        $prescription = Prescription::with('items')->first();

        $response = $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->get("/prescriptions/{$prescription->id}/print");

        $response->assertOk()
            ->assertSee($patient->name)
            ->assertSee('Amoxicillin 500mg')
            ->assertSee('Take after meals.')
            ->assertSee($dentist->name);
    }

    public function test_dentist_can_update_prescription_and_replace_items(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();

        $this->actingAs($dentist)->post('/prescriptions', $this->prescriptionPayload($patient, $dentist));
        $prescription = Prescription::first();

        $payload = $this->prescriptionPayload($patient, $dentist);
        $payload['diagnosis'] = 'Updated diagnosis';
        $payload['drug_name'] = ['Paracetamol 500mg'];

        $response = $this->actingAs($dentist)->put("/prescriptions/{$prescription->id}", $payload);
        $response->assertRedirect('/prescriptions');

        $prescription->refresh();
        $this->assertSame('Updated diagnosis', $prescription->diagnosis);
        $this->assertCount(1, $prescription->items);
        $this->assertSame('Paracetamol 500mg', $prescription->items->first()->drug_name);
    }

    // ------------------------------------------------------------------
    // Diagnostic history print
    // ------------------------------------------------------------------

    public function test_staff_can_print_patient_diagnostic_history(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();
        $appointment = $this->makeAppointment($patient, $dentist);

        $this->actingAs($dentist)->post('/prescriptions', $this->prescriptionPayload($patient, $dentist, $appointment));

        $response = $this->actingAs($this->makeUser(User::ROLE_OWNER))->get("/patients/{$patient->id}/history/print");

        $response->assertOk()
            ->assertSee($patient->name)
            ->assertSee('General Checkup')
            ->assertSee('Amoxicillin 500mg');

        // Patients cannot print other records.
        $this->actingAs($patient)->get("/patients/{$patient->id}/history/print")->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Chat bot
    // ------------------------------------------------------------------

    public function test_chatbot_endpoint_returns_reply_and_logs_messages(): void
    {
        $patient = $this->makePatient();

        $response = $this->actingAs($patient)->postJson('/chatbot', [
            'message' => 'What are your clinic hours?',
        ]);

        $response->assertOk()->assertJsonStructure(['reply']);

        $reply = $response->json('reply');
        $this->assertStringContainsString('9:00 AM', $reply);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $patient->id,
            'sender' => 'user',
            'message' => 'What are your clinic hours?',
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $patient->id,
            'sender' => 'bot',
        ]);
    }

    public function test_chatbot_rejects_empty_message_and_guests(): void
    {
        $patient = $this->makePatient();

        $this->postJson('/chatbot', ['message' => ''])->assertUnauthorized();
        $this->actingAs($patient)->postJson('/chatbot', ['message' => ''])->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // SMS additions
    // ------------------------------------------------------------------

    public function test_booking_sends_pending_acknowledgment_sms(): void
    {
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->actingAs($patient)->postJson('/appointments/book', [
            'dentist_id' => $dentist->id,
            'appointment_date' => now()->addDays(5)->toDateString(),
            'time_slot' => '13:00',
            'service_type' => 'General Checkup',
        ])->assertCreated();

        $log = SmsLog::where('recipient_phone', $patient->contact_no)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('PENDING', $log->message);
    }
}
