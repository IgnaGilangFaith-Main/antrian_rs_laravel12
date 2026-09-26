<?php

namespace Tests\Feature;

use App\Events\QueueCalledEvent;
use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Counter::updateOrCreate(['counter_number' => 1], ['code' => '1', 'is_active' => true]);
        Counter::updateOrCreate(['counter_number' => 2], ['code' => '2', 'is_active' => true]);
    }

    public function test_take_number_is_sequential_per_day(): void
    {
        $this->postJson('/queue/take-number')->assertCreated()->assertJsonPath('queue_label', 'A-001');
        $this->postJson('/queue/take-number')->assertCreated()->assertJsonPath('queue_label', 'A-002');

        $this->assertSame(2, Queue::where('queue_date', today()->toDateString())->count());
    }

    public function test_take_number_stores_token_in_session(): void
    {
        $res = $this->postJson('/queue/take-number')->assertCreated();

        $this->assertSame($res->json('token'), session('queue_token'));
    }

    public function test_call_next_is_fifo(): void
    {
        Event::fake([QueueCalledEvent::class]);
        $this->postJson('/queue/take-number');
        $this->postJson('/queue/take-number');

        $res = $this->postJson('/petugas/loket/1/panggil')->assertOk();

        $this->assertSame(1, $res->json('queue.queue_number'));
        $this->assertSame(Queue::STATUS_CALLED, $res->json('queue.status'));
        $this->assertNotNull(
            $res->json('queue.called_at'),
            'called_at harus terisi setelah callNext().'
        );
    }

    public function test_two_counters_never_get_the_same_queue(): void
    {

        $this->postJson('/queue/take-number');
        $this->postJson('/queue/take-number');

        $a = $this->postJson('/petugas/loket/1/panggil')->assertOk();
        $b = $this->postJson('/petugas/loket/2/panggil')->assertOk();

        $this->assertNotSame($a->json('queue.id'), $b->json('queue.id'));
    }

    public function test_call_next_rejects_when_empty(): void
    {
        $this->postJson('/petugas/loket/1/panggil')->assertStatus(422);
    }

    public function test_call_next_on_inactive_counter_404(): void
    {
        Counter::where('counter_number', 2)->update(['is_active' => false]);
        $this->postJson('/queue/take-number');
        $this->postJson('/petugas/loket/2/panggil')->assertNotFound();
    }

    public function test_recall_broadcasts_same_number(): void
    {
        $this->postJson('/queue/take-number');
        $called = $this->postJson('/petugas/loket/1/panggil')->assertOk();

        $res = $this->postJson("/petugas/loket/1/recall/{$called->json('queue.id')}")->assertOk();

        $this->assertSame($called->json('queue.queue_number'), $res->json('queue.queue_number'));
        $this->assertSame(1, $res->json('queue.counter.counter_number'));
    }

    /**
     * Route recall punya dua parameter ({counterId}, {queue}) dan Laravel
     * mengisinya POSISIONAL. Kalau controller menerima satu argumen saja, ia
     * dapat counterId — bukan id antrian. Test recall yang lama memakai antrian
     * id 1 di loket 1, jadi kedua angka kebetulan sama dan bug-nya tersembunyi.
     */
    public function test_recall_uses_queue_id_not_counter_id(): void
    {
        $this->postJson('/queue/take-number');
        $this->postJson('/queue/take-number');
        $this->postJson('/queue/take-number');

        // Panggil dari loket 2 supaya antrian id 1 milik counter 2 — kolom
        // counterId (2) dan id antrian (1) jelas berbeda, salah-binding terdeteksi.
        $called = $this->postJson('/petugas/loket/2/panggil')->assertOk();
        $queueId = $called->json('queue.id');

        $this->assertNotSame(2, $queueId, 'Test butuh antrian id berbeda dari counter id.');

        $res = $this->postJson("/petugas/loket/2/recall/{$queueId}")->assertOk();

        $this->assertSame($queueId, $res->json('queue.id'));
        $this->assertSame($called->json('queue.queue_number'), $res->json('queue.queue_number'));
        $this->assertSame(2, $res->json('queue.counter.counter_number'));
    }

    public function test_recall_rejects_queue_of_another_counter(): void
    {
        $this->postJson('/queue/take-number');
        $called = $this->postJson('/petugas/loket/1/panggil')->assertOk();

        // Loket 2 mencoba memanggil ulang antrian milik loket 1.
        $this->postJson("/petugas/loket/2/recall/{$called->json('queue.id')}")
            ->assertForbidden();
    }

    public function test_status_requires_session_token(): void
    {
        $this->postJson('/queue/take-number');

        $this->getJson('/queue/status')
            ->assertOk()
            ->assertJsonPath('queue.status', Queue::STATUS_WAITING);
    }

    /**
     * Waktu dikirim ke layar petugas harus sudah "H:i" (24 jam), bukan ISO
     * mentah — kalau tidak, JS menampilkan 2026-09-27T05:09:00.000000Z.
     */
    public function test_called_at_dikirim_format_24_jam(): void
    {
        $this->postJson('/queue/take-number');
        $called = $this->postJson('/petugas/loket/1/panggil')->assertOk();

        $this->assertMatchesRegularExpression(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $called->json('queue.called_at'),
        );

        $this->assertSame(
            $called->json('queue.called_at'),
            $this->getJson('/queue/status')->json('queue.called_at'),
        );
    }

    public function test_tanggal_di_layar_bahasa_indonesia(): void
    {
        $this->get('/petugas/loket/1')
            ->assertOk()
            ->assertSee(now()->locale('id')->translatedFormat('l, j F Y'));
    }
}
