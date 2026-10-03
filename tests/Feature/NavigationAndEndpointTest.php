<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The desktop sidebar (which links each role gets) plus the CRUD endpoints
 * that had no coverage: account activation, manual SMS, profile updates,
 * appointment deletion and the table filters.
 */
class NavigationAndEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'contact_no' => '09170000000',
        ], $attributes));
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

    /** Sidebar link labels rendered for a role. */
    private function sidebarLinks(User $user): array
    {
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        // Only the sidebar markup, not the mobile navbar or the avatar dropdown.
        $start = strpos($html, '<aside class="app-sidebar');
        $this->assertNotFalse($start, 'The sidebar was not rendered for this role.');
        $aside = substr($html, $start);
        $end = strpos($aside, '</aside>');
        $aside = substr($aside, 0, $end);

        preg_match_all('/<i class="bi bi-[a-z0-9-]+"><\/i>([A-Za-z0-9 \-]+)/', $aside, $m);

        return array_map('trim', $m[1]);
    }

    // ------------------------------------------------------------------
    // Sidebar
    // ------------------------------------------------------------------

    public function test_owner_sidebar_lists_every_destination_including_accounts(): void
    {
        $links = $this->sidebarLinks($this->makeUser(User::ROLE_OWNER));

        $this->assertContains('Dashboard', $links);
        $this->assertContains('Patients', $links);
        $this->assertContains('Calendar', $links);
        $this->assertContains('e-Prescription', $links);
        $this->assertContains('Book Appointment', $links);
        $this->assertContains('All Schedule', $links);
        $this->assertContains('SMS Logs', $links);
        $this->assertContains('Accounts', $links);
        $this->assertContains('My Profile', $links);
    }

    public function test_secretary_sidebar_has_schedule_tools_but_no_accounts(): void
    {
        $links = $this->sidebarLinks($this->makeUser(User::ROLE_SECRETARY));

        $this->assertContains('All Schedule', $links);
        $this->assertContains('SMS Logs', $links);
        $this->assertNotContains('Accounts', $links);
    }

    public function test_dentist_sidebar_has_no_administration_links(): void
    {
        $links = $this->sidebarLinks($this->makeUser(User::ROLE_DENTIST));

        $this->assertContains('Patients', $links);
        $this->assertContains('Calendar', $links);
        $this->assertContains('e-Prescription', $links);
        $this->assertNotContains('Accounts', $links);
        $this->assertNotContains('All Schedule', $links);
        $this->assertNotContains('SMS Logs', $links);
    }

    public function test_patient_sidebar_offers_only_self_service_links(): void
    {
        $links = $this->sidebarLinks($this->makePatient());

        $this->assertContains('Dashboard', $links);
        $this->assertContains('My Records', $links);
        $this->assertContains('Book Appointment', $links);
        $this->assertNotContains('Patients', $links);
        $this->assertNotContains('Accounts', $links);
        $this->assertNotContains('SMS Logs', $links);
    }

    public function test_every_sidebar_link_actually_resolves(): void
    {
        $html = $this->actingAs($this->makeUser(User::ROLE_OWNER))->get('/dashboard')->getContent();

        preg_match_all('/<a class="sidebar-link[^"]*" href="([^"]+)"/', $html, $m);

        $this->assertNotEmpty($m[1]);

        foreach (array_unique($m[1]) as $href) {
            $path = parse_url($href, PHP_URL_PATH);
            $this->assertNotSame('/', $path, "Sidebar link {$href} points at the site root.");
        }
    }

    public function test_sidebar_markup_is_absent_for_guests(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('<aside class="app-sidebar', $html);
        // The CSS contains body.has-sidebar rules on every page, so the body
        // tag itself is what must not carry the class.
        $this->assertSame(1, preg_match('/<body[^>]*>/', $html, $m));
        $this->assertStringNotContainsString('has-sidebar', $m[0]);
    }

    public function test_signed_in_pages_opt_into_the_sidebar_layout(): void
    {
        $html = $this->actingAs($this->makeUser(User::ROLE_OWNER))->get('/dashboard')->getContent();

        $this->assertStringContainsString('<aside class="app-sidebar', $html);
        $this->assertSame(1, preg_match('/<body[^>]*>/', $html, $m));
        $this->assertStringContainsString('has-sidebar', $m[0]);
    }

    public function test_print_views_do_not_render_the_sidebar(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $patient = $this->makePatient();

        Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => now()->toDateString(),
            'time_slot' => '09:00',
            'service_type' => 'General Checkup',
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        foreach (['/dashboard/print' => $owner, '/patients/'.$patient->id.'/history/print' => $dentist] as $path => $user) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('<aside class="app-sidebar', $html, "Sidebar leaked into {$path}");
        }
    }

    // ------------------------------------------------------------------
    // Account activation
    // ------------------------------------------------------------------

    public function test_deactivating_a_staff_account_toggles_the_flag(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->actingAs($owner)
            ->patchJson("/accounts/{$dentist->id}/toggle-active")
            ->assertOk()
            ->assertJson(['is_active' => false]);

        $this->assertFalse($dentist->fresh()->isActive());

        $this->actingAs($owner)
            ->patchJson("/accounts/{$dentist->id}/toggle-active")
            ->assertOk()
            ->assertJson(['is_active' => true]);

        $this->assertTrue($dentist->fresh()->isActive());
    }

    // ------------------------------------------------------------------
    // Manual SMS
    // ------------------------------------------------------------------

    public function test_secretary_can_send_a_manual_sms(): void
    {
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $this->actingAs($secretary)
            ->post('/sms', ['recipient_phone' => '09170000009', 'message' => 'Manual test message'])
            ->assertRedirect();

        $this->assertDatabaseHas('sms_logs', [
            'recipient_phone' => '09170000009',
            'message' => 'Manual test message',
        ]);
    }

    public function test_manual_sms_requires_a_phone_and_a_message(): void
    {
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $this->actingAs($secretary)
            ->post('/sms', ['recipient_phone' => '', 'message' => ''])
            ->assertSessionHasErrors(['recipient_phone', 'message']);

        $this->assertSame(0, SmsLog::count());
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    public function test_user_can_update_their_own_profile(): void
    {
        $user = $this->makePatient();

        $this->actingAs($user)
            ->put('/profile', ['name' => 'Renamed Person', 'contact_no' => '09171239876'])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('Renamed Person', $fresh->name);
        $this->assertSame('09171239876', $fresh->contact_no);
    }

    // ------------------------------------------------------------------
    // Appointments
    // ------------------------------------------------------------------

    public function test_secretary_can_remove_an_appointment(): void
    {
        $secretary = $this->makeUser(User::ROLE_SECRETARY);
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'time_slot' => '09:00',
            'service_type' => 'General Checkup',
            'status' => Appointment::STATUS_PENDING,
        ]);

        $this->actingAs($secretary)
            ->deleteJson("/appointments/{$appointment->id}")
            ->assertOk();

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }

    public function test_appointment_feed_can_be_filtered_by_status_and_date(): void
    {
        $secretary = $this->makeUser(User::ROLE_SECRETARY);
        $patient = $this->makePatient();
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $target = now()->addDays(3)->toDateString();

        Appointment::create([
            'patient_id' => $patient->id, 'dentist_id' => $dentist->id,
            'appointment_date' => $target, 'time_slot' => '09:00',
            'service_type' => 'General Checkup', 'status' => Appointment::STATUS_CONFIRMED,
        ]);
        Appointment::create([
            'patient_id' => $patient->id, 'dentist_id' => $dentist->id,
            'appointment_date' => now()->addDays(4)->toDateString(), 'time_slot' => '10:00',
            'service_type' => 'General Checkup', 'status' => Appointment::STATUS_PENDING,
        ]);

        $byStatus = $this->actingAs($secretary)
            ->getJson('/appointments/data?status=Confirmed')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $byStatus);
        $this->assertSame('Confirmed', $byStatus[0]['status']);

        $byDate = $this->actingAs($secretary)
            ->getJson('/appointments/data?date='.$target)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $byDate);
        $this->assertSame($target, $byDate[0]['date_raw']);
    }
}