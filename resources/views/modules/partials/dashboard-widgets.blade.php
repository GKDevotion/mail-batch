@php($moduleWidgets = app(\App\Support\Modules\WidgetRegistry::class)->for(auth()->user()))
@if (count($moduleWidgets))
    <div class="row g-3 mt-0">
        @foreach ($moduleWidgets as $widget)
            <div class="{{ $widget['col'] }}">@include($widget['view'])</div>
        @endforeach
    </div>
@endif
