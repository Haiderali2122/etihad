@props(['title', 'subtitle' => null, 'back' => null])
<div class="page-head">
    <div>
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @if ($back || isset($actions))
        <div class="actions">
            @if ($back)
                <a class="btn" href="{{ $back }}">{{ __('Back') }}</a>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
</div>
