{{--
    Asked when the ✕ next to an employee is pressed: "Left" (pay stops today, leaving date saved) or "Inactive"
    (off attendance and payroll, no leaving date). Records are kept either way. Buttons with data-leave open it.
--}}
<div class="scrim" id="leaveScrim" hidden>
    <form method="POST" id="leaveForm" class="modal" role="dialog" aria-modal="true" aria-labelledby="leaveTitle" style="width:min(460px,100%)">
        @csrf @method('PATCH')
        <div class="modal-h"><div><h2 id="leaveTitle"></h2><p>{{ __('They come off attendance and payroll; their records are kept under Left / inactive.') }}</p></div></div>
        <div class="modal-b" style="display:grid;gap:10px">
            <button type="submit" name="reason" value="left" class="btn" style="justify-content:flex-start;color:var(--bad)"><x-icon name="x" />{{ __('Left — pay stops today') }}</button>
            <button type="submit" name="reason" value="inactive" class="btn" style="justify-content:flex-start"><x-icon name="clock" />{{ __('Inactive — no leaving date') }}</button>
        </div>
        <div class="modal-f">
            <button type="button" class="btn" id="leaveCancel">{{ __('Cancel') }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    (() => {
        const scrim = document.getElementById('leaveScrim'), form = document.getElementById('leaveForm');
        const title = document.getElementById('leaveTitle'), titleText = @json(__('What happened to :name?'));
        const close = () => { scrim.hidden = true; };
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-leave]');
            if (!btn) { return; }
            form.action = btn.dataset.leave;
            title.textContent = titleText.replace(':name', btn.dataset.name);
            scrim.hidden = false;
        });
        document.getElementById('leaveCancel').addEventListener('click', close);
        scrim.addEventListener('click', e => { if (e.target === scrim) { close(); } });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !scrim.hidden) { close(); } });
    })();
</script>
@endpush
