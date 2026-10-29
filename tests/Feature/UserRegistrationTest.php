<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_employee_account_and_returns_a_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'AeroTech Employee',
            'email' => 'employee@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'roles' => ['IT Asset Manager'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'AeroTech Employee')
            ->assertJsonPath('user.email', 'employee@example.com')
            ->assertJsonPath('user.roles', ['Employee/Requestor'])
            ->assertJsonPath('user.permissions', [
                'purchase_requests.create',
                'asset_requests.create',
                'transfers.create',
                'maintenance.create',
                'licenses.request',
                'disposals.create',
            ])
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);

        $user = User::query()->where('email', 'employee@example.com')->firstOrFail();

        $this->assertModelExists($user);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertTrue($user->hasRole('Employee/Requestor'));
        $this->assertFalse($user->hasRole('IT Asset Manager'));
    }

    public function test_registration_requires_a_name_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/register')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_an_existing_email_address(): void
    {
        User::factory()->create(['email' => 'employee@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Another Employee',
            'email' => 'employee@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_passwords_shorter_than_eight_characters(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'AeroTech Employee',
            'email' => 'employee@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'AeroTech Employee',
            'email' => 'employee@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }
}
