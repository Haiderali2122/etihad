<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} · Etihad ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="auth-body">
<main class="auth">
    <section class="auth-brand" aria-label="Etihad Publishers & Printers">
        {{-- Letterhead bands: the same indigo and royal-blue swoosh as Etihad's printed stationery. --}}
        <svg class="auth-waves" viewBox="0 0 800 900" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 760 C 220 690, 420 860, 800 720 L800 900 L0 900Z" fill="#2E3490"/>
            <path d="M0 810 C 240 750, 460 900, 800 790 L800 900 L0 900Z" fill="#1C2066"/>
            <path d="M0 775 C 230 705, 430 875, 800 735" stroke="#3E6FC1" stroke-width="6" fill="none"/>
            <circle cx="690" cy="140" r="210" fill="#2E3490" opacity=".55"/>
            <circle cx="690" cy="140" r="130" fill="none" stroke="#3E6FC1" stroke-width="2" opacity=".5"/>
        </svg>
        <div class="auth-watermark" aria-hidden="true"><x-mark :size="420" /></div>

        <div class="auth-brand-top">
            <div class="auth-logo"><x-mark :size="58" /></div>
            <div>
                <div class="auth-wordmark">ETIHAD</div>
                <div class="auth-sub">Publishers &amp; Printers</div>
            </div>
        </div>

        <div class="auth-pitch">
            <h1>{{ __('Every rim and every rupee, on one record.') }}</h1>
            <p class="auth-ur" lang="ur" dir="rtl">ہر رم ٹرک سے خریدار تک، اور ہر روپیہ مالک سے خرچ تک</p>
            <ul class="auth-points">
                <li><span><x-icon name="truck" /></span>{{ __('Paper traced from truck slip to press') }}</li>
                <li><span><x-icon name="wallet" /></span>{{ __('Cash given out, matched to every spend') }}</li>
                <li><span><x-icon name="check" /></span>{{ __('Owner approval on bills, repairs and expenses') }}</li>
            </ul>
        </div>

        <div class="auth-foot" dir="ltr">{{ config('press.address') }}</div>
    </section>

    <section class="auth-panel">
        <div class="auth-lang lang" role="group" aria-label="{{ __('Language') }}">
            <a href="{{ route('locale', 'en') }}" @class(['on' => app()->getLocale() === 'en'])>English</a>
            <a href="{{ route('locale', 'ur') }}" @class(['ur', 'on' => app()->getLocale() === 'ur'])>اردو</a>
        </div>

        <form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
            @csrf
            <div class="auth-mobile-brand"><x-mark :size="44" ring="#262B7E" fill="#262B7E" /><b>ETIHAD</b></div>
            <h2>{{ __('Welcome back') }}</h2>
            <p class="auth-lead">{{ __('Sign in to the Etihad press office.') }}</p>

            @if ($errors->any())
                <div class="auth-error" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>
            @endif

            <div class="auth-field">
                <label for="email">{{ __('Email') }}</label>
                <div class="auth-input" dir="ltr">
                    <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@etihad.pk">
                </div>
            </div>

            <div class="auth-field">
                <label for="password">{{ __('Password') }}</label>
                <div class="auth-input" dir="ltr">
                    <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    <button type="button" class="auth-reveal" id="reveal" aria-label="{{ __('Show password') }}" aria-pressed="false"><x-icon name="eye" /></button>
                </div>
            </div>

            <label class="auth-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('Keep me signed in on this computer') }}</label>

            <button class="auth-submit" type="submit">{{ __('Sign in') }} <x-icon name="chev" /></button>

            <p class="auth-help">{{ __('Forgot your password? Ask the owner to reset it from Users & roles.') }}</p>
        </form>

        <div class="auth-copy">© {{ now()->year }} {{ config('press.name') }}</div>
    </section>
</main>
<script>
    document.getElementById('reveal').addEventListener('click', e => {
        const input = document.getElementById('password'), show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        e.currentTarget.setAttribute('aria-pressed', String(show));
        e.currentTarget.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
    });
</script>
</body>
</html>
