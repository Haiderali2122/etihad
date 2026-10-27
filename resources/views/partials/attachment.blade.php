@if ($path)
    <a href="{{ Storage::disk('public')->url($path) }}" target="_blank" rel="noopener" class="btn sm"><x-icon name="file" />{{ __('Bill') }}</a>
@endif
