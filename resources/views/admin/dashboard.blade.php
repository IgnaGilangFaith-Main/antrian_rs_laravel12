<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — Antrian RS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css'])
</head>

<body class="bg-slate-100 text-slate-900 min-h-screen font-sans">
    <div class="max-w-4xl mx-auto p-6 space-y-6">

        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Administrasi</p>
                <h1 class="text-3xl font-black">Panel Admin</h1>
                <p class="text-sm text-slate-500">Tanggal operate:
                    {{ now()->locale('id')->translatedFormat('l, j F Y') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button
                    class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-sm font-semibold">Keluar</button>
            </form>
        </header>

        @if (session('success'))
            <p class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                {{ session('success') }}</p>
        @endif

        {{-- Angka hari ini --}}
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ([['Menunggu', $stats['waiting'], 'amber'], ['Dipanggil', $stats['called'], 'blue'], ['Selesai', $stats['done'], 'emerald'], ['Total', $stats['total'], 'slate']] as [$label, $value, $color])
                <div class="bg-white rounded-2xl shadow p-4">
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ $label }}</p>
                    <p class="text-4xl font-black text-{{ $color }}-600">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        {{-- Aksi harian --}}
        <section class="bg-white rounded-2xl shadow p-6 space-y-4">
            <h2 class="text-lg font-bold">Aksi cepat</h2>

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.counters.reseed') }}"
                    onsubmit="return confirm('Buat ulang 5 loket? Aman — data lama tidak dihapus.')">
                    @csrf
                    <button class="px-4 py-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-semibold">Buat
                        ulang 5 loket</button>
                </form>

                <form method="POST" action="{{ route('admin.queue.reset') }}"
                    onsubmit="return confirm('Kosongkan seluruh antrean hari ini? Riwayat hari lain tetap aman.')">
                    @csrf
                    <button class="px-4 py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold">Reset
                        antrean hari ini</button>
                </form>
            </div>
            <p class="text-xs text-slate-500">
                Reset antrean = nomor berikutnya kembali A-001 tanpa perlu <code>migrate:fresh --seed</code>.
            </p>
        </section>

        {{-- Daftar loket --}}
        <section class="bg-white rounded-2xl shadow p-6 space-y-3">
            <h2 class="text-lg font-bold">Loket</h2>

            @if ($counters->isEmpty())
                <p class="text-sm text-red-600">Belum ada loket. Klik “Buat ulang 5 loket”.</p>
            @endif

            <ul class="divide-y divide-slate-100">
                @foreach ($counters as $counter)
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-bold">Loket {{ $counter->counter_number }}</p>
                            <p class="text-xs text-slate-500">Kode {{ $counter->code }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span @class([
                                'text-xs font-semibold px-2 py-1 rounded-full',
                                'bg-emerald-100 text-emerald-700' => $counter->is_active,
                                'bg-slate-200 text-slate-600' => !$counter->is_active,
                            ])>{{ $counter->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            <form method="POST" action="{{ route('admin.counters.toggle', $counter) }}">
                                @csrf
                                <button
                                    class="px-3 py-1.5 text-xs rounded-lg bg-slate-100 hover:bg-slate-200 font-semibold">
                                    {{ $counter->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <a href="{{ route('counter.board', $counter) }}" target="_blank" rel="noopener"
                                class="px-3 py-1.5 text-xs rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold">Buka</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</body>

</html>
