<?php

namespace Tests\Feature;

use App\Models\AttendanceSheet;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regression: months were saved as "2026-10-01 00:00:00" but looked up as "2026-10-01" (SQLite). */
class MonthStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_the_same_month_twice_reopens_the_existing_sheet(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/attendance', ['project_id' => 1, 'month' => '2026-10', 'holidays' => '2, 19, 20'])->assertRedirect('/attendance/1');
        $this->post('/attendance', ['project_id' => 1, 'month' => '2026-10'])->assertRedirect('/attendance/1');
        $this->assertSame(1, AttendanceSheet::query()->count());
        $this->assertSame('2026-10-01', AttendanceSheet::query()->toBase()->value('month'));
    }

    public function test_attendance_list_shows_a_started_sheet_as_started(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/attendance', ['project_id' => 1, 'month' => '2026-10']);

        $this->get('/attendance?month=2026-10')->assertSee('Open sheet')->assertDontSee('Not started');
    }

    public function test_bill_month_filter_finds_the_bill(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(AttendanceService::class);
        $sheet = $service->open(Project::query()->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $admin, null);
        $this->actingAs($admin)->post('/invoices', ['attendance_sheet_id' => $sheet->id]);

        $this->assertSame('2026-10-01', Invoice::query()->toBase()->value('month'));
        $this->get('/invoices?month=2026-10')->assertSee('ICMR/MPH/01/2026');
    }
}
