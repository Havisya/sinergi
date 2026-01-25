<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        // Jika belum login
        if (!$user) {
            return response()->json(['message' => 'Unauthorized. Silakan login terlebih dahulu.'], 401);
        }

        // Jika role user tidak termasuk yang diizinkan
        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Akses ditolak. Role Anda tidak memiliki izin.'], 403);
        }

        return $next($request);
    }
}
