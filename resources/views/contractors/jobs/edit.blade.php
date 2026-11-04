<x-layouts.app :title="__('Edit job')">
    <x-page-head :back="route('contractors.show', $job->contractor_id)" :title="__('Edit :work job', ['work' => mb_strtolower($job->kind->label())])" :subtitle="$job->contractor->name.($job->workOrder ? ' · '.$job->workOrder->number : '')" />
    @php $binding = $job->kind === \App\Enums\ContractorJobKind::Binding; @endphp
    <form method="POST" action="{{ route('contractor-jobs.update', $job) }}" class="card form-card">
        @csrf @method('PUT')
        <div class="card-b"><div class="form">
            <x-field name="forms" type="number" step="0.5" min="0.5" :label="__('Forms per copy')" :value="\App\Models\InventoryItem::qty($job->forms)" required />
            <x-field name="copies" type="number" min="1" :label="__('Total books (copies)')" :value="$job->copies" required />
            @if ($binding)
                <x-field name="form_rate" type="number" step="0.01" min="0" :label="__('Binding rate per form (per 1,000 books)')" :value="(float) $job->form_rate" required />
                <x-field name="copy_rate" type="number" step="0.01" min="0" :label="__('Binding rate (per 1,000 books)')" :value="(float) $job->copy_rate" required />
            @else
                <x-field name="folding_rate" type="number" step="0.01" min="0" :label="__('Folding rate (per 1,000 forms)')" :value="(float) $job->folding_rate" required />
            @endif
            <x-field name="job_on" type="date" :label="__('Date')" :value="$job->job_on->toDateString()" required />
            <x-field name="note" :label="__('Note')" :value="$job->note" full />
        </div></div>
        <div class="form-actions"><a class="btn" href="{{ route('contractors.show', $job->contractor_id) }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save changes') }}</button></div>
    </form>
</x-layouts.app>
