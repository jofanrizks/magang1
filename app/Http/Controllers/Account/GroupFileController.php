<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Request\UploadGroupFileRequest;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupFile;
use App\Models\ServiceOption;
use App\Support\ActivityLogContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GroupFileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = GroupFile::with([
            'group',
            'user:id,nama',
            'serviceOption:id,service_id,name',
            'serviceOption.service:id,group_id,code,name',
        ]);

        $group = null;
        $validatedFilters = $request->validate([
            'group_id' => [
                'nullable',
                'integer',
                'exists:groups,id',
            ],
            'service_option_id' => [
                'nullable',
                'integer',
                'exists:service_options,id',
            ],
        ]);

        $requestedGroupId = $validatedFilters['group_id'] ?? null;
        $requestedOptionId = $validatedFilters['service_option_id'] ?? null;

        $option = $requestedOptionId
            ? $this->findServiceOption((int) $requestedOptionId)
            : null;

        if (
            $option &&
            $requestedGroupId &&
            (int) $option->service?->group_id !== (int) $requestedGroupId
        ) {
            return $this->optionGroupMismatchResponse();
        }

        if ($user->role === 'user') {
            $groupIds = $user->groups()
                ->pluck('groups.id');

            if ($groupIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User belum memiliki group.',
                ], 422);
            }

            if ($requestedGroupId) {

                if (!$groupIds->contains((int) $requestedGroupId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses ke group ini.',
                    ], 403);
                }

                $query->where(
                    'group_id',
                    $requestedGroupId
                );

                $group = Group::find(
                    $requestedGroupId
                );
            } else {
                $query->whereIn('group_id', $groupIds);
            }
        }

        if ($user->role === 'viewer') {
            if (!$requestedGroupId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group tujuan wajib dipilih.',
                ], 422);
            }

            $query->where(
                'group_id',
                $requestedGroupId
            );

            $group = Group::find(
                $requestedGroupId
            );
        }


        if (
            in_array(
                $user->role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            if ($requestedGroupId) {

                $query->where(
                    'group_id',
                    $requestedGroupId
                );

                $group = Group::find(
                    $requestedGroupId
                );
            }
        }

        if ($option) {
            $query->where(
                'service_option_id',
                $option->id
            );
        }

        $files = $query
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Data file group berhasil diambil',
            'data' => [
                'group' => $group,
                'files' => $files,
            ],
        ]);
    }

    public function store(
        UploadGroupFileRequest $request
    ): JsonResponse {
        $user = Auth::user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya User yang dapat mengunggah file.',
            ], 403);
        }

        $validated = $request->validated();
        $targetGroupId = (int) $validated['group_id'];
        $option = $this->findServiceOption(
            (int) $validated['service_option_id']
        );

        if (!$this->optionMatchesGroup($option, $targetGroupId)) {
            return $this->optionGroupMismatchResponse();
        }

        if (
            !$option->is_active ||
            !$option->service?->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Opsi layanan tidak aktif.',
            ], 403);
        }

        $hasAccess = $user->groups()
            ->where('groups.id', $targetGroupId)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengunggah file ke group ini.',
            ], 403);
        }

        $uploadedFile = $request->file('file');

        $extension =
            $uploadedFile->getClientOriginalExtension();

        $fileName = Str::uuid()->toString();

        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        $filePath = $uploadedFile->storeAs(
            'group-files/' . $targetGroupId,
            $fileName,
            'public'
        );

        try {
            $groupFile = GroupFile::create([
                'user_id' => $user->id,
                'group_id' => $targetGroupId,
                'service_option_id' => $option->id,
                'original_name' =>
                    $uploadedFile->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Upload File',
                'description' =>
                    'Mengunggah file "' .
                    $groupFile->original_name .
                    '" pada opsi ' .
                    $option->name,
                ...ActivityLogContext::fromRequest($request),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(
                $filePath
            );

            throw $exception;
        }

        $groupFile->load([
            'group',
            'user:id,nama',
            'serviceOption:id,service_id,name',
            'serviceOption.service:id,group_id,code,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diunggah',
            'data' => $groupFile,
        ], 201);
    }

    public function adminStore(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (
            !in_array(
                $user->role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengunggah file admin.',
            ], 403);
        }

        $validated = $request->validate([
            'group_id' => [
                'required',
                'integer',
                'exists:groups,id',
            ],
            'service_option_id' => [
                'required',
                'integer',
                'exists:service_options,id',
            ],
            'file' => 'required|file|max:10240',
        ]);

        $option = $this->findServiceOption(
            (int) $validated['service_option_id']
        );

        if (!$this->optionMatchesGroup($option, (int) $validated['group_id'])) {
            return $this->optionGroupMismatchResponse();
        }

        $uploadedFile = $request->file('file');
        $extension = $uploadedFile->getClientOriginalExtension();
        $fileName = Str::uuid()->toString();

        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        $filePath = $uploadedFile->storeAs(
            'group-files/' . $validated['group_id'],
            $fileName,
            'public'
        );

        try {
            $groupFile = GroupFile::create([
                'user_id' => $user->id,
                'group_id' => $validated['group_id'],
                'service_option_id' => $option->id,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'actor_id' => $user->id,
                'activity' => 'Admin Upload File',
                'description' =>
                    'Mengunggah file "' .
                    $groupFile->original_name .
                    '" ke group-' .
                    $validated['group_id'] .
                    ' opsi ' .
                    $option->name,
                ...ActivityLogContext::fromRequest($request),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($filePath);

            throw $exception;
        }

        $groupFile->load([
            'group',
            'user:id,nama',
            'serviceOption:id,service_id,name',
            'serviceOption.service:id,group_id,code,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diunggah oleh admin',
            'data' => $groupFile,
        ], 201);
    }

    public function move(
        Request $request,
        int $id
    ): JsonResponse {
        $user = Auth::user();

        if (
            !in_array(
                $user->role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk memindahkan file.',
            ], 403);
        }

        $validated = $request->validate([
            'group_id' => [
                'required',
                'integer',
                'exists:groups,id',
            ],
            'service_option_id' => [
                'required',
                'integer',
                'exists:service_options,id',
            ],
        ]);

        $option = $this->findServiceOption(
            (int) $validated['service_option_id']
        );

        $groupFile = GroupFile::find($id);

        if (!$groupFile) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        $targetGroupId = (int) $validated['group_id'];
        $targetOptionId = (int) $validated['service_option_id'];
        $oldGroupId = (int) $groupFile->group_id;
        $oldOptionId = (int) $groupFile->service_option_id;

        if (!$this->optionMatchesGroup($option, $targetGroupId)) {
            return $this->optionGroupMismatchResponse();
        }

        if (
            $targetGroupId === $oldGroupId &&
            $targetOptionId === $oldOptionId
        ) {
            return response()->json([
                'success' => false,
                'message' => 'File sudah berada pada layanan dan opsi tersebut.',
            ], 422);
        }

        $oldFilePath = $groupFile->file_path;

        $newFilePath = $targetGroupId === $oldGroupId
            ? $oldFilePath
            : 'group-files/' .
                $targetGroupId .
                '/' .
                $groupFile->file_name;

        if (
            $targetGroupId !== $oldGroupId &&
            !Storage::disk('public')->exists(
                $oldFilePath
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'File fisik tidak ditemukan pada penyimpanan.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $moved = true;

            if ($targetGroupId !== $oldGroupId) {
                $moved = Storage::disk('public')->move(
                    $oldFilePath,
                    $newFilePath
                );
            }

            if (!$moved) {
                throw new \RuntimeException(
                    'File gagal dipindahkan pada penyimpanan.'
                );
            }

            $groupFile->update([
                'group_id' => $targetGroupId,
                'service_option_id' => $targetOptionId,
                'file_path' => $newFilePath,
            ]);

            ActivityLog::create([
                'user_id' => $groupFile->user_id,
                'actor_id' => $user->id,
                'activity' => 'Move File',
                'description' =>
                    $user->role .
                    ' ' .
                    $user->nama .
                    ' memindahkan file "' .
                    $groupFile->original_name .
                    '" dari group-' .
                    $oldGroupId .
                    ' ke group-' .
                    $targetGroupId .
                    ' opsi ' .
                    $option->name,
                ...ActivityLogContext::fromRequest($request),
            ]);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            if (
                Storage::disk('public')->exists(
                    $newFilePath
                ) &&
                !Storage::disk('public')->exists(
                    $oldFilePath
                )
            ) {
                Storage::disk('public')->move(
                    $newFilePath,
                    $oldFilePath
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'File gagal dipindahkan.',
            ], 500);
        }

        $groupFile->load([
            'group',
            'user:id,nama',
            'serviceOption:id,service_id,name',
            'serviceOption.service:id,group_id,code,name',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dipindahkan.',
            'data' => $groupFile,
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = Auth::user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya User yang dapat menghapus file.',
            ], 403);
        }

        $groupFile = GroupFile::where(
                'id',
                $id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (!$groupFile) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        $originalName =
            $groupFile->original_name;

        $filePath =
            $groupFile->file_path;

        Storage::disk('public')->delete(
            $filePath
        );

        $groupFile->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Delete File',
            'description' =>
                'Menghapus file "' .
                $originalName .
                '"',
            ...ActivityLogContext::fromRequest($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dihapus',
        ]);
    }

    public function adminDestroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = Auth::user();

        if (
            !in_array(
                $user->role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus file admin.',
            ], 403);
        }

        $groupFile = GroupFile::find($id);

        if (!$groupFile) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        $originalName = $groupFile->original_name;
        $filePath = $groupFile->file_path;
        $oldGroupId = $groupFile->group_id;

        Storage::disk('public')->delete($filePath);

        $groupFile->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'activity' => 'Admin Delete File',
            'description' =>
                'Menghapus file "' .
                $originalName .
                '" dari group-' .
                $oldGroupId,
            ...ActivityLogContext::fromRequest($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dihapus oleh admin',
        ]);
    }

    private function findServiceOption(int $id): ?ServiceOption
    {
        return ServiceOption::with([
            'service:id,group_id,code,name,is_active',
        ])->find($id);
    }

    private function optionMatchesGroup(
        ?ServiceOption $option,
        int $groupId
    ): bool {
        return $option &&
            $option->service &&
            (int) $option->service->group_id === $groupId;
    }

    private function optionGroupMismatchResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Opsi layanan tidak sesuai dengan group tujuan.',
        ], 422);
    }
}
