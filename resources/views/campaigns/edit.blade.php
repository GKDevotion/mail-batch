@extends('layouts.app')
@section('title', 'Edit campaign')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <h1 class="h3 mb-3">Edit campaign</h1>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('campaigns.update', $campaign) }}" novalidate>
                        @csrf @method('PUT')
                        @include('campaigns.partials.form')
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Save changes</button>
                            <a class="btn btn-outline-secondary" href="{{ route('campaigns.show', $campaign) }}">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
