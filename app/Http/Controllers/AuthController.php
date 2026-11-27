<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\DjangoPasswordVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly DjangoPasswordVerifier $passwordVerifier,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            return response()->json([
                'success' => false,
                'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
            ]);
        }

        $member = Member::query()->where('username', $username)->first();
        if (! $member || ! $this->passwordVerifier->verify($password, (string) $member->password)) {
            return response()->json([
                'success' => false,
                'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'member_id' => $member->id,
            'is_staff' => (int) $member->is_staff,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
        ]);

        return response()->json([
            'success' => true,
            'first_name' => $member->first_name,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }
}
