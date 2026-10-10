@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    @php
        $first = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
        $totalRecipients = max($stats['total_recipients'], 1);
        $pct = fn (int $n) => (int) round($n / $totalRecipients * 100);
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-body-secondary mb-0">Welcome back, {{ $first }}. Here is how your campaigns are doing.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('campaigns.create') }}"><i class="bi bi-plus-lg me-1"></i>New campaign</a>
    </div>

    <div id="dashboard-stats" class="row g-3 mb-3" data-url="{{ route('dashboard.stats') }}">
        <x-stat-card stat="total_campaigns" label="Total Campaigns" :value="$stats['total_campaigns']" icon="megaphone" color="#5b47f5" :series="$series['campaigns']" />
        <x-stat-card stat="total_recipients" label="Total Recipients" :value="$stats['total_recipients']" icon="people" color="#0ea5e9" :series="$series['recipients']" />
        <x-stat-card stat="sent" label="Sent" :value="$stats['sent']" icon="send-check" color="#16a34a" :series="$series['sent']" />
        <x-stat-card stat="failed" label="Failed" :value="$stats['failed']" icon="x-octagon" color="#ef4444" :series="$series['failed']" />
        <x-stat-card stat="pending" label="Pending" :value="$stats['pending']" icon="hourglass-split" color="#f97316" :pct="$pct($stats['pending'])" />
        <x-stat-card stat="skipped" label="Skipped" :value="$stats['skipped']" icon="skip-forward" color="#eab308" :pct="$pct($stats['skipped'])" />
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Sending activity</span><span class="small fw-normal text-body-secondary">Last 14 days</span>
                </div>
                <div class="card-body">
                    <x-bar-chart :labels="$series['labels']" :a="$series['sent']" :b="$series['failed']" a-label="Sent" b-label="Failed" />
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">Campaign status</div>
                <div class="card-body">
                    <x-donut :segments="[
                        ['label' => 'Sent', 'value' => $stats['sent'], 'color' => '#16a34a'],
                        ['label' => 'Failed', 'value' => $stats['failed'], 'color' => '#ef4444'],
                        ['label' => 'Pending', 'value' => $stats['pending'], 'color' => '#f97316'],
                        ['label' => 'Skipped', 'value' => $stats['skipped'], 'color' => '#eab308'],
                    ]" :center-value="number_format($stats['total_recipients'])" center-label="recipients" />
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Recent campaigns</span>
            <a href="{{ route('campaigns.index') }}" class="small fw-normal">View all</a>
        </div>
        @include('campaigns.partials.table', ['campaigns' => $recentCampaigns, 'compact' => true])
    </div>

    @include('modules.partials.dashboard-widgets')
@endsection
