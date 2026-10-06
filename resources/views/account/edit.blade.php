@extends('layouts.app')
@section('title', 'Account')

@section('content')
    <div class="row justify-content-center"><div class="col-12 col-lg-6 col-xl-5">
        <h1 class="h3 mb-3">Account</h1>
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="fw-semibold">{{ auth()->user()->name }}</div>
            <div class="text-body-secondary">{{ auth()->user()->email }}</div>
        </div></div>

        <div class="card border-0 shadow-sm"><div class="card-header bg-body fw-semibold">Change password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.password') }}" novalidate>
                    @csrf @method('PUT')
                    @foreach ([['current_password', 'Current password', 'current-password'], ['password', 'New password', 'new-password'], ['password_confirmation', 'Confirm new password', 'new-password']] as [$f, $label, $auto])
                        <div class="mb-3">
                            <label for="{{ $f }}" class="form-label">{{ $label }}</label>
                            <input id="{{ $f }}" type="password" name="{{ $f }}" required autocomplete="{{ $auto }}" class="form-control @error($f) is-invalid @enderror">
                            @error($f) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                    <div class="form-text mb-3">Minimum 10 characters with upper and lower case letters and a number.</div>
                    <button class="btn btn-primary">Update password</button>
                </form>
            </div>
        </div>
    </div></div>
@endsection
