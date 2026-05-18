<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group_name');
        $todayRate = ExchangeRate::where('rate_date', today()->toDateString())->first();
        $lastRate = ExchangeRate::orderByDesc('rate_date')->first();

        return Inertia::render('Settings/Index', [
            'title' => 'الإعدادات',
            'settings' => $settings,
            'todayRate' => $todayRate,
            'lastRate' => $lastRate,
            'recentRates' => ExchangeRate::orderByDesc('rate_date')->limit(10)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            Setting::where('key', $setting['key'])->update(['value' => $setting['value']]);
        }

        return redirect()->back()->with('success', 'تم تحديث الإعدادات بنجاح');
    }

    public function storeExchangeRate(Request $request)
    {
        $validated = $request->validate([
            'rate_date' => 'required|date',
            'sar_to_jod' => 'required|numeric|min:0.001',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['jod_to_sar'] = round(1 / $validated['sar_to_jod'], 6);
        $validated['set_by'] = auth()->id();

        ExchangeRate::updateOrCreate(
            ['rate_date' => $validated['rate_date']],
            $validated
        );

        return redirect()->back()->with('success', 'تم تحديث سعر الصرف بنجاح');
    }
}
