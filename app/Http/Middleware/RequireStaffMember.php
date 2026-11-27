<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->session()->get('is_staff', 0) !== 1) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized: กรุณาเข้าสู่ระบบด้วยบัญชีเจ้าหน้าที่',
            ], 403);
        }

        return $next($request);
    }
}
