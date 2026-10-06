@extends('layouts.app')
@section('title', 'System settings')

@section('content')
    <h1 class="h3 mb-3">Administration</h1>
    @include('admin.partials.nav')

    <div class="row"><div class="col-12 col-lg-8 col-xl-6">
        <form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
            @csrf @method('PUT')
            <div class="card border-0 shadow-sm"><div class="card-body p-4">
                <div class="mb-3">
                    <label for="max_batch_size" class="form-label">Batch size limit</label>
                    <input id="max_batch_size" type="number" name="max_batch_size" min="1" max="{{ $hardMax }}" required value="{{ old('max_batch_size', $values['max_batch_size']) }}"
                           class="form-control @error('max_batch_size') is-invalid @enderror">
                    <div class="form-text">Emails per "Start sending" click. Between 1 and {{ $hardMax }} (server ceiling).</div>
                    @error('max_batch_size') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="default_daily_limit" class="form-label">Default daily limit per user</label>
                    <input id="default_daily_limit" type="number" name="default_daily_limit" min="0" required value="{{ old('default_daily_limit', $values['default_daily_limit']) }}"
                           class="form-control @error('default_daily_limit') is-invalid @enderror">
                    <div class="form-text">Used for users without a personal limit (set personal limits on the Users page). 0 blocks sending.</div>
                    @error('default_daily_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="max_retries" class="form-label">Maximum failed attempts per recipient</label>
                    <input id="max_retries" type="number" name="max_retries" min="1" max="10" required value="{{ old('max_retries', $values['max_retries']) }}"
                           class="form-control @error('max_retries') is-invalid @enderror">
                    <div class="form-text">Prevents endless retrying. Includes the first attempt.</div>
                    @error('max_retries') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-check form-switch mb-4">
                    <input type="hidden" name="force_unsubscribe" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="force_unsubscribe" name="force_unsubscribe" value="1" @checked(old('force_unsubscribe', $values['force_unsubscribe']))>
                    <label class="form-check-label" for="force_unsubscribe">Always add an unsubscribe link, even if the campaign owner turned it off</label>
                </div>
                <button class="btn btn-primary">Save settings</button>
            </div></div>
        </form>
    </div></div>
@endsection
