@extends('layouts.guest')
@section('title', 'Unsubscribed')

@section('content')
    <h1 class="h4 mb-3"><i class="bi bi-check-circle-fill text-success me-1"></i>You are unsubscribed</h1>
    <p class="mb-0">You will no longer receive emails from <strong>{{ $sender }}</strong>.</p>
@endsection
