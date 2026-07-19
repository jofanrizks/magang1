<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ManagedService;
use App\Models\ServiceOption;
use App\Support\ActivityLogContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = ManagedService::query()
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
                'options:id,service_id,name,description,sort_order,is_active',
            ])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $service = $this->findService($id);

        if (!$service) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $service = ManagedService::query()
            ->fixed()
            ->find($id);

        if (!$service) {
            return $this->notFoundResponse();
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
            'options' => [
                'required',
                'array',
            ],
            'options.*.id' => [
                'nullable',
                'integer',
                'distinct',
            ],
            'options.*.name' => [
                'required',
                'string',
                'min:1',
                'max:255',
            ],
            'options.*.description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'options.*.sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
            'options.*.is_active' => [
                'required',
                'boolean',
            ],
            'deleted_option_ids' => [
                'nullable',
                'array',
            ],
            'deleted_option_ids.*' => [
                'integer',
                'distinct',
            ],
        ]);

        $this->validateOptionOwnership(
            $service,
            $validated
        );

        try {
            DB::transaction(function () use ($service, $validated, $request) {
                $service->update([
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'is_active' => $validated['is_active'],
                ]);

                foreach ($validated['options'] as $index => $optionData) {
                    $payload = [
                        'name' => trim($optionData['name']),
                        'description' => $optionData['description'] ?? null,
                        'sort_order' => $index + 1,
                        'is_active' => $optionData['is_active'],
                    ];

                    if (!empty($optionData['id'])) {
                        ServiceOption::where('service_id', $service->id)
                            ->where('id', $optionData['id'])
                            ->update($payload);

                        continue;
                    }

                    $service->options()->create($payload);
                }

                $deletedOptionIds =
                    $validated['deleted_option_ids'] ?? [];

                if (!empty($deletedOptionIds)) {
                    $service->options()
                        ->whereIn('id', $deletedOptionIds)
                        ->delete();
                }

                $actor = $request->user();

                ActivityLog::create([
                    'user_id' => $actor->id,
                    'actor_id' => $actor->id,
                    'activity' => 'Update Service',
                    'description' =>
                        "Nama dan opsi {$service->name} diperbarui oleh {$actor->role}.",
                    ...ActivityLogContext::fromRequest($request),
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Failed to update managed service.', [
                'service_id' => $service->id,
                'actor_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Layanan gagal diperbarui.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil diperbarui.',
            'data' => $this->findService($service->id),
        ]);
    }

    private function validateOptionOwnership(
        ManagedService $service,
        array $validated
    ): void {
        $optionIds = collect($validated['options'])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $deletedOptionIds = collect($validated['deleted_option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        $duplicateTouchedIds = $optionIds
            ->intersect($deletedOptionIds)
            ->values();

        if ($duplicateTouchedIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'deleted_option_ids' =>
                    'Option yang diperbarui tidak boleh sekaligus dihapus.',
            ]);
        }

        $touchedOptionIds = $optionIds
            ->merge($deletedOptionIds)
            ->unique()
            ->values();

        if ($touchedOptionIds->isEmpty()) {
            return;
        }

        $ownedOptionIds = ServiceOption::where('service_id', $service->id)
            ->whereIn('id', $touchedOptionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $invalidOptionIds = $touchedOptionIds
            ->diff($ownedOptionIds)
            ->values();

        if ($invalidOptionIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'options' =>
                    'Terdapat option yang bukan milik layanan ini.',
            ]);
        }
    }

    private function findService(int $id): ?ManagedService
    {
        return ManagedService::query()
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
                'options:id,service_id,name,description,sort_order,is_active',
            ])
            ->find($id);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Service tidak ditemukan.',
        ], 404);
    }
}
