@props(['tone' => 'info'])
<span {{ $attributes->merge(['class' => "badge {$tone}"]) }}>{{ $slot }}</span>
