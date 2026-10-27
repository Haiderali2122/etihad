<x-layouts.app :title="$machine->exists ? __('Edit machine') : __('Add machine')">
    <x-page-head :title="$machine->exists ? __('Edit machine') : __('Add machine')" />
    <form method="POST" action="{{ $machine->exists ? route('machines.update', $machine) : route('machines.store') }}" class="card form-card">
        @csrf
        @if ($machine->exists) @method('PUT') @endif
        <div class="card-b"><div class="form">
            <x-field name="code" :label="__('Machine no.')" :value="$machine->code" placeholder="P-01" required />
            <x-field name="name" :label="__('Name')" :value="$machine->name" required />
            <x-field name="type" :label="__('Type')" :options="collect(\App\Enums\MachineType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])" :value="$machine->type" required />
            <x-field name="status" :label="__('Status')" :options="collect(\App\Enums\MachineStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])" :value="$machine->status" required />
            <x-field name="model" :label="__('Model')" :value="$machine->model" />
            <x-field name="purchase_price" type="number" min="0" :label="__('Purchase price (Rs)')" :value="$machine->purchase_price" />
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$machine->notes" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ $machine->exists ? route('machines.show', $machine) : route('machines.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save') }}</button></div>
    </form>
</x-layouts.app>
