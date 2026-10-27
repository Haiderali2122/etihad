<x-layouts.app :title="__('Foreman advance sheet')">
    <x-page-head :title="__('Foreman advance sheet')" :subtitle="__('Cash given to a foreman. List who he handed it to; each amount becomes that person\'s advance. Whatever is left stays as the foreman\'s own advance.')" />

    @if ($foremen->isEmpty())
        <div class="card"><div class="empty"><b>{{ __('No foremen yet') }}</b>{{ __('Tick “Foreman” on an employee\'s record first.') }}</div></div>
    @else
        <form method="POST" action="{{ route('foreman-sheets.store') }}" class="card form-card" id="sheetForm">
            @csrf
            <div class="card-b"><div class="form">
                <x-field name="foreman_id" :label="__('Foreman')" :options="$foremen->pluck('name', 'id')" :value="$foremanId" required />
                <x-field name="given_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                <x-field name="amount" type="number" min="1" :label="__('Cash given to the foreman (Rs)')" required />
                <x-field name="note" :label="__('Note')" />
            </div></div>
            <div class="card-h" style="padding-top:0"><div><h3>{{ __('Handed out to') }}</h3><div class="sub" id="sheetSummary"></div></div>
                <button type="button" class="btn sm" id="addLine"><x-icon name="plus" />{{ __('Add person') }}</button></div>
            <div class="tbl-wrap" style="margin-top:12px"><table class="fmt-table">
                <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Amount (Rs)') }}</th><th></th></tr></thead>
                <tbody id="lines">
                @foreach (old('lines', [[]]) as $i => $line)
                    @include('foreman-sheets.line', ['i' => $i, 'line' => $line])
                @endforeach
                </tbody>
            </table></div>
            @error('lines.*.employee_id')<div class="card-b"><div class="flash err">{{ $message }}</div></div>@enderror
            <div class="form-actions"><a class="btn" href="{{ route('salary-advances.index') }}">{{ __('Cancel') }}</a><button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save sheet') }}</button></div>
        </form>
        <template id="lineTemplate">@include('foreman-sheets.line', ['i' => '__I__', 'line' => []])</template>
        @push('scripts')
            <script>
                (() => {
                    const lines = document.getElementById('lines'), amount = document.getElementById('f_amount'), foreman = document.getElementById('f_foreman_id');
                    const teams = @json($foremen->mapWithKeys(fn ($f) => [$f->id => $f->team->pluck('id')]));
                    let next = Date.now();
                    const fmt = n => 'Rs ' + n.toLocaleString('en-PK');
                    const summary = () => {
                        const handed = [...lines.querySelectorAll('[name$="[amount]"]')].reduce((s, i) => s + (+i.value || 0), 0), given = +amount.value || 0;
                        const left = given - handed;
                        document.getElementById('sheetSummary').innerHTML = left >= 0
                            ? `{{ __('Handed out') }} ${fmt(handed)} · {{ __('Kept by the foreman') }} <b>${fmt(left)}</b>`
                            : `<span style="color:var(--bad)">{{ __('Handed out is more than the cash given by') }} ${fmt(-left)}</span>`;
                    };
                    const add = (employeeId = '') => {
                        lines.insertAdjacentHTML('beforeend', document.getElementById('lineTemplate').innerHTML.replaceAll('__I__', next++));
                        if (employeeId) { lines.lastElementChild.querySelector('select').value = employeeId; }
                        window.stackTables?.();
                    };
                    document.getElementById('addLine').addEventListener('click', () => add());
                    lines.addEventListener('click', e => { const b = e.target.closest('[data-remove]'); if (b) { b.closest('tr').remove(); summary(); } });
                    lines.addEventListener('input', summary);
                    amount.addEventListener('input', summary);
                    // Picking a foreman lists his team, ready for amounts.
                    foreman.addEventListener('change', () => {
                        const empty = [...lines.querySelectorAll('select')].every(s => !s.value);
                        if (!empty) { return; }
                        lines.innerHTML = '';
                        (teams[foreman.value] || []).forEach(id => add(id));
                        if (!lines.children.length) { add(); }
                        summary();
                    });
                    if ([...lines.querySelectorAll('select')].every(s => !s.value)) { foreman.dispatchEvent(new Event('change')); }
                    summary();
                })();
            </script>
        @endpush
    @endif
</x-layouts.app>
