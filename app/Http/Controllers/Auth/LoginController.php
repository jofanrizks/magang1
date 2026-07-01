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
        $credentials = $request->only('nik','password');

        if(!$token = auth()->attempt($credentials))
        {
            return response()->json([
                'success'=>false,
                'message'=>'NIK atau password salah'
            ],401);
        }

        $user = auth()->user();

        if($user->approval != 'approved')
        {
            return response()->json([
                'success'=>false,
                'message'=>'Menunggu approval admin'
            ],403);
        }

        if($user->sts != 'aktif')
        {
            return response()->json([
                'success'=>false,
                'message'=>'Akun tidak aktif'
            ],403);
        }
        if ($user->sts === 'disabled') 
        {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang dinonaktifkan. Silakan aktifkan kembali akun Anda.'
            ], 403);
        }

        return response()->json([
            'success'=>true,
            'token'=>$token,
            'user'=>$user
        ]);
    }

    public function logout(Request $request)
    {
        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }



    public function me()
    {
        return response()->json(auth()->user());
    }
}