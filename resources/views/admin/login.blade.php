<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin — Antrian RS</title>
    @vite(['resources/css/app.css'])
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center font-sans">
    <main class="w-full max-w-sm p-6 space-y-6">
        <header class="text-center">
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Administrasi</p>
            <h1 class="text-3xl font-black">ANTRIAN RS</h1>
        </header>

        <form method="POST" action="{{ route('admin.login') }}"
            class="bg-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
            @csrf

            @if (!config('admin.password'))
                <p class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
                    <strong>ADMIN_PASSWORD belum diisi.</strong><br>
                    Isi dulu di <code>.env</code> lalu <code>php artisan config:clear</code>.
                </p>
            @endif

            <div>
                <label for="password" class="block text-sm text-slate-400 mb-1">Password admin</label>
                <input id="password" name="password" type="password" required autofocus
                    class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-slate-700 focus:border-blue-500 focus:outline-none">
            </div>

            @error('error')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror
            @if (session('error'))
                <p class="text-sm text-red-400">{{ session('error') }}</p>
            @endif

            <button class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-700 font-bold transition">Masuk</button>
        </form>
    </main>
</body>

</html>
