<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'role' => [
                'required',
                Rule::in(['user', 'viewer']),
            ],

            'nik' => [
                'required',
                'digits:16',
                'unique:users,nik',
            ],

            'nama' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'instansi' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'jabatan' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            'telp' => [
                'required',
                'string',
                'regex:/^(08|62)[0-9]{8,13}$/',
                'unique:users,telp',
            ],

            'group_ids' => [
                'exclude_unless:role,user',
                'required',
                'array',
                'min:1',
            ],

            'group_ids.*' => [
                'integer',
                'distinct',
                'exists:groups,id',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
        ], [
            'role.required' => 'Jenis akun wajib dipilih.',
            'role.in' => 'Jenis akun hanya boleh User atau Viewer.',

            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari tepat 16 digit.',
            'nik.unique' => 'NIK sudah terdaftar.',

            'nama.required' => 'Nama wajib diisi.',
            'nama.min' => 'Nama minimal 3 karakter.',

            'instansi.required' => 'Instansi wajib diisi.',
            'instansi.min' => 'Instansi minimal 3 karakter.',

            'jabatan.required' => 'Jabatan wajib diisi.',
            'jabatan.min' => 'Jabatan minimal 2 karakter.',

            'telp.required' => 'Nomor HP wajib diisi.',
            'telp.regex' => 'Nomor HP harus diawali 08 atau 62.',
            'telp.unique' => 'Nomor HP sudah terdaftar.',

            'group_ids.required' =>
                'Group wajib dipilih untuk akun User.',

            'group_ids.array' =>
                'Format group tidak valid.',

            'group_ids.min' =>
                'Pilih minimal satu group untuk akun User.',

            'group_ids.*.exists' =>
                'Group yang dipilih tidak ditemukan.',

            'group_ids.*.distinct' =>
                'Group tidak boleh dipilih lebih dari satu kali.',

            'password.required' => 'Password wajib diisi.',
            'password.confirmed' =>
                'Konfirmasi password tidak sama.',
        ]);

        $groupIds = $validated['group_ids'] ?? [];
        unset($validated['group_ids']);

        $user = DB::transaction(function () use (
            $validated,
            $groupIds,
            $request
        ) {
            $user = User::create([
                'role' => $validated['role'],
                'nik' => $validated['nik'],
                'nama' => trim($validated['nama']),
                'instansi' => trim($validated['instansi']),
                'jabatan' => trim($validated['jabatan']),
                'telp' => $validated['telp'],
                'password' => Hash::make(
                    $validated['password']
                ),
                'sts' => 'pending',
                'approval' => 'pending',
                'tgldaftar' => now(),
            ]);

            if ($user->role === 'user') {
                $user->groups()->sync($groupIds);
            }

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => null,
                'activity' => 'Register',
                'description' => $user->role === 'viewer'
                    ? 'Registrasi akun Viewer'
                    : 'Registrasi akun User',
                ...ActivityLogContext::fromRequest($request),
            ]);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' =>
                'Registrasi berhasil, menunggu approval admin',
            'data' => $user->load('groups:id,name'),
        ], 201);
    }
}
