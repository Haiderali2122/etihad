<x-layouts.app :title="__('Approvals')">
    <x-page-head :title="__('Approvals')" :subtitle="__('Entries the manager has made that need your decision.')" />
    <div class="card"><div class="card-b"><div class="list">
        @forelse ($items as $item)
            @php $m = $item['model']; @endphp
            <div class="row" style="flex-wrap:wrap">
                <span @class(['ic-tile', 'warn' => $m->approvalAmount()])><x-icon :name="$m->approvalIcon()" /></span>
                <div class="grow" style="min-width:220px">
                    <div class="t"><a href="{{ $m->approvalUrl() }}" style="color:inherit">{{ $m->approvalTitle() }}</a></div>
                    <div class="s">{{ $m->approvalKind() }} · {{ $m->approvalDetail() }} · {{ __('by :name', ['name' => $m->creator?->name ?? '—']) }}, {{ $m->created_at->diffForHumans() }}</div>
                </div>
                @if ($m->approvalAmount())<b class="num">Rs {{ number_format($m->approvalAmount()) }}</b>@endif
                @include('partials.attachment', ['path' => $m->attachment])
                <form method="POST" action="{{ route('approvals.update', [$item['type'], $m->id]) }}" class="inline-form">
                    @csrf @method('PATCH')
                    <input type="text" name="note" placeholder="{{ __('Note (needed to send back)') }}" aria-label="{{ __('Note') }}">
                    <button class="btn sm" name="decision" value="reject" type="submit"><x-icon name="back" />{{ __('Send back') }}</button>
                    <button class="btn sm good" name="decision" value="approve" type="submit"><x-icon name="check" />{{ __('Approve') }}</button>
                </form>
            </div>
        @empty
            <div class="empty"><b>{{ __('All clear') }}</b>{{ __('No entries are waiting.') }}</div>
        @endforelse
        @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
    </div></div></div>
</x-layouts.app>
