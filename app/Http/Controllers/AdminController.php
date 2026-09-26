<?php

namespace App\Http\Controllers;

use App\Events\QueueResetEvent;
use App\Models\Counter;
use App\Models\Queue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (request()->session()->get('is_admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /** POST /admin/login — route publik, tapi sudah di-throttle 5x/menit. */
    public function authenticate(Request $request): RedirectResponse
    {
        $expected = (string) config('admin.password');

        // hash_equals: waktu tetap, tidak bocor lewat timing.
        if ($expected === '' || ! hash_equals($expected, (string) $request->input('password'))) {
            return back()->with('error', 'Password salah.');
        }

        // Regenerate ID session: mencegah session fixation.
        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('is_admin');

        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        $date = today()->toDateString();

        $counters = Counter::orderBy('counter_number')->get();
        $stats = [
            'waiting' => Queue::where('queue_date', $date)->where('status', Queue::STATUS_WAITING)->count(),
            'called' => Queue::where('queue_date', $date)->where('status', Queue::STATUS_CALLED)->count(),
            'done' => Queue::where('queue_date', $date)->where('status', Queue::STATUS_DONE)->count(),
            'total' => Queue::where('queue_date', $date)->count(),
        ];

        return view('admin.dashboard', compact('counters', 'stats'));
    }

    /** Isi ulang 5 loket (dipakai setelah migrate:fresh). */
    public function reseedCounters(): RedirectResponse
    {
        foreach (range(1, 5) as $n) {
            Counter::updateOrCreate(
                ['counter_number' => $n],
                ['code' => (string) $n, 'is_active' => true],
            );
        }

        return back()->with('success', '5 loket dibuat ulang.');
    }

    /** Nonaktifkan loket yang rusak/rame — cukup ubah status, tak perlu hapus. */
    public function toggleCounter(Counter $counter): RedirectResponse
    {
        $counter->update(['is_active' => ! $counter->is_active]);

        return back()->with('success', "Loket {$counter->counter_number} ".($counter->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Kosongkan antrean hari ini sehingga nomor kembali A-001 tanpa migrate:fresh.
     * Yang dihapus hanya baris hari ini — riwayat kemarin tetap utuh.
     */
    public function resetQueue(): RedirectResponse
    {
        $date = today()->toDateString();

        $deleted = DB::transaction(function () use ($date) {
            $today = Queue::where('queue_date', $date);
            $count = (clone $today)->count();

            // lockForUpdate: admin buka 2 tab lalu klik 2x -> tidak ada dobel delete.
            (clone $today)->lockForUpdate()->delete();

            return $count;
        });

        QueueResetEvent::dispatch($date, $deleted);

        return back()->with(
            'success',
            'Antrean '.today()->locale('id')->translatedFormat('l, j F Y')." dikosongkan ({$deleted} baris). Nomor berikutnya kembali A-001."
        );
    }
}
