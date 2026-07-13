<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Setting::first()
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_name' => 'required',
            'primary_color' => 'required',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $setting = Setting::first();

        if (!$setting) {

            $setting = Setting::create();

        }

        if ($request->hasFile('logo')) {
            if (
                $setting->logo &&
                Storage::disk('public')->exists($setting->logo)
            ) {
                Storage::disk('public')->delete($setting->logo);
            }

            $setting->logo = $request
                ->file('logo')
                ->store('logo', 'public');

        }

        $setting->app_name =
            $request->app_name;

        $setting->primary_color =
            $request->primary_color;

        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Setting berhasil diperbarui',
            'data' => $setting
        ]);
    }
}
