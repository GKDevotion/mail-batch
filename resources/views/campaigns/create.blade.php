@extends('layouts.app')
@section('title', 'New campaign')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <h1 class="h3 mb-3">New campaign</h1>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('campaigns.store') }}" novalidate>
                        @csrf
                        @include('campaigns.partials.form')
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Create campaign</button>
                            <a class="btn btn-outline-secondary" href="{{ route('campaigns.index') }}">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
