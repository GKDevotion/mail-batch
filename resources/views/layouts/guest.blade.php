<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#5b47f5">
    <title>@yield('title', 'Sign in') · {{ config('mailbatch.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    @foreach ([400, 500, 600, 700] as $w)
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.1.0/{{ $w }}.css">
    @endforeach
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ @filemtime(public_path('assets/css/app.css')) }}">
</head>
<body>
<main class="mb-auth">
    <div class="mb-auth-box">
        <div class="text-center mb-4 mb-auth-brand">
            <img src="{{ asset('assets/img/logo-mark.svg') }}" width="56" height="56" alt="MailBatch logo" class="mb-3">
            <div class="fs-3 fw-bold">{{ config('mailbatch.name') }}</div>
            <div class="text-body-secondary">{{ config('mailbatch.tagline') }}</div>
        </div>
        <div class="card mb-auth-card">
            <div class="card-body p-4">
                <x-alert-messages />
                @yield('content')
            </div>
        </div>
        <p class="text-center small text-body-secondary mt-4 mb-0">&copy; {{ date('Y') }} {{ config('mailbatch.name') }}</p>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
