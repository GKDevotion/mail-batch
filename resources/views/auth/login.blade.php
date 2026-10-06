@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h1 class="h4 mb-3">Sign in</h1>
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus
                   class="form-control @error('email') is-invalid @enderror">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required
                   class="form-control @error('password') is-invalid @enderror">
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
    @if (Route::has('register'))
        <p class="text-center small mt-3 mb-0">No account? <a href="{{ route('register') }}">Register</a></p>
    @endif
@endsection
