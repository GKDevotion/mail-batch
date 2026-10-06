@extends('layouts.app')
@section('title', 'SMTP configuration')

@php
    $showNew = $accounts->isEmpty() || ($errors->any() && ! old('smtp_account_id'));
@endphp

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">SMTP</li>
    </ol></nav>
    <h1 class="h3 mb-3">SMTP configuration</h1>

    @if ($campaign->smtpAccount)
        <div class="alert alert-success d-flex flex-wrap justify-content-between gap-2">
            <div>
                <strong>Current:</strong> {{ $campaign->smtpAccount->name }} —
                {{ $campaign->smtpAccount->smtp_host }}:{{ $campaign->smtpAccount->smtp_port }}
                ({{ $campaign->smtpAccount->encryption->label() }}), from {{ $campaign->smtpAccount->from_email }}
            </div>
            <a href="{{ route('smtp-accounts.edit', $campaign->smtpAccount) }}" class="alert-link">Edit account</a>
        </div>
    @endif

    @unless ($canEdit)
        <div class="alert alert-secondary">This campaign can no longer be changed.</div>
    @else
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link {{ $showNew ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#tab-saved" type="button" role="tab">Use saved account</button></li>
            <li class="nav-item"><button class="nav-link {{ $showNew ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-new" type="button" role="tab">Add new account</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade {{ $showNew ? '' : 'show active' }}" id="tab-saved" role="tabpanel">
                @if ($accounts->isEmpty())
                    <p class="text-body-secondary">You have no saved accounts yet. Use the "Add new account" tab.</p>
                @else
                    <form method="POST" action="{{ route('campaigns.smtp.update', $campaign) }}" novalidate>
                        @csrf
                        <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                            <label for="smtp_account_id" class="form-label">SMTP account</label>
                            <select id="smtp_account_id" name="smtp_account_id" required class="form-select @error('smtp_account_id') is-invalid @enderror">
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}" @selected(old('smtp_account_id', $campaign->smtp_account_id) == $acc->id)>
                                        {{ $acc->name }} — {{ $acc->smtp_host }}:{{ $acc->smtp_port }} ({{ $acc->encryption->label() }})
                                    </option>
                                @endforeach
                            </select>
                            @error('smtp_account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div></div>
                        @include('smtp-accounts.partials.test-panel', ['suffix' => 'saved'])
                        <button type="submit" class="btn btn-primary mt-3">Use this account</button>
                    </form>
                @endif
            </div>

            <div class="tab-pane fade {{ $showNew ? 'show active' : '' }}" id="tab-new" role="tabpanel">
                <form method="POST" action="{{ route('campaigns.smtp.update', $campaign) }}" novalidate>
                    @csrf
                    <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
                        @include('smtp-accounts.partials.fields')
                    </div></div>
                    @include('smtp-accounts.partials.test-panel', ['suffix' => 'new'])
                    <button type="submit" class="btn btn-primary mt-3">Save account &amp; use it</button>
                </form>
            </div>
        </div>
    @endunless
@endsection

@push('scripts') <script src="{{ asset('assets/js/smtp.js') }}"></script> @endpush
