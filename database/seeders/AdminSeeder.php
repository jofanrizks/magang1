<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'role' => 'admin',
            'nik' => '0000000000000001',
            'nama' => 'Super Admin',
            'instansi' => 'System',
            'jabatan' => 'Administrator',
            'telp' => '081111111111',
            'password' => Hash::make('admin123'),

            'sts' => 'aktif',
            'approval' => 'approved',
            'tgldaftar' => now(),
        ]);
    }
}