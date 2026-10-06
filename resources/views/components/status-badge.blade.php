@props(['status'])
<span class="badge text-bg-{{ $status->badgeClass() }}">{{ $status->label() }}</span>
