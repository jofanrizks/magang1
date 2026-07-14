<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Request\UploadGroupFileRequest;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupFile;
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

        if ($user->role === 'user' && !$user->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'User belum memiliki group.',
            ], 422);
        }

        $query = GroupFile::with([
            'group',
            'user:id,nama',
        ]);

        $group = null;

        if ($user->role === 'user') {
            $query->where(
                'group_id',
                $user->group_id
            );

            $group = $user->group;
        }



        if ($user->role === 'viewer') {
            $validated = $request->validate([
                'group_id' => [
                    'required',
                    'integer',
                    'exists:groups,id',
                ],
            ]);

            $query->where(
                'group_id',
                $validated['group_id']
            );

            $group = Group::find(
                $validated['group_id']
            );
        }


        if (
            in_array(
                $user->role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            if ($request->filled('group_id')) {
                $validated = $request->validate([
                    'group_id' => [
                        'integer',
                        'exists:groups,id',
                    ],
                ]);

                $query->where(
                    'group_id',
                    $validated['group_id']
                );

                $group = Group::find(
                    $validated['group_id']
                );
            }
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

        if (!$user->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'User belum memiliki group.',
            ], 422);
        }

        $uploadedFile = $request->file('file');

        $extension =
            $uploadedFile->getClientOriginalExtension();

        $fileName = Str::uuid()->toString();

        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        $filePath = $uploadedFile->storeAs(
            'group-files/' . $user->group_id,
            $fileName,
            'public'
        );

        try {
            $groupFile = GroupFile::create([
                'user_id' => $user->id,
                'group_id' => $user->group_id,
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
                    '"',
                'ip_address' => $request->ip(),
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
            'file' => 'required|file|max:10240',
        ]);

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
                    $validated['group_id'],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($filePath);

            throw $exception;
        }

        $groupFile->load([
            'group',
            'user:id,nama',
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
        ]);

        $groupFile = GroupFile::find($id);

        if (!$groupFile) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        $targetGroupId = (int) $validated['group_id'];
        $oldGroupId = (int) $groupFile->group_id;

        if ($targetGroupId === $oldGroupId) {
            return response()->json([
                'success' => false,
                'message' => 'File sudah berada pada group tersebut.',
            ], 422);
        }

        $oldFilePath = $groupFile->file_path;

        $newFilePath =
            'group-files/' .
            $targetGroupId .
            '/' .
            $groupFile->file_name;

        if (
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
            $moved = Storage::disk('public')->move(
                $oldFilePath,
                $newFilePath
            );

            if (!$moved) {
                throw new \RuntimeException(
                    'File gagal dipindahkan pada penyimpanan.'
                );
            }

            $groupFile->update([
                'group_id' => $targetGroupId,
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
                    $targetGroupId,
                'ip_address' => $request->ip(),
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dipindahkan.',
            'data' => $groupFile,
        ]);
    }

    public function destroy(
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
            'ip_address' => request()->ip(),
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
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dihapus oleh admin',
        ]);
    }
}
