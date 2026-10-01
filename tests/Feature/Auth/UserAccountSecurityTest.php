<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3A — the User model as a security boundary: hashing, hidden fields,
 * mass assignment, and the accountability timestamps.
 */
class UserAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_is_hashed_on_create(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $this->assertNotSame('ValidPassword123', $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('ValidPassword123', $user->getAttributes()['password']));
        $this->assertStringStartsWith('$2y$', $user->getAttributes()['password']);
    }

    public function test_the_password_is_rehashed_when_changed(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);
        $originalHash = $user->getAttributes()['password'];

        $user->password = 'NewPassword456';
        $user->save();

        $this->assertNotSame($originalHash, $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('NewPassword456', $user->getAttributes()['password']));
    }

    public function test_the_password_and_remember_token_are_hidden_from_array_serialisation(): void
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayHasKey('email', $array);
    }

    public function test_the_password_hash_never_appears_in_json_output(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $json = $user->toJson();

        $this->assertStringNotContainsString($user->getAttributes()['password'], $json);
        $this->assertStringNotContainsString('ValidPassword123', $json);
    }

    public function test_the_password_hash_is_never_rendered_on_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee($user->getAttributes()['password'], false);
        $response->assertDontSee('ValidPassword123');
    }

    public function test_the_email_address_is_normalised_on_write(): void
    {
        $user = User::factory()->create(['email' => '  Operator@WICHOV.Test  ']);

        $this->assertSame('operator@wichov.test', $user->fresh()->email);
    }

    public function test_the_email_address_must_be_unique(): void
    {
        User::factory()->create(['email' => 'operator@wichov.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'operator@wichov.test']);
    }

    public function test_signing_in_records_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
            'last_login_at' => null,
        ]);

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_signing_in_does_not_alter_the_account_updated_timestamp(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ]);

        $updatedAtBefore = $user->updated_at;

        $this->post(route('login.store'), [
            'email' => 'operator@wichov.test',
            'password' => 'ValidPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue($updatedAtBefore->equalTo($user->fresh()->updated_at));
    }

    public function test_the_authenticated_dashboard_shows_the_signed_in_operator(): void
    {
        $user = User::factory()->create([
            'name' => 'Amina Yusuf',
            'email' => 'amina@wichov.test',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Amina Yusuf');
        $response->assertSee('amina@wichov.test');
    }
}
