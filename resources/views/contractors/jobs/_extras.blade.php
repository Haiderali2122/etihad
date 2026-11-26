{{--
    Extra work on a folding or binding job, each ticked only when it was done and charged per 1,000 books:
    sticker pasting (Rs 200 to start), numbering (Rs 65) and pinning (rate typed in for each job).
    Expects $job (null on the "give job" form).
--}}
<div class="field full"><label style="margin-bottom:2px">{{ __('Extra work') }}</label><span class="hint" style="margin-top:0">{{ __('Tick only what was done on this job. Each is charged per 1,000 books.') }}</span></div>
@foreach (\App\Models\ContractorJob::Extras as $extra => $info)
    @php
        $saved = $job?->{$extra.'_rate'};
        $ticked = (bool) old('has_'.$extra, $saved !== null);
        $rate = old($extra.'_rate', $saved !== null ? (float) $saved : $info['default']);
    @endphp
    <div class="field">
        <input type="hidden" name="has_{{ $extra }}" value="0">
        <label class="check-row" for="f_has_{{ $extra }}"><input type="checkbox" id="f_has_{{ $extra }}" name="has_{{ $extra }}" value="1" data-extra="{{ $extra }}" @checked($ticked)> {{ __($info['label']) }}</label>
        <div data-extra-rate="{{ $extra }}" @if (! $ticked) hidden @endif style="margin-top:6px">
            <input type="number" id="f_{{ $extra }}_rate" name="{{ $extra }}_rate" step="0.01" min="0" value="{{ $rate }}" placeholder="{{ __('Rate per 1,000 books') }}" aria-label="{{ __(':work rate (per 1,000 books)', ['work' => __($info['label'])]) }}">
        </div>
        @error($extra.'_rate')<span class="err">{{ $message }}</span>@enderror
    </div>
@endforeach

@push('scripts')
<script>
    // A ticked extra shows its rate box; unticked ones are hidden and not charged.
    document.querySelectorAll('input[data-extra]').forEach(box => box.addEventListener('change', () => {
        const rate = document.querySelector(`[data-extra-rate="${box.dataset.extra}"]`);
        rate.hidden = !box.checked;
        if (box.checked) { rate.querySelector('input').focus(); }
    }));
</script>
@endpush
