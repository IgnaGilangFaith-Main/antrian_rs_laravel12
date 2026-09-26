<?php

namespace App\Http\Controllers;

use App\Events\QueueCalledEvent;
use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QueueController extends Controller
{
    private const PREFIX = 'A';

    /** Pasien (guest): ambil nomor antrian hari ini, token disimpan di session. */
    public function takeNumber(Request $request): JsonResponse
    {
        $date = today()->toDateString();
        $number = null;

        // retry menutup celah antara "baris terakhir belum terlihat" dan commit konkuren.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $number = DB::transaction(function () use ($date) {
                    $last = Queue::where('queue_date', $date)
                        ->orderByDesc('queue_number')
                        ->lockForUpdate()
                        ->first();

                    $next = $last ? $last->queue_number + 1 : 1;

                    return Queue::create([
                        'public_token' => (string) Str::uuid(),
                        'queue_date' => $date,
                        'queue_number' => $next,
                        'queue_label' => self::PREFIX.'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT),
                        'status' => Queue::STATUS_WAITING,
                    ]);
                });

                break;
            } catch (QueryException $e) {
                if ($attempt === 3 || ! $this->isDuplicate($e)) {
                    throw $e;
                }
            }
        }

        $request->session()->put('queue_token', $number->public_token);

        return response()->json([
            'ok' => true,
            'queue_label' => $number->queue_label,
            'queue_number' => $number->queue_number,
            'token' => $number->public_token,
        ], 201);
    }

    /**
     * Layar petugas loket: tombol panggil / panggil ulang.
     * Antrian dipanggil manual per loket — tak ada auto-dispatch.
     */
    public function board(int $counterId): View
    {
        $counter = Counter::where('is_active', true)->findOrFail($counterId);

        $current = Queue::with('counter')
            ->where('counter_id', $counter->id)
            ->where('status', Queue::STATUS_CALLED)
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->first();

        return view('queue.counter', [
            'counter' => $counter,
            'current' => $current,
            'waiting' => Queue::where('status', Queue::STATUS_WAITING)
                ->where('queue_date', today()->toDateString())
                ->count(),
        ]);
    }

    /** Petugas loket: panggil satu nomor terdepan (FIFO) untuk loket $counterId.
     * lockForUpdate() => dua loket tidak mungkin mendapat nomor sama walau tombol
     * ditekan bersamaan: tx kedua memblokir di baris pertama, lalu membaca ulang
     * setelah commit tx pertama dan otomatis dapat nomor berikutnya.
     * ponytail: pakai lock('for update skip locked') (MySQL 8 / PG) kalau latensi
     * antrean jadi masalah; skip baris terkunci alih-alih menunggu.
     */
    public function callNext(int $counterId): JsonResponse
    {
        $counter = Counter::where('is_active', true)->findOrFail($counterId);

        $queue = DB::transaction(function () use ($counter) {
            $next = Queue::query()
                ->where('status', Queue::STATUS_WAITING)
                ->where('queue_date', today()->toDateString())
                ->orderBy('id')              // FIFO
                ->lockForUpdate()
                ->first();

            abort_if($next === null, 422, 'Tidak ada antrean menunggu.');

            $next->update([
                'status' => Queue::STATUS_CALLED,
                'counter_id' => $counter->id,
                'called_at' => now(),
            ]);

            return $next;
        });

        $this->announce($queue, $counter->counter_number);

        return response()->json([
            'ok' => true,
            'queue' => $this->payload($queue),
            'counter' => $counter->only(['id', 'counter_number']),
        ]);
    }

    /**
     * Petugas loket: panggil ulang nomor yang sama, pasien belum merespons.
     *
     * $counterId wajib diterima karena route punya dua parameter
     * ({counterId}, {queue}) dan Laravel mengisinya POSISIONAL — kalau argumen
     * pertama dinamai $queueId, ia menerima counterId, bukan id antrian.
     */
    public function recall(int $counterId, int $queueId): JsonResponse
    {
        $queue = Queue::with('counter')->findOrFail($queueId);

        abort_unless($queue->counter_id, 422, 'Antrian ini belum pernah dipanggil.');

        abort_unless(
            $queue->counter_id === $counterId,
            403,
            'Antrian ini milik loket lain.'
        );

        $queue->forceFill(['called_at' => now()])->save();

        $this->announce($queue, $queue->counter->counter_number);

        return response()->json(['ok' => true, 'queue' => $this->payload($queue->fresh('counter'))]);
    }

    /** Petugas loket: jumlah antrean menunggu hari ini (untuk refresh tombol). */
    public function waitingCount(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'waiting' => Queue::where('status', Queue::STATUS_WAITING)
                ->where('queue_date', today()->toDateString())
                ->count(),
        ]);
    }

    /**
     * Bentuk JSON untuk layar petugas: waktu sudah "05:06" (24 jam), bukan
     * ISO mentah — JS tak perlu parse Date dan tak bisa salah zona.
     */
    private function payload(Queue $queue): array
    {
        return $queue->only(['id', 'queue_number', 'queue_label', 'status']) + [
            'called_at' => $queue->called_at?->format('H:i'),
            'counter' => $queue->counter?->only(['id', 'counter_number']),
        ];
    }

    private function announce(Queue $queue, int $counterNumber): void
    {
        QueueCalledEvent::dispatch(
            queueNumber: $queue->queue_number,
            queueLabel: $queue->queue_label,
            counterNumber: $counterNumber,
            status: $queue->status,
        );
    }

    /**
     * Pasien: status antrian dari session (cek via polling, mis. tiap 5 detik).
     * Token di cookie/session = otorisasi, tanpa login.
     */
    public function status(Request $request): JsonResponse
    {
        $token = $request->session()->get('queue_token');

        $queue = $token
            ? Queue::with('counter')->where('public_token', $token)->first()
            : null;

        return response()->json([
            'ok' => true,
            'queue' => $queue?->only(['queue_label', 'status'])
                + ['called_at' => $queue?->called_at?->format('H:i')],
            'counter_number' => $queue?->counter?->counter_number,
        ]);
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505', '23503'], true)
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
