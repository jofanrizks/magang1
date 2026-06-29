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
            'app_name' => 'required',
            'primary_color' => 'required',
            'logo' => 'nullable|image'
        ]);

        $setting = Setting::first();

        if (!$setting) {

            $setting = Setting::create();

        }

        if ($request->hasFile('logo')) {

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
            'data' => $setting
        ]);
    }
}