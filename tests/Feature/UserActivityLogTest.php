<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_from_request_stores_device_context(): void
    {
        $user = $this->createUser();
        $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36';

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Login',
            'description' => 'User berhasil login ke sistem',
            ...ActivityLogContext::fromRequest($this->requestWithUserAgent(
                $userAgent,
                '192.168.1.15'
            )),
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'ip_address' => '192.168.1.15',
            'browser' => 'Google Chrome 147',
            'operating_system' => 'Linux',
            'device_type' => 'Desktop',
            'user_agent' => $userAgent,
        ]);
    }

    public function test_authorized_admin_can_read_activity_log_with_actor_context(): void
    {
        $admin = $this->createUser([
            'role' => 'admin',
        ]);

        $target = $this->createUser([
            'role' => 'user',
            'nik' => '3200000000000002',
            'telp' => '081234567802',
        ]);

        ActivityLog::create([
            'user_id' => $target->id,
            'actor_id' => $admin->id,
            'activity' => 'Update User',
            'description' => 'Akun diperbarui oleh admin',
            'ip_address' => null,
            'browser' => null,
            'operating_system' => null,
            'device_type' => null,
            'user_agent' => null,
        ]);

        $this->actingAs($admin, 'api')
            ->getJson("/api/users/{$target->id}/log")
            ->assertOk()
            ->assertJsonPath('data.activity_logs.0.activity', 'Update User')
            ->assertJsonPath('data.activity_logs.0.browser', null)
            ->assertJsonPath('data.activity_logs.0.actor.id', $admin->id)
            ->assertJsonPath('data.activity_logs.0.actor.nama', $admin->nama)
            ->assertJsonPath('data.activity_logs.0.actor.role', 'admin');
    }

    public function test_non_admin_cannot_read_internal_activity_log_endpoint(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'api')
            ->getJson("/api/users/{$user->id}/log")
            ->assertForbidden();
    }

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'role' => 'user',
            'nik' => '3200000000000001',
            'nama' => 'User Test',
            'instansi' => 'Instansi Test',
            'jabatan' => 'Jabatan Test',
            'telp' => '081234567801',
            'password' => Hash::make('Password123'),
            'sts' => 'aktif',
            'approval' => 'approved',
            'must_change_password' => false,
            'tgldaftar' => now(),
        ], $overrides));
    }

    private function requestWithUserAgent(
        ?string $userAgent,
        string $ipAddress = '127.0.0.1'
    ): Request {
        $server = [
            'REMOTE_ADDR' => $ipAddress,
        ];

        $server['HTTP_USER_AGENT'] = $userAgent ?? '';

        return Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            $server
        );
    }
}
