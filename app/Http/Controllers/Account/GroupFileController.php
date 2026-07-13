<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Request\UploadGroupFileRequest;
use App\Models\ActivityLog;
use App\Models\GroupFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GroupFileController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'User belum memiliki group.',
            ], 422);
        }

        $files = GroupFile::with([
                'group',
                'user:id,nama',
            ])
            ->where('group_id', $user->group_id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Data file group berhasil diambil',
            'data' => [
                'group' => $user->group,
                'files' => $files,
            ],
        ]);
    }

    public function store(UploadGroupFileRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->group_id) {
            return response()->json([
                'success' => false,
                'message' => 'User belum memiliki group.',
            ], 422);
        }

        $uploadedFile = $request->file('file');

        $extension = $uploadedFile->getClientOriginalExtension();

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
                'original_name' => $uploadedFile->getClientOriginalName(),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getMimeType(),
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'activity' => 'Upload File',
                'description' => 'Mengunggah file "' . $groupFile->original_name . '"',
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($filePath);

            throw $exception;
        }

        $groupFile->load('group');

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diunggah',
            'data' => $groupFile,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        $groupFile = GroupFile::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$groupFile) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.',
            ], 404);
        }

        $originalName = $groupFile->original_name;
        $filePath = $groupFile->file_path;

        Storage::disk('public')->delete($filePath);
        $groupFile->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Delete File',
            'description' => 'Menghapus file "' . $originalName . '"',
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dihapus',
        ]);
    }
}