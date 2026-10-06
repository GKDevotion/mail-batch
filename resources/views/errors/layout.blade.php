<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('heading') · {{ config('mailbatch.name', 'MailBatch') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<main class="mb-auth">
    <div class="mb-auth-box text-center" style="max-width: 480px;">
        <img src="{{ asset('assets/img/logo-mark.svg') }}" width="56" height="56" alt="MailBatch logo" class="mb-3">
        <div class="display-1 fw-bold text-primary">@yield('code')</div>
        <h1 class="h3 mb-3">@yield('heading')</h1>
        <p class="text-body-secondary mb-4">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn btn-primary">Back to the dashboard</a>
    </div>
</main>
</body>
</html>
