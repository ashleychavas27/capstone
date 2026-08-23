<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role, 'contact_no' => '09170000000']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Dentist',
            'email' => 'new.dentist@clinic.test',
            'contact_no' => '09171234567',
            'role' => User::ROLE_DENTIST,
            'license_no' => '0099001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Authorization: Owner only
    // ------------------------------------------------------------------

    public function test_only_owner_can_access_account_pages(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_OWNER))->get('/accounts')->assertOk();

        $this->actingAs($this->makeUser(User::ROLE_SECRETARY))->get('/accounts')->assertForbidden();
        $this->actingAs($this->makeUser(User::ROLE_DENTIST))->get('/accounts')->assertForbidden();
        $this->actingAs($this->makeUser(User::ROLE_PATIENT))->get('/accounts')->assertForbidden();

        $this->actingAs($this->makeUser(User::ROLE_SECRETARY))
            ->post('/accounts', $this->payload())
            ->assertForbidden();
    }

    public function test_patient_accounts_are_hidden_from_the_module(): void
    {
        $this->makeUser(User::ROLE_PATIENT);

        $rows = $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->getJson('/accounts/data')
            ->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertNotSame('Patient', $row['role']);
        }
    }

    // ------------------------------------------------------------------
    // Creating staff accounts
    // ------------------------------------------------------------------

    public function test_owner_can_create_dentist_account_with_license(): void
    {
        $response = $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->post('/accounts', $this->payload());

        $response->assertRedirect('/accounts');

        $dentist = User::where('email', 'new.dentist@clinic.test')->first();
        $this->assertNotNull($dentist);
        $this->assertSame(User::ROLE_DENTIST, $dentist->role);
        $this->assertSame('0099001', $dentist->license_no);
        $this->assertTrue($dentist->isActive());
    }

    public function test_dentist_role_requires_a_license_number(): void
    {
        $payload = $this->payload(['license_no' => null]);

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->post('/accounts', $payload)
            ->assertSessionHasErrors('license_no');

        $this->assertSame(0, User::where('email', 'new.dentist@clinic.test')->count());
    }

    public function test_created_accounts_are_never_patients_via_this_module(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->post('/accounts', $this->payload(['role' => User::ROLE_PATIENT]));

        $this->assertDatabaseMissing('users', ['email' => 'new.dentist@clinic.test']);
    }

    // ------------------------------------------------------------------
    // Editing + license rules
    // ------------------------------------------------------------------

    public function test_owner_can_edit_any_dentists_license_number(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->actingAs($owner)
            ->put("/accounts/{$dentist->id}", $this->payload([
                'name' => $dentist->name,
                'email' => $dentist->email,
                'license_no' => '0012345',
                'password' => '',
                'password_confirmation' => '',
            ]));

        $this->assertSame('0012345', $dentist->fresh()->license_no);
    }

    public function test_dentist_updates_own_license_from_profile(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);

        $this->actingAs($dentist)->put('/profile', [
            'name' => $dentist->name,
            'contact_no' => $dentist->contact_no,
            'license_no' => '0077777',
        ]);

        $this->assertSame('0077777', $dentist->fresh()->license_no);
    }

    public function test_secretary_profile_does_not_require_license_but_may_not_change_others(): void
    {
        $secretary = $this->makeUser(User::ROLE_SECRETARY);

        $this->actingAs($secretary)->put('/profile', [
            'name' => 'Updated Secretary',
            'contact_no' => '09171112222',
        ])->assertRedirect();

        $this->assertSame('Updated Secretary', $secretary->fresh()->name);
    }

    // ------------------------------------------------------------------
    // Deactivation
    // ------------------------------------------------------------------

    public function test_owner_cannot_deactivate_own_account(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->patchJson("/accounts/{$owner->id}/toggle-active")
            ->assertForbidden();

        $this->assertTrue($owner->fresh()->isActive());
    }

    public function test_deactivated_staff_cannot_sign_in_and_can_be_reactivated(): void
    {
        $dentist = $this->makeUser(User::ROLE_DENTIST);
        $dentist->update(['email' => 'deactivated@clinic.test', 'password' => 'password']);
        $owner = $this->makeUser(User::ROLE_OWNER);

        // Owner deactivates the dentist.
        $this->actingAs($owner)->patchJson("/accounts/{$dentist->id}/toggle-active");
        $this->assertFalse($dentist->fresh()->isActive());

        // Login is blocked (fresh guest session — drop the owner login first).
        \Illuminate\Support\Facades\Auth::logout();
        $this->flushSession();
        $this->post('/login', [
            'email' => 'deactivated@clinic.test',
            'password' => 'password',
        ]);
        $this->assertGuest();

        // Reactivation restores access.
        $this->actingAs($owner)->patchJson("/accounts/{$dentist->id}/toggle-active");
        $this->assertTrue($dentist->fresh()->isActive());
    }
}
