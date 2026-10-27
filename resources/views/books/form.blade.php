@php
    $rows = old('formats', $book->formats->map(fn ($f) => $f->only(['id', 'name', 'size', 'forms', 'pages', 'paper_type_id', 'folding_rate', 'binding_rate']))->all());
    if (empty($rows)) {
        $rows = [['name' => __('Standard')]];
    }
@endphp
<x-layouts.app :title="$book->exists ? __('Edit book') : __('Add book')">
    <x-page-head :title="$book->exists ? __('Edit book') : __('Add book')" :subtitle="__('Forms (فارمے) per copy drive the rims needed: forms × copies ÷ 500 sheets, plus wastage.')" />

    <form method="POST" action="{{ $book->exists ? route('books.update', $book) : route('books.store') }}" class="card" style="max-width:1100px">
        @csrf
        @if ($book->exists) @method('PUT') @endif

        <div class="card-b"><div class="form">
            <x-field name="name" :label="__('Book name')" :value="$book->name" placeholder="Urdu Reader Class 5" required />
            <x-field name="name_ur" :label="__('Name in Urdu')" :value="$book->name_ur" dir="rtl" />
            <x-field name="class_subject" :label="__('Class / subject')" :value="$book->class_subject" placeholder="Class 5, Urdu" />
            <x-field name="party_id" :label="__('Publisher (optional)')" :options="$publishers->pluck('name', 'id')" :value="$book->party_id" :placeholder="__('Not set')" />
        </div></div>

        <div class="card-h" style="padding-top:4px"><div><h3>{{ __('Formats') }}</h3><div class="sub">{{ __('One row for each way the book is printed. Rates can be left blank until agreed.') }}</div></div>
            <button type="button" class="btn sm" id="addFormat"><x-icon name="plus" />{{ __('Add format') }}</button></div>
        @error('formats')<div class="card-b" style="padding-bottom:0"><div class="flash err">{{ $message }}</div></div>@enderror

        <div class="tbl-wrap" style="margin-top:12px"><table class="fmt-table">
            <thead><tr>
                <th>{{ __('Format name') }} *</th><th>{{ __('Size') }}</th><th>{{ __('Forms') }} *</th><th>{{ __('Pages') }}</th>
                <th>{{ __('Default paper') }}</th><th>{{ __('Folding rate') }}</th><th>{{ __('Binding rate') }}</th><th></th>
            </tr></thead>
            <tbody id="formatRows">
            @foreach ($rows as $i => $row)
                @include('books.partials.format-row', ['i' => $i, 'row' => $row])
            @endforeach
            </tbody>
        </table></div>

        <div class="card-b"><div class="form">
            <x-field name="notes" type="textarea" :label="__('Notes')" :value="$book->notes" full />
            <div class="field full"><input type="hidden" name="is_active" value="0"><label class="check-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $book->is_active))> {{ __('Active') }}</label></div>
        </div></div>

        <div class="form-actions">
            <a class="btn" href="{{ $book->exists ? route('books.show', $book) : route('books.index') }}">{{ __('Cancel') }}</a>
            <button class="btn primary" type="submit"><x-icon name="check" />{{ __('Save book') }}</button>
        </div>
    </form>

    <template id="formatTemplate">@include('books.partials.format-row', ['i' => '__I__', 'row' => []])</template>

    @push('scripts')
        <script>
            (() => {
                const rows = document.getElementById('formatRows');
                let next = Date.now();
                const refresh = () => rows.querySelectorAll('[data-remove]').forEach(b => b.disabled = rows.children.length === 1);
                document.getElementById('addFormat').addEventListener('click', () => {
                    rows.insertAdjacentHTML('beforeend', document.getElementById('formatTemplate').innerHTML.replaceAll('__I__', next++));
                    window.stackTables?.();
                    rows.lastElementChild.querySelector('input').focus();
                    refresh();
                });
                rows.addEventListener('click', e => {
                    const btn = e.target.closest('[data-remove]');
                    if (btn && rows.children.length > 1) { btn.closest('tr').remove(); refresh(); }
                });
                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
