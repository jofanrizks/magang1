<?php

namespace App\Http\Controllers;

use App\Models\ManagedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ManagedService::query()
            ->fixed()
            ->select([
                'id',
                'group_id',
                'code',
                'name',
                'description',
                'is_active',
                'sort_order',
            ])
            ->with([
                'group:id,name',
                'options' => function ($query) use ($user) {
                    $query->select([
                        'id',
                        'service_id',
                        'name',
                        'description',
                        'sort_order',
                        'is_active',
                    ]);

                    if (!$this->isAdminRole($user->role)) {
                        $query->where('is_active', true);
                    }
                },
            ])
            ->orderBy('sort_order');

        $this->applyAccessScope($query, $user);

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $service = ManagedService::query()
            ->fixed()
            ->select([
                'id',
                'group_id',
                'code',
                'name',
                'description',
                'is_active',
                'sort_order',
            ])
            ->with([
                'group:id,name',
                'options' => function ($query) use ($user) {
                    $query->select([
                        'id',
                        'service_id',
                        'name',
                        'description',
                        'sort_order',
                        'is_active',
                    ]);

                    if (!$this->isAdminRole($user->role)) {
                        $query->where('is_active', true);
                    }
                },
            ])
            ->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service tidak ditemukan.',
            ], 404);
        }

        if (!$this->canViewService($service, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke layanan ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    private function applyAccessScope($query, $user): void
    {
        if ($this->isAdminRole($user->role)) {
            return;
        }

        $query->where('is_active', true);

        if ($user->role === 'user') {
            $groupIds = $user->groups()
                ->pluck('groups.id');

            $query->whereIn('group_id', $groupIds);
        }
    }

    private function canViewService(ManagedService $service, $user): bool
    {
        if ($this->isAdminRole($user->role)) {
            return true;
        }

        if (!$service->is_active) {
            return false;
        }

        if ($user->role === 'viewer') {
            return true;
        }

        if ($user->role !== 'user') {
            return false;
        }

        return $user->groups()
            ->where('groups.id', $service->group_id)
            ->exists();
    }

    private function isAdminRole(string $role): bool
    {
        return in_array(
            $role,
            ['admin', 'super_admin'],
            true
        );
    }
}
