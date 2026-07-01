<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;

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
        'title' => 'nullable|string',
        'images' => 'required|array',
        'images.*' => 'image|mimes:jpg,jpeg,png|max:2048'
    ]);

        $banners = [];

        foreach ($request->file('images') as $image) {

            $path = $image->store('banner', 'public');

            $banners[] = Banner::create([
                'title' => $request->title,
                'image' => $path,
                'is_active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    public function destroy($id)
    {
        Banner::findOrFail($id)
            ->delete();

        return response()->json([
            'success' => true
        ]);
    }
}