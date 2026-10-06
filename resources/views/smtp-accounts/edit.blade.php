@extends('layouts.app')
@section('title', 'Edit SMTP account')

@section('content')
    <div class="row justify-content-center"><div class="col-12 col-xl-9">
        <h1 class="h3 mb-3">Edit SMTP account</h1>
        <form method="POST" action="{{ route('smtp-accounts.update', $account) }}" novalidate>
            @csrf @method('PUT')
            <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                @include('smtp-accounts.partials.fields')
            </div></div>
            @include('smtp-accounts.partials.test-panel', ['suffix' => 'edit', 'accountId' => $account->id])
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('smtp-accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection

@push('scripts') <script src="{{ asset('assets/js/smtp.js') }}"></script> @endpush
