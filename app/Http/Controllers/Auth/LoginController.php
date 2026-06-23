<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nik' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('nik', $request->nik)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah'
            ], 401);
        }

        if ($user->approval !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Akun belum di-approve admin'
            ], 403);
        }

        if ($user->sts !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
    }
    public function testing (Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Testing berhasil',
            'data' => $request->all()
        ], 200);
    }
}