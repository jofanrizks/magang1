<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsappService;
use App\Support\ActivityLogContext;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function pendingUsers(Request $request)
    {
        $filters = $this->validatedListFilters($request);

        $users = $this->applyUserFilters(
                $this->visibleUsers()
                    ->where('approval', 'pending'),
                $filters
            )
            ->with('groups:id,name')
            ->select($this->userListFields())
            ->paginate($filters['per_page']);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function sendOtp(
        Request $request,
        $id,
        OtpService $otpService,
        WhatsappService $whatsappService
    ) {
        $user = User::findOrFail($id);
        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user)) {
            return $this->forbiddenResponse();
        }

        if ($user->approval !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'User sudah diproses sebelumnya'
            ], 422);
        }

        $otp = $otpService->generate(
            $user->id,
            'activation'
        );

        $sent = $whatsappService->send(
            $user->telp,
            "Kode aktivasi Anda: {$otp->plain_code}"
        );

        if (!$sent) {
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP gagal dikirim'
            ], 502);
        }

        DB::transaction(function () use ($user, $request, $actor) {
            $user->update([
                'approval' => 'approved',
                'sts' => 'pending',
                'tglapproval' => now(),
                'rejection_reason' => null,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => 'User Approved',
                'description' => "User disetujui oleh {$actor->role} dan OTP aktivasi dikirim",
                ...ActivityLogContext::fromRequest($request),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'User berhasil disetujui dan OTP aktivasi dikirim',
            'data' => $user->fresh()->load('groups:id,name'),
        ]);
    }

    public function rejectUser(Request $request, $id, WhatsappService $whatsappService)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user)) {
            return $this->forbiddenResponse();
        }

        if ($user->approval !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'User sudah diproses sebelumnya'
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ]);

        $reason = trim($validated['reason']);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($user, $actor, $request, $reason) {
            $user->update([
                'approval' => 'rejected',
                'sts' => 'disabled',
                'tgldisabled' => now(),
                'rejection_reason' => $reason,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => 'User Rejected',
                'description' => "Pengajuan ditolak oleh {$this->rejectionActorName($actor)}. Alasan: {$reason}",
                ...ActivityLogContext::fromRequest($request),
            ]);
        });

        $user = $user->fresh();

        $this->sendRejectionNotification($user, $actor, $reason, $whatsappService);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditolak.',
            'data' => [
                'id' => $user->id,
                'approval' => $user->approval,
                'sts' => $user->sts,
                'rejection_reason' => $user->rejection_reason,
            ]
        ]);
    }

    public function getAllUsers(Request $request)
    {
        $filters = $this->validatedListFilters($request);

        $users = $this->applyUserFilters(
                $this->visibleUsers(),
                $filters
            )
            ->with('groups:id,name')
            ->select($this->userListFields())
            ->paginate($filters['per_page']);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function getApprovedUsers(Request $request)
    {
        $filters = $this->validatedListFilters($request);

        $users = $this->applyUserFilters(
                $this->visibleUsers()
                    ->where('approval', 'approved'),
                $filters
            )
            ->with('groups:id,name')
            ->select($this->userListFields())
            ->paginate($filters['per_page']);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedUserData($request, true);
        $actor = auth()->user();

        if (!$this->canUseRole($actor, $data['role'])) {
            return $this->forbiddenResponse();
        }

        $this->requireGroupsForRole($data, $data['role']);

        $groupIds = $data['group_ids'] ?? null;
        unset($data['group_ids']);

        $data['password'] = Hash::make($data['password']);
        $data['must_change_password'] = true;
        $data['tgldaftar'] = now();
        $data['tglapproval'] = ($data['approval'] ?? null) === 'approved'
            ? now()
            : null;

        $user = DB::transaction(function () use ($data, $groupIds, $request, $actor) {
            $user = User::create($data);

            $this->syncUserGroups($user, $data['role'], $groupIds);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => 'Create User',
                'description' => "Akun {$user->role} dibuat oleh {$actor->role}",
                ...ActivityLogContext::fromRequest($request),
            ]);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => $user->load('groups:id,name')
        ], 201);
    }

    public function show($id)
    {
        $user = User::with('groups:id,name')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if (!$this->canViewTarget(auth()->user(), $user)) {
            return $this->forbiddenResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user)) {
            return $this->forbiddenResponse();
        }

        $data = $this->validatedUserData($request, false, $user);
        unset($data['password']);

        $targetRole = $data['role'] ?? $user->role;

        if (!$this->canUseRole($actor, $targetRole)) {
            return $this->forbiddenResponse();
        }

        $this->requireGroupsForRole($data, $targetRole);

        $groupIds = $data['group_ids'] ?? null;
        unset($data['group_ids']);

        $data['tglupdate'] = now();

        if (
            $user->approval === 'rejected'
            && isset($data['approval'])
            && in_array($data['approval'], ['pending', 'approved'], true)
        ) {
            $data['rejection_reason'] = null;
        }

        DB::transaction(function () use ($user, $data, $groupIds, $targetRole, $request, $actor) {
            $user->update($data);
            $this->syncUserGroups($user, $targetRole, $groupIds);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => 'Update User',
                'description' => "Akun diperbarui oleh {$actor->role}",
                ...ActivityLogContext::fromRequest($request),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui',
            'data' => $user->fresh()->load('groups:id,name')
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user)) {
            return $this->forbiddenResponse();
        }

        DB::transaction(function () use ($user, $request, $actor) {
            $user->update([
                'password' => Hash::make($request->password),
                'must_change_password' => true,
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => 'Admin Reset Password',
                'description' => "Password di-reset oleh {$actor->role}",
                ...ActivityLogContext::fromRequest($request),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Password user berhasil di-reset'
        ]);
    }

    public function disableUser(Request $request, $id, WhatsappService $whatsappService)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user) || $actor->id === $user->id) {
            return $this->forbiddenResponse();
        }

        DB::transaction(function () use ($user, $request, $actor) {
            $user->update([
                'sts' => 'disabled',
                'tgldisabled' => Carbon::now()
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => 'Account Disabled by Administrator',
                'description' => $this->disableDescription($actor),
                ...ActivityLogContext::fromRequest($request),
            ]);
        });

        $this->sendManualDisableNotification($user->fresh(), $actor, $whatsappService);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dinonaktifkan'
        ]);
    }

    public function enableUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user)) {
            return $this->forbiddenResponse();
        }

        DB::transaction(function () use ($user, $request, $actor) {
            $isRejected = $user->approval === 'rejected';

            $user->update([
                'approval' => $isRejected ? 'pending' : $user->approval,
                'sts' => $isRejected ? 'pending' : 'aktif',

                'rejection_reason' => $isRejected
                    ? null
                    : $user->rejection_reason,

                'tglapproval' => $isRejected
                    ? null
                    : $user->tglapproval,

                'login_attempt' => 0,
                'tgldisabled' => null,
                'tglupdate' => now(),
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'activity' => $isRejected
                    ? 'Rejected User Reopened'
                    : 'Account Enabled',
                'description' => $isRejected
                    ? "Pengajuan user dibuka kembali oleh {$actor->role}"
                    : "Akun diaktifkan kembali oleh {$actor->role}",
                ...ActivityLogContext::fromRequest($request),
            ]);
        });

        $user = $user->fresh()->load('groups:id,name');

        return response()->json([
            'success' => true,
            'message' => $user->approval === 'pending'
                ? 'Pengajuan user berhasil dibuka kembali dan menunggu persetujuan.'
                : 'User berhasil diaktifkan.',
            'data' => $user,
        ]);
    }
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $actor = auth()->user();

        if (!$this->canManageTarget($actor, $user) || $actor->id === $user->id) {
            return $this->forbiddenResponse();
        }

        try {
            DB::transaction(function () use ($user, $request, $actor) {
                ActivityLog::create([
                    'user_id' => $user->id,
                    'actor_id' => $actor->id,
                    'activity' => 'Delete User',
                    'description' => "Akun {$user->role} dihapus oleh {$actor->role}",
                    ...ActivityLogContext::fromRequest($request),
                ]);

                $user->delete();
            });
        } catch (QueryException $exception) {
            Log::warning('Failed to delete user.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'User tidak dapat dihapus karena masih memiliki relasi data.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus'
        ]);
    }

    public function dashboard()
    {
        $roles = auth()->user()->role === 'super_admin'
            ? ['admin', 'user', 'viewer']
            : ['user', 'viewer'];

        $totalUser = User::whereIn('role', $roles)->count();

        $aktif = User::whereIn('role', $roles)
            ->where('sts', 'aktif')
            ->count();

        $disabled = User::whereIn('role', $roles)
            ->where('sts', 'disabled')
            ->count();

        $pending = User::whereIn('role', $roles)
            ->where('approval', 'pending')
            ->count();

        $rejected = User::whereIn('role', $roles)
            ->where('approval', 'rejected')
            ->count();

        $recentUsers = User::whereIn('role', $roles)
            ->orderByDesc('tgldaftar')
            ->take(5)
            ->get([
                'id',
                'role',
                'nik',
                'nama',
                'instansi',
                'jabatan',
                'telp',
                'sts',
                'approval',
                'tgldaftar'
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_user' => $totalUser,
                    'aktif' => $aktif,
                    'disabled' => $disabled,
                    'pending' => $pending,
                    'rejected' => $rejected,
                ],
                'recent_users' => $recentUsers,
            ]
        ]);
    }

    public function logUser($id)
    {
        $user = User::with([
            'activityLogs' => fn($q) => $q->latest(),
            'activityLogs.actor:id,nama,role',
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if (!$this->canViewTarget(auth()->user(), $user)) {
            return $this->forbiddenResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    private function validatedUserData(Request $request, bool $isCreate, ?User $user = null): array
    {
        $uniqueNik = Rule::unique('users', 'nik');
        $uniqueTelp = Rule::unique('users', 'telp');

        if ($user) {
            $uniqueNik->ignore($user->id);
            $uniqueTelp->ignore($user->id);
        }

        return $request->validate([
            'role' => [$isCreate ? 'required' : 'sometimes', Rule::in(['admin', 'user', 'viewer'])],
            'group_ids' => ['nullable', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'distinct', 'exists:groups,id'],
            'nik' => [$isCreate ? 'required' : 'sometimes', $uniqueNik],
            'nama' => $isCreate ? 'required' : 'sometimes',
            'instansi' => $isCreate ? 'required' : 'sometimes',
            'jabatan' => $isCreate ? 'required' : 'sometimes',
            'telp' => [$isCreate ? 'required' : 'sometimes', $uniqueTelp],
            'sts' => [$isCreate ? 'required' : 'sometimes', Rule::in(['pending', 'aktif', 'disabled'])],
            'approval' => [$isCreate ? 'required' : 'sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'password' => $isCreate ? 'required|min:6|confirmed' : 'sometimes|min:6|confirmed',
        ]);
    }

    private function syncUserGroups(User $user, string $role, ?array $groupIds): void
    {
        if ($role === 'user') {
            $user->groups()->sync($groupIds ?? []);
            return;
        }

        $user->groups()->detach();
    }

    private function requireGroupsForRole(array $data, string $role): void
    {
        if ($role !== 'user' || array_key_exists('group_ids', $data)) {
            return;
        }

        throw ValidationException::withMessages([
            'group_ids' => 'Group wajib dipilih untuk role user.',
        ]);
    }

    private function visibleUsers()
    {
        $query = User::query();

        if (auth()->user()->role === 'super_admin') {
            $query->whereIn('role', ['admin', 'user', 'viewer']);
        }

        if (auth()->user()->role === 'admin') {
            $query->whereIn('role', ['user', 'viewer']);
        }

        return $query;
    }

    private function validatedListFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => [
                'nullable',
                Rule::in(['super_admin', 'admin', 'user', 'viewer']),
            ],
            'group_id' => [
                'nullable',
                'integer',
                'exists:groups,id',
            ],
            'sts' => [
                'nullable',
                Rule::in(['pending', 'aktif', 'disabled']),
            ],
            'approval' => [
                'nullable',
                Rule::in(['pending', 'approved', 'rejected']),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $validated['per_page'] = (int) ($validated['per_page'] ?? 25);

        return $validated;
    }

    private function applyUserFilters($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $search = addcslashes($filters['search'], '\%_');

            $query->where(function ($query) use ($search) {
                $keyword = "%{$search}%";

                $query->where('nama', 'like', $keyword)
                    ->orWhere('nik', 'like', $keyword)
                    ->orWhere('telp', 'like', $keyword)
                    ->orWhere('instansi', 'like', $keyword)
                    ->orWhere('jabatan', 'like', $keyword);
            });
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (!empty($filters['group_id'])) {
            $query->whereHas('groups', function ($query) use ($filters) {
                $query->where('groups.id', $filters['group_id']);
            });
        }

        if (!empty($filters['sts'])) {
            $query->where('sts', $filters['sts']);
        }

        if (!empty($filters['approval'])) {
            $query->where('approval', $filters['approval']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('tgldaftar', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('tgldaftar', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('tgldaftar');
    }

    private function canViewTarget(User $actor, User $target): bool
    {
        if ($actor->role === 'super_admin') {
            return true;
        }

        return $actor->role === 'admin'
            && in_array($target->role, ['user', 'viewer'], true);
    }

    private function canManageTarget(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $this->canUseRole($actor, $target->role);
    }

    private function canUseRole(User $actor, string $role): bool
    {
        if ($actor->role === 'super_admin') {
            return in_array($role, ['admin', 'user', 'viewer'], true);
        }

        if ($actor->role === 'admin') {
            return in_array($role, ['user', 'viewer'], true);
        }

        return false;
    }

    private function disableDescription(User $actor): string
    {
        if ($actor->role === 'super_admin') {
            return 'Akun dinonaktifkan oleh Super Admin';
        }

        return "Akun dinonaktifkan oleh Admin {$actor->nama}";
    }

    private function rejectionActorName(User $actor): string
    {
        if ($actor->role === 'super_admin') {
            return 'Super Admin';
        }

        return "Admin {$actor->nama}";
    }

    private function sendRejectionNotification(
        User $user,
        User $actor,
        string $reason,
        WhatsappService $whatsappService
    ): void {
        $actorName = $actor->role === 'super_admin'
            ? 'Super Admin'
            : $actor->nama;

        $waktuWib = now()
            ->timezone('Asia/Jakarta')
            ->format('d-m-Y H:i');

        $message = "Halo, {$user->nama}.\n\n"
            . "Pengajuan akun Anda telah ditolak.\n\n"
            . "Alasan penolakan:\n{$reason}\n\n"
            . "Tanggal: {$waktuWib} WIB\n"
            . "Diproses oleh: {$actorName}\n\n"
            . 'Silakan perbaiki data atau hubungi administrator apabila membutuhkan informasi lebih lanjut.';

        try {
            $sent = $whatsappService->send($user->telp, $message);

            if (!$sent) {
                Log::warning('Failed to send rejection WhatsApp notification.', [
                    'user_id' => $user->id,
                    'actor_id' => $actor->id,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to send rejection WhatsApp notification.', [
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

  private function sendManualDisableNotification(
        User $user,
        User $actor,
        WhatsappService $whatsappService
    ): void {
        $actorName = $actor->role === 'super_admin'
            ? 'Super Admin'
            : $actor->nama;

        $waktuWib = now()
            ->timezone('Asia/Jakarta')
            ->format('d-m-Y H:i');

        $message = "Halo, {$user->nama}.\n\n"
            . "Akun Anda telah dinonaktifkan oleh administrator.\n\n"
            . "Tanggal: {$waktuWib} WIB\n"
            . "Dilakukan oleh: {$actorName}\n\n"
            . 'Silakan hubungi administrator apabila Anda membutuhkan informasi lebih lanjut.';

        try {
            $sent = $whatsappService->send(
                $user->telp,
                $message
            );

            if (!$sent) {
                Log::warning(
                    'Failed to send manual-disable WhatsApp notification.',
                    [
                        'user_id' => $user->id,
                        'actor_id' => $actor->id,
                    ]
                );
            }
        } catch (\Throwable $exception) {
            Log::warning(
                'Failed to send manual-disable WhatsApp notification.',
                [
                    'user_id' => $user->id,
                    'actor_id' => $actor->id,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }

    private function forbiddenResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.'
        ], 403);
    }

    private function userListFields(): array
    {
        return [
            'id',
            'role',
            'nik',
            'nama',
            'instansi',
            'jabatan',
            'telp',
            'sts',
            'approval',
            'rejection_reason',
            'login_attempt',
            'must_change_password',
            'tgldaftar',
            'tglapproval',
            'tgldisabled',
        ];
    }
}
