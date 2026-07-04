<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class BannerController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Banner::where(
                'is_active',
                true
            )->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|in:Banner 1,Banner 2,Banner 3,Banner 4,Banner 5',
            'images' => 'required|array|max:1',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $user = auth()->user();

        $image = $request->file('images')[0];

        $path = $image->store('banner', 'public');

        $banner = Banner::where('title', $request->title)->first();

        if ($banner) {

            // hapus gambar lama
            if (
                $banner->image &&
                \Storage::disk('public')->exists($banner->image)
            ) {
                \Storage::disk('public')->delete($banner->image);
            }

            $banner->update([
                'image' => $path,
                'is_active' => true,
            ]);

        } else {

            $banner = Banner::create([
                'title' => $request->title,
                'image' => $path,
                'is_active' => true,
            ]);

        }

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Add Banner',
            'description' => "Mengubah {$request->title}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $banner,
        ]);
    }

    public function destroy($id)
    {
        Banner::findOrFail($id)
            ->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'activity' => 'Delete Banner',
            'description' => 'User menghapus banner',
            'ip_address' => $request->ip(),
        ]);
        return response()->json([
            'success' => true
        ]);
    }
}