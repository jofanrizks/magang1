<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(
            Setting::first()
        );
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string',
            'primary_color' => 'required|string',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $setting = Setting::first();

        if (!$setting) {
            $setting = Setting::create();
        }

        if ($request->hasFile('logo')) {
            $setting->logo = $request->file('logo')
                ->store('logo', 'public');
        }

        if ($request->hasFile('banner')) {
            $setting->banner = $request->file('banner')
                ->store('banner', 'public');
        }

        $setting->app_name = $request->app_name;
        $setting->primary_color = $request->primary_color;

        $setting->save();

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }
}