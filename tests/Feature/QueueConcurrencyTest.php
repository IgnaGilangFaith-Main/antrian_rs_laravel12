<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bukti anti-race: 5 proses PHP paralel menjalankan logika callNext() yang sama
 * persis bersamaan. Butuh MySQL/PostgreSQL nyata — sqlite in-memory single-connection
 * tak bisa menguji row locking, jadi test di-skip di situ.
 *
 * Jalankan (DB terpisah, migrate:fresh di awal):
 *   DB_CONNECTION=mysql DB_DATABASE=antrian_rs_race_test php artisan test --filter=QueueConcurrencyTest
 */
class QueueConcurrencyTest extends TestCase
{
    // Tanpa RefreshDatabase: seed harus ter-commit supaya proses child (fork) bisa melihatnya.
    public function test_five_parallel_processes_get_unique_queues(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Butuh MySQL/PostgreSQL + ext-pcntl untuk uji row locking paralel.');
        }

        $this->artisan('migrate:fresh')->assertSuccessful();

        foreach (range(1, 5) as $n) {
            Counter::updateOrCreate(['counter_number' => $n], ['code' => (string) $n, 'is_active' => true]);
        }

        $counters = Counter::orderBy('counter_number')->take(5)->pluck('id', 'counter_number');
        $date = today()->toDateString();

        foreach (range(1, 5) as $i) {
            Queue::create([
                'public_token' => (string) Str::uuid(),
                'queue_date' => $date,
                'queue_number' => $i,
                'queue_label' => 'A-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'status' => Queue::STATUS_WAITING,
            ]);
        }

        // --- logika diuji: identik dengan QueueController::callNext() ---
        $claim = function (int $counterId, string $date): string {
            return DB::transaction(function () use ($counterId, $date): string {
                $next = Queue::query()
                    ->where('status', Queue::STATUS_WAITING)
                    ->where('queue_date', $date)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $next) {
                    return 'EMPTY';
                }

                $next->update([
                    'status' => Queue::STATUS_CALLED,
                    'counter_id' => $counterId,
                    'called_at' => now(),
                ]);

                return $next->queue_label;
            });
        };

        $pids = [];
        foreach ($counters as $number => $counterId) {
            $file = sys_get_temp_dir()."/queue-race-{$number}.txt";

            if (($pid = pcntl_fork()) === 0) {
                DB::disconnect();
                try {
                    file_put_contents($file, $claim($counterId, $date));
                } catch (\Throwable $e) {
                    file_put_contents($file, 'ERR:'.substr($e->getMessage(), 0, 120));
                }
                exit(0);
            }

            $pids[$number] = [$pid, $file];
        }

        foreach ($pids as [$pid]) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach ($pids as $number => [$pid, $file]) {
            $results[$number] = trim((string) (@file_get_contents($file) ?: 'MISSING'));
            @unlink($file);
        }

        $labels = array_values(array_filter($results, fn ($r) => str_starts_with($r, 'A-')));

        $this->assertCount(5, $labels, 'Setiap loket harus dapat satu nomor. Hasil: '.json_encode($results));
        $this->assertCount(5, array_unique($labels), 'Tidak boleh ada nomor dobel. Hasil: '.json_encode($results));
        $this->assertSame(0, Queue::where('status', Queue::STATUS_WAITING)->count(), 'Sisa antrian harus 0.');
    }
}
