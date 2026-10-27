<x-layouts.app :title="$employee->exists ? __('Edit employee') : __('Add employee')">
    <x-page-head :title="$employee->exists ? __('Edit employee') : __('Add employee')" :subtitle="__('Staff are records, not logins.')" />
    <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="card form-card">
        @csrf
        @if ($employee->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Name')" :value="$employee->name" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$employee->name_ur" dir="rtl" />
            <x-field name="code" :label="__('Employee no.')" :value="$employee->code" placeholder="E-001" required :hint="__('Use the same number as on the biometric device.')" />
            <x-field name="designation" :label="__('Designation')" :options="$designations" :value="$employee->designation" :placeholder="__('Choose…')" required />
            <x-field name="monthly_salary" type="number" min="0" :label="__('Monthly salary (Rs)')" :value="$employee->monthly_salary" required />
            <x-field name="foreman_id" :label="__('Works under foreman')" :options="$foremen->pluck('name', 'id')" :value="$employee->foreman_id" :placeholder="__('None')" />
            <x-field name="father_name" :label="__('Father\'s name')" :value="$employee->father_name" />
            <x-field name="cnic" :label="__('CNIC')" :value="$employee->cnic" placeholder="35202-1234567-1" />
            <x-field name="phone" :label="__('Phone')" :value="$employee->phone" />
            <x-field name="joined_on" type="date" :label="__('Joined on')" :value="$employee->joined_on?->toDateString()" />
            <x-field name="left_on" type="date" :label="__('Left on')" :value="$employee->left_on?->toDateString()" :hint="__('Pay stops after this date.')" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$employee->notes" full />
            <div class="field full"><input type="hidden" name="is_foreman" value="0"><label class="check-row"><input type="checkbox" name="is_foreman" value="1" @checked(old('is_foreman', $employee->is_foreman))> {{ __('Foreman — can be given cash to hand out to his team') }}</label></div>
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active))> {{ __('Active — on the attendance sheet and payroll') }}</label></div>
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save employee') }}</button></div>
    </form>
</x-layouts.app>
