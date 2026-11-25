<x-layouts.app :title="__('Settings')">
    <x-page-head :title="__('Settings')" :subtitle="__('Only the owner can change these.')" />
    <form method="POST" action="{{ route('settings.update') }}" class="card form-card">
        @csrf @method('PUT')
        <div class="card-h"><div><h3>{{ __('Cash and paper') }}</h3></div></div>
        <div class="card-b"><div class="form">
            <div class="field full"><input type="hidden" name="approvals_required" value="0"><label class="check-row"><input type="checkbox" name="approvals_required" value="1" @checked(old('approvals_required', $approvalsRequired))> {{ __('Approvals required — the manager\'s entries wait for the owner') }}</label><span class="hint">{{ __('Turn off to approve every entry as it is saved; nothing goes to Approvals.') }}</span></div>
            <x-field name="manager_daily_limit" type="number" min="0" :label="__('Manager daily spending limit (Rs)')" :value="$dailyLimit" required :hint="__('Spends within this total per day are approved automatically. Set 0 to approve every spend yourself.')" />
            <x-field name="default_wastage_percent" type="number" step="0.5" min="0" :label="__('Default wastage %')" :value="$defaultWastage" required :hint="__('Pre-filled when issuing paper. To be confirmed with the owner (2–4%).')" />
        </div></div>

        <div class="card-h" style="border-top:1px solid var(--line-2);padding-top:18px"><div><h3>{{ __('Payroll rules') }}</h3>
            <div class="sub">{{ __('Defaults follow the scoping notes and are still to be confirmed with the owner. Changes apply the next time a payroll is prepared or recalculated.') }}</div></div></div>
        <div class="card-b"><div class="form">
            <x-field name="ot_divisor" type="number" step="0.5" min="1" :label="__('Overtime: divide daily rate by')" :value="$payroll['ot_divisor']" required :hint="__('Haider: daily rate ÷ 6 per hour. For 150% of an hourly rate, enter the working hours here.')" />
            <x-field name="ot_multiplier" type="number" step="0.05" min="0.5" :label="__('Overtime: then multiply by')" :value="$payroll['ot_multiplier']" required :hint="__('1 = straight rate, 1.5 = time and a half (Abdullah).')" />
            <x-field name="shift_start" type="time" :label="__('Shift starts')" :value="substr($payroll['shift_start'], 0, 5)" required :hint="__('Clocking in after this counts as late.')" />
            <x-field name="shift_end" type="time" :label="__('Shift ends')" :value="substr($payroll['shift_end'], 0, 5)" required :hint="__('Clocking out before this counts as early; time after it is overtime (in at 10 and out at 6 = 1 hour late and 1 hour overtime).')" />
            <x-field name="night_start" type="time" :label="__('Night shift starts')" :value="substr($payroll['night_start'], 0, 5)" required />
            <x-field name="night_end" type="time" :label="__('Night shift ends (next morning)')" :value="substr($payroll['night_end'], 0, 5)" required />
            <x-field name="late_grace" type="number" min="0" max="120" step="1" :label="__('Late after (minutes past shift start)')" :value="$payroll['late_grace']" required :hint="__('20 = up to 9:20 is on time; after that the whole time from 9:00 counts (9:25 = 25 minutes late).')" />
            <x-field name="early_grace" type="number" min="0" max="120" step="1" :label="__('Early before (minutes before shift end)')" :value="$payroll['early_grace']" required :hint="__('15 = leaving from 4:45 is not early; before that the whole time before 5:00 counts (4:30 = 30 minutes).')" />
            <x-field name="overtime_grace" type="number" min="0" max="120" step="1" :label="__('Overtime after (minutes past shift end)')" :value="$payroll['overtime_grace']" required :hint="__('15 = up to 5:15 is not overtime; after that the whole time after 5:00 counts (5:40 = 40 minutes).')" />
            <x-field name="standard_hours" type="number" step="0.5" min="1" :label="__('Normal working hours a day')" :value="$payroll['standard_hours']" required :hint="__('Used for the hourly rate in salary sums.')" />
            <x-field name="unmarked_as" :label="__('Days with no attendance count as')" :options="['present' => __('Present (no deduction)'), 'absent' => __('Absent (deducted)')]" :value="$payroll['unmarked_as']" required :hint="__('Once the biometric device is in daily use, set this to Absent.')" />
            <x-field name="mid_month_day" type="number" min="1" max="28" :label="__('Advance salary day')" :value="$payroll['mid_month_day']" required />
            <x-field name="mid_month_percent" type="number" step="1" min="0" max="100" :label="__('Advance salary, % of salary')" :value="$payroll['mid_month_percent']" required :hint="__('Pre-filled on the mid-month sheet; each amount can be changed.')" />
            <x-field name="pay_day" type="number" min="1" max="28" :label="__('Salary paid on (day of next month)')" :value="$payroll['pay_day']" required />
        </div></div>
        <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save settings') }}</button></div>
    </form>
</x-layouts.app>
