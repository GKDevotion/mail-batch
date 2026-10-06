@extends('layouts.guest')
@section('title', 'Register')

@section('content')
    <h1 class="h4 mb-3">Create account</h1>
    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        @foreach ([
            ['name', 'Name', 'text', 'name'],
            ['email', 'Email', 'email', 'username'],
            ['password', 'Password', 'password', 'new-password'],
            ['password_confirmation', 'Confirm password', 'password', 'new-password'],
        ] as [$field, $label, $type, $auto])
            <div class="mb-3">
                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" autocomplete="{{ $auto }}" required
                       @if ($type !== 'password') value="{{ old($field) }}" @endif
                       class="form-control @error($field) is-invalid @enderror">
                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endforeach
        <div class="form-text mb-3">Minimum 10 characters with upper/lower case letters and a number.</div>
        <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>
    <p class="text-center small mt-3 mb-0">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
