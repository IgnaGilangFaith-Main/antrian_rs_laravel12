<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $password = 'rahasia'): void
    {
        config(['admin.password' => $password]);

        $this->post('/admin/login', ['password' => $password])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_halaman_terkunci_tanpa_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->post('/admin/queue/reset')->assertRedirect(route('admin.login'));
    }

    public function test_password_salah_ditolak(): void
    {
        config(['admin.password' => 'rahasia']);

        $this->from('/admin/login')
            ->post('/admin/login', ['password' => 'salah'])
            ->assertRedirect('/admin/login')
            ->assertSessionHas('error')
            ->assertSessionMissing('is_admin');

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_bisa_masuk(): void
    {
        $this->login();

        $this->get('/admin')->assertOk()->assertSee('Panel Admin');
    }

    public function test_reseed_membuat_ulang_lima_loket(): void
    {
        $this->login();

        // simulating migrate:fresh: tabel ada tapi kosong.
        Counter::query()->delete();
        $this->assertSame(0, Counter::count());

        $this->post('/admin/counters/reseed')->assertRedirect();

        $this->assertSame(5, Counter::count());
        $this->assertSame(
            [1, 2, 3, 4, 5],
            Counter::orderBy('counter_number')->pluck('counter_number')->all(),
        );
    }

    public function test_reset_mengosongkan_antrean_hari_ini_saja(): void
    {
        $this->login();
        $this->postJson('/queue/take-number');
        $this->postJson('/queue/take-number');

        // Kemarin harus tetap utuh.
        Queue::create([
            'queue_date' => today()->subDay()->toDateString(),
            'queue_number' => 1,
            'queue_label' => 'A-001',
            'status' => Queue::STATUS_DONE,
        ]);

        $this->post('/admin/queue/reset')->assertRedirect();

        $this->assertSame(0, Queue::where('queue_date', today()->toDateString())->count());
        $this->assertSame(1, Queue::where('queue_date', today()->subDay()->toDateString())->count());
    }

    public function test_setelah_reset_nomor_mulai_lagi_dari_satu(): void
    {
        $this->login();
        $this->postJson('/queue/take-number');
        $this->post('/admin/queue/reset');

        $this->postJson('/queue/take-number')
            ->assertCreated()
            ->assertJsonPath('queue_label', 'A-001');
    }

    public function test_toggle_menonaktifkan_loket(): void
    {
        $this->login();
        $counter = Counter::create(['code' => '1', 'counter_number' => 1, 'is_active' => true]);

        $this->post("/admin/counters/{$counter->id}/toggle")->assertRedirect();
        $this->assertFalse($counter->fresh()->is_active);

        $this->post("/admin/counters/{$counter->id}/toggle")->assertRedirect();
        $this->assertTrue($counter->fresh()->is_active);
    }

    public function test_loket_tidak_aktif_menghasilkan_404_di_board(): void
    {
        $counter = Counter::create(['code' => '1', 'counter_number' => 1, 'is_active' => false]);

        $this->get("/petugas/loket/{$counter->id}")->assertNotFound();
    }

    public function test_logout_menghapus_session(): void
    {
        $this->login();

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertNull(session('is_admin'));
    }
}
