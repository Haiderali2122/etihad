@props(['size' => 44, 'ring' => '#fff', 'fill' => '#fff'])
{{-- Etihad monogram, an approximation of the letterhead mark. --}}
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" aria-hidden="true">
    <path d="M8 34a20 20 0 0 1 4-24" fill="none" stroke="{{ $ring }}" stroke-width="1.4" opacity=".75"/><path d="M5 30a22 22 0 0 1 3-21" fill="none" stroke="{{ $ring }}" stroke-width="1.2" opacity=".55"/>
    <path d="M40 34a20 20 0 0 0-4-24" fill="none" stroke="{{ $ring }}" stroke-width="1.4" opacity=".75"/><path d="M43 30a22 22 0 0 0-3-21" fill="none" stroke="{{ $ring }}" stroke-width="1.2" opacity=".55"/>
    <text x="24" y="33" text-anchor="middle" font-family="Georgia,'Times New Roman',serif" font-style="italic" font-weight="700" font-size="26" fill="{{ $fill }}">E</text>
    <text x="31" y="22" text-anchor="middle" font-family="Georgia,serif" font-style="italic" font-size="11" fill="{{ $fill }}">P</text>
    <text x="31" y="38" text-anchor="middle" font-family="Georgia,serif" font-style="italic" font-size="11" fill="{{ $fill }}">P</text>
</svg>
