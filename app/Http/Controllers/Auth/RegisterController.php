<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nik' => ['required', 'unique:users,nik'],
            'nama' => ['required'],
            'instansi' => ['required'],
            'jabatan' => ['required'],
            'telp' => ['required', 'unique:users,telp'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'distinct', 'exists:groups,id'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $groupIds = $validated['group_ids'];
        unset($validated['group_ids']);

        $user = DB::transaction(function () use ($validated, $groupIds, $request) {
            $user = User::create([
                'role' => 'user',
                'nik' => $validated['nik'],
                'nama' => $validated['nama'],
                'instansi' => $validated['instansi'],
                'jabatan' => $validated['jabatan'],
                'telp' => $validated['telp'],
                'password' => Hash::make($validated['password']),
                'sts' => 'pending',
                'approval' => 'pending',
                'tgldaftar' => now(),
            ]);

            $user->groups()->sync($groupIds);

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Register',
                'description' => 'Registrasi akun',
                'ip_address' => $request->ip(),
            ]);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil, menunggu approval admin',
            'data' => $user->load('groups:id,name')
        ], 201);
    }
    
}
