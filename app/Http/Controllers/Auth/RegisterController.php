<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nik' => 'required|unique:users,nik',
            'nama' => 'required',
            'instansi' => 'required',
            'jabatan' => 'required',
            'telp' => 'required|unique:users,telp',
            'password' => 'required|min:6|confirmed',
        ]);
        $user = new User();

            $user->role = 'user';
            $user->nik = $request->nik;
            $user->nama = $request->nama;
            $user->instansi = $request->instansi;
            $user->jabatan = $request->jabatan;
            $user->telp = $request->telp;
            $user->password = Hash::make($request->password);
            $user->sts = 'pending';
            $user->approval = 'pending';
            $user->tgldaftar = now();

            $user->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Register',
                'description' => 'Registrasi akun'
            ]);
        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil, menunggu approval admin',
            'data' => $user
        ], 201);
    }
    
}