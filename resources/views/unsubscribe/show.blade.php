@extends('layouts.guest')
@section('title', 'Unsubscribe')

@section('content')
    <h1 class="h4 mb-3">Unsubscribe</h1>
    @if ($already)
        <p class="mb-0">{{ $email }} is already unsubscribed from emails sent by <strong>{{ $sender }}</strong>.</p>
    @else
        <p>Stop receiving emails from <strong>{{ $sender }}</strong> at <strong>{{ $email }}</strong>?</p>
        <form method="POST" action="{{ route('unsubscribe.store', $token) }}">
            @csrf
            <button type="submit" class="btn btn-danger w-100">Yes, unsubscribe me</button>
        </form>
    @endif
@endsection
