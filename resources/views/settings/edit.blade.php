<x-layouts.app :title="__('Settings')">
    <x-page-head :title="__('Settings')" :subtitle="__('Only the owner can change these.')" />
    <form method="POST" action="{{ route('settings.update') }}" class="card form-card">
        @csrf @method('PUT')
        <div class="card-h"><div><h3>{{ __('Cash and paper') }}</h3></div></div>
        <div class="card-b"><div class="form">
            <x-field name="manager_daily_limit" type="number" min="0" :label="__('Manager daily spending limit (Rs)')" :value="$dailyLimit" required :hint="__('Spends within this total per day are approved automatically. Set 0 to approve every spend yourself.')" />
            <x-field name="default_wastage_percent" type="number" step="0.5" min="0" :label="__('Default wastage %')" :value="$defaultWastage" required :hint="__('Pre-filled when issuing paper. To be confirmed with the owner (2–4%).')" />
        </div></div>

        <div class="card-h" style="border-top:1px solid var(--line-2);padding-top:18px"><div><h3>{{ __('Payroll rules') }}</h3>
            <div class="sub">{{ __('Defaults follow the scoping notes and are still to be confirmed with the owner. Changes apply the next time a payroll is prepared or recalculated.') }}</div></div></div>
        <div class="card-b"><div class="form">
            <x-field name="ot_divisor" type="number" step="0.5" min="1" :label="__('Overtime: divide daily rate by')" :value="$payroll['ot_divisor']" required :hint="__('Haider: daily rate ÷ 6 per hour. For 150% of an hourly rate, enter the working hours here.')" />
            <x-field name="ot_multiplier" type="number" step="0.05" min="0.5" :label="__('Overtime: then multiply by')" :value="$payroll['ot_multiplier']" required :hint="__('1 = straight rate, 1.5 = time and a half (Abdullah).')" />
            <x-field name="standard_hours" type="number" step="0.5" min="1" :label="__('Normal working hours a day')" :value="$payroll['standard_hours']" required :hint="__('Time on the device beyond this counts as overtime (e.g. 9 to 9 with 8 normal hours = 4 hours OT).')" />
            <x-field name="unmarked_as" :label="__('Days with no attendance count as')" :options="['present' => __('Present (no deduction)'), 'absent' => __('Absent (deducted)')]" :value="$payroll['unmarked_as']" required :hint="__('Once the biometric device is in daily use, set this to Absent.')" />
            <x-field name="mid_month_day" type="number" min="1" max="28" :label="__('Mid-month advance day')" :value="$payroll['mid_month_day']" required />
            <x-field name="mid_month_percent" type="number" step="1" min="0" max="100" :label="__('Mid-month advance, % of salary')" :value="$payroll['mid_month_percent']" required :hint="__('Pre-filled on the mid-month sheet; each amount can be changed.')" />
            <x-field name="pay_day" type="number" min="1" max="28" :label="__('Salary paid on (day of next month)')" :value="$payroll['pay_day']" required />
        </div></div>
        <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save settings') }}</button></div>
    </form>
</x-layouts.app>
