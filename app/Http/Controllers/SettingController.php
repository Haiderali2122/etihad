<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'dailyLimit' => (int) Setting::get(Setting::ManagerDailyLimit, 0),
            'defaultWastage' => (float) Setting::get(Setting::DefaultWastagePercent, 3),
            'payroll' => Setting::payrollRules(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::put(Setting::ManagerDailyLimit, $request->integer('manager_daily_limit'));
        Setting::put(Setting::DefaultWastagePercent, $request->input('default_wastage_percent'));

        foreach ([
            Setting::OvertimeDivisor => 'ot_divisor', Setting::OvertimeMultiplier => 'ot_multiplier',
            Setting::StandardHours => 'standard_hours', Setting::UnmarkedDaysAs => 'unmarked_as',
            Setting::MidMonthAdvanceDay => 'mid_month_day', Setting::MidMonthAdvancePercent => 'mid_month_percent',
            Setting::PayDay => 'pay_day',
        ] as $key => $field) {
            Setting::put($key, $request->input($field));
        }

        return redirect()->route('settings.edit')->with('status', __('Settings saved.'));
    }
}
