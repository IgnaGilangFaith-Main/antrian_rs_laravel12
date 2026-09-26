<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loket {{ $counter->counter_number }} — Petugas</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css'])
</head>

<body class="bg-slate-100 text-slate-900 min-h-screen flex items-center justify-center font-sans">
    <main class="w-full max-w-md p-6 space-y-6">

        <header class="text-center">
            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">Petugas Pendaftaran</p>
            <h1 class="text-3xl font-black">LOKET {{ $counter->counter_number }}</h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ now()->format('H:i') }} &middot; {{ now()->locale('id')->translatedFormat('l, j F Y') }}
            </p>
        </header>

        {{-- Nomor yang sedang dilayani loket ini --}}
        <section class="bg-white rounded-2xl shadow p-6 text-center">
            <p class="text-sm text-slate-500">Sedang dilayani</p>
            <p id="current-label" class="text-6xl font-black text-slate-300" data-queue-id="{{ $current?->id }}">
                {{ $current->queue_label ?? '—' }}
            </p>
            <p id="current-time" @class(['text-xs text-slate-400', 'hidden' => !$current])>dipanggil {{ $current?->called_at?->format('H:i') }}</p>
            <button id="recall" @class([
                'mt-4 w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-semibold transition',
                'hidden' => !$current,
            ])>
                Panggil Ulang
            </button>
        </section>

        {{-- Tombol utama: panggil nomor berikutnya (FIFO) --}}
        <button id="call"
            class="w-full py-6 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white text-2xl font-black shadow-lg transition">
            PANGGIL NEXT
        </button>

        <p class="text-center text-sm text-slate-500">
            Menunggu: <span id="waiting" class="font-bold text-slate-700">{{ $waiting }}</span> pasien
        </p>

        <p id="msg" class="text-center text-sm"></p>

    </main>

    <script>
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const el = (id) => document.getElementById(id);
        const base = location.pathname.replace(/\/$/, '');

        // ID antrian yang sedang dilayani, disimpan di dataset agar recall tak
        // perlu tebak dari label.
        function setCurrent(id, label, time) {
            el('current-label').textContent = label;
            el('current-label').dataset.queueId = id;
            el('current-label').className = 'text-6xl font-black text-emerald-600';
            el('current-time').textContent = time ? `dipanggil ${time}` : '';
            el('current-time').classList.remove('hidden');
            el('recall').classList.remove('hidden');
        }

        async function post(path) {
            const res = await fetch(base + path, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            return {
                ok: res.ok,
                data: await res.json()
            };
        }

        function say(text, ok = true) {
            el('msg').textContent = text;
            el('msg').className =
                'text-center text-sm font-semibold ' + (ok ? 'text-emerald-600' : 'text-red-600');
        }

        el('call').addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            btn.disabled = true;
            btn.textContent = 'Memanggil...';

            const {
                ok,
                data
            } = await post('/panggil');

            if (ok) {
                setCurrent(data.queue.id, data.queue.queue_label, data.queue.called_at);
                say(`Dipanggil ke Loket ${data.counter.counter_number}.`);
            } else {
                say(data.message ?? 'Gagal memanggil.');
            }

            btn.disabled = false;
            btn.textContent = 'PANGGIL NEXT';
            refreshWaiting();
        });

        el('recall').addEventListener('click', async () => {
            const id = el('current-label').dataset.queueId;
            if (!id) return;

            const {
                ok,
                data
            } = await post(`/recall/${id}`);
            say(ok ? `Memanggil ulang ${data.queue.queue_label}.` : 'Gagal memanggil ulang.');
        });

        async function refreshWaiting() {
            const res = await fetch('/queue/waiting-count', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
            });
            if (res.ok) el('waiting').textContent = (await res.json()).waiting;
        }
        setInterval(refreshWaiting, 10000);
    </script>
</body>

</html>
