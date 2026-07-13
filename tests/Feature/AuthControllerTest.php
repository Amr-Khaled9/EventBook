<?php

namespace Tests\Feature;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Register Tests ====================

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Amr Khaled',
            'email' => 'amr@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user',
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Registered successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'amr@example.com',
            'name' => 'Amr Khaled',
            'role' => 'user',
        ]);
    }

    public function test_register_fails_without_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'amr@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Amr Khaled',
            'email' => 'amr@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Amr Khaled',
            'email' => 'amr@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_without_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Amr Khaled',
            'email' => 'amr@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ==================== Login Tests ====================

    public function test_user_can_login_successfully(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'amr@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user',
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Logged in successfully',
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'amr@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'notfound@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_login_fails_without_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ==================== Logout Tests ====================

    public function test_user_can_logout_successfully(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Logged out successfully',
            ]);
    }

    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ==================== Forgot Password Tests ====================

    public function test_forgot_password_sends_otp_successfully(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'amr@example.com',
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'amr@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'OTP sent to your email',
            ]);

        $this->assertDatabaseHas('password_reset_otps', [
            'email' => 'amr@example.com',
        ]);

        Mail::assertQueued(OtpMail::class);
    }

    public function test_forgot_password_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_fails_without_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ==================== Reset Password Tests ====================

    public function test_user_can_reset_password_successfully(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'role' => 'user',
        ]);

        PasswordResetOtp::create([
            'email' => 'amr@example.com',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'amr@example.com',
            'otp' => '123456',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Password reset successfully',
            ]);

        // Verify user can login with new password
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'amr@example.com',
            'password' => 'newpassword123',
        ]);
        $loginResponse->assertStatus(200);

        // Verify OTP was deleted after use
        $this->assertDatabaseMissing('password_reset_otps', [
            'email' => 'amr@example.com',
        ]);
    }

    public function test_reset_password_fails_with_invalid_otp(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'role' => 'user',
        ]);

        PasswordResetOtp::create([
            'email' => 'amr@example.com',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'amr@example.com',
            'otp' => '999999',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_reset_password_fails_with_expired_otp(): void
    {
        User::factory()->create([
            'email' => 'amr@example.com',
            'role' => 'user',
        ]);

        PasswordResetOtp::create([
            'email' => 'amr@example.com',
            'otp' => '123456',
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'amr@example.com',
            'otp' => '123456',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_reset_password_fails_without_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'amr@example.com',
            'otp' => '123456',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'amr@example.com',
            'otp' => '123456',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
