<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\Uploads\StaleImports;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_locks_after_five_wrong_passwords_even_with_right_password(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com', 'password' => 'Right-pass1']);
        foreach (range(1, 5) as $i) {
            // vary the IP so only the per-account lock (not the per-IP throttle) can stop it
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"])
                ->post('/login', ['email' => 'OPS@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99'])
            ->post('/login', ['email' => 'ops@example.com', 'password' => 'Right-pass1'])
            ->assertSessionHasErrors(['email' => 'Too many wrong attempts. Try again in 15 minute(s) or ask your admin to reset the password.']);
        $this->assertGuest();
    }

    public function test_successful_sign_in_clears_the_counter(): void
    {
        User::factory()->create(['email' => 'ops@example.com', 'password' => 'Right-pass1']);
        foreach (range(1, 4) as $i) {
            $this->post('/login', ['email' => 'ops@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'ops@example.com', 'password' => 'Right-pass1'])->assertRedirect();
        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts('login:ops@example.com'));
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeaderMissing('Strict-Transport-Security');   // plain http in tests
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_abandoned_import_files_older_than_a_day_are_removed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/masters/old.xlsx', 'x');
        Storage::disk('local')->put('imports/masters/new.xlsx', 'x');
        touch(Storage::disk('local')->path('imports/masters/old.xlsx'), time() - 90000);
        StaleImports::purge('imports/masters');
        Storage::disk('local')->assertMissing('imports/masters/old.xlsx');
        Storage::disk('local')->assertExists('imports/masters/new.xlsx');
    }
}
