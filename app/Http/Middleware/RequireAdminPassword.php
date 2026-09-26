<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang sederhana untuk /admin: satu password dari .env (ADMIN_PASSWORD),
 * disimpan sebagai flag di session. Tanpa tabel user/role — klinik cukup 1 operator.
 *
 * Kalau butuh banyak akun, ganti ke auth Laravel (User + migration), bukan ini.
 */
class RequireAdminPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('is_admin')) {
            return $next($request);
        }

        // 403 "Unauthorized" membingungkan untuk operator klinik yang bukan devs.
        // Redirect ke form login lebih jelas, dan intended() mengembalikan ke
        // halaman yang tadi dicoba.
        return redirect()->guest(route('admin.login'));
    }
}
