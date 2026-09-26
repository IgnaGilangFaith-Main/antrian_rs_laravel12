<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ambil Antrian</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-50 text-slate-900 min-h-screen flex items-center justify-center font-sans">
    <main class="text-center space-y-6 p-8">
        <h1 class="text-2xl font-bold">Pendaftaran Umum</h1>

        <div id="result" class="hidden">
            <p class="text-sm text-slate-500">Nomor Antrian Anda</p>
            <p id="label" class="text-7xl font-black text-blue-600"></p>
        </div>

        <button id="take"
            class="px-8 py-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xl font-semibold shadow-lg transition">
            Ambil Antrian
        </button>

        <p id="status" class="text-sm text-slate-500"></p>
    </main>

    <script>
        const take = document.getElementById('take');
        const result = document.getElementById('result');
        const label = document.getElementById('label');
        const status = document.getElementById('status');

        take.addEventListener('click', async () => {
            take.disabled = true;
            try {
                const res = await fetch('/queue/take-number', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                const data = await res.json();
                label.textContent = data.queue_label;
                result.classList.remove('hidden');
                take.textContent = 'Ambil Lagi';
            } catch (e) {
                status.textContent = 'Gagal. Coba lagi.';
            } finally {
                take.disabled = false;
            }
        });

        // Polling status: muncul saat loket memanggil nomor ini.
        setInterval(async () => {
            const res = await fetch('/queue/status', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.queue?.status === 'CALLED') {
                status.textContent = `Dipanggil! Ke Loket ${data.counter_number}`;
            }
        }, 5000);
    </script>
</body>

</html>
