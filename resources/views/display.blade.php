<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Display Antrian</title>
    @vite(['resources/css/app.css', 'resources/js/display.js'])
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col items-center justify-center gap-8 font-sans">
    <p class="text-2xl tracking-[0.4em] text-amber-400 uppercase">Pendaftaran Umum</p>
    <p class="absolute top-6 right-8 text-sm text-slate-500">
        {{ now()->format('H:i') }} &middot; {{ now()->locale('id')->translatedFormat('l, j F Y') }}
    </p>

    <div class="text-center space-y-4">
        <p class="text-6xl font-black text-amber-400" data-last-call>---</p>
        <p class="text-3xl text-slate-200">Silakan menuju ke <span class="font-bold text-white"
                data-now-serving>...</span></p>
    </div>

    <p class="hidden absolute bottom-24 text-4xl font-black text-red-500" data-reset-notice>ANTREAN DI-RESET</p>

    <p class="absolute bottom-6 text-sm text-slate-500">Petugas loket memanggil nomor secara manual &bull;
        bunyi bel otomatis sebagai penanda</p>
</body>

</html>
