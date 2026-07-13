<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate([
            'nik' => '0000000000000001',
        ], [
            'nama' => 'Super Admin',
            'instansi' => 'System',
            'jabatan' => 'Administrator',
            'telp' => '081111111111',
            'password' => Hash::make(
                env('ADMIN_DEFAULT_PASSWORD', 'ChangeThisAdminPassword123!')
            ),

            'sts' => 'aktif',
            'approval' => 'approved',
            'must_change_password' => false,
            'tgldaftar' => now(),
        ]);

        $user->update([
            'role' => 'super_admin',
            'group_id' => null,
            'nama' => 'Super Admin',
            'instansi' => 'System',
            'jabatan' => 'Administrator',
            'sts' => 'aktif',
            'approval' => 'approved',
        ]);
    }
}
