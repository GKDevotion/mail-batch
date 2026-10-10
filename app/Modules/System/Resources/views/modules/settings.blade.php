@extends('layouts.app')
@section('title', $module->name().' settings')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('admin.modules.index') }}">Modules</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $module->name() }}</li>
    </ol></nav>
    <h1 class="h3 mb-3">{{ $module->name() }} settings</h1>

    <div class="row"><div class="col-12 col-lg-8 col-xl-6">
        <form method="POST" action="{{ route('admin.modules.settings.update', $module->key()) }}" novalidate>
            @csrf @method('PUT')
            <div class="card"><div class="card-body p-4">
                @foreach ($definitions as $name => $def)
                    @php($type = $def['type'] ?? 'text')
                    @if ($type === 'bool')
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $values[$name]))>
                            <label class="form-check-label" for="{{ $name }}">{{ $def['label'] }}</label>
                        </div>
                    @else
                        <div class="mb-3">
                            <label for="{{ $name }}" class="form-label">{{ $def['label'] }}</label>
                            @if ($type === 'select')
                                <select id="{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                    @foreach ($def['options'] ?? [] as $value => $label) <option value="{{ $value }}" @selected((string) old($name, $values[$name]) === (string) $value)>{{ $label }}</option> @endforeach
                                </select>
                            @else
                                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type === 'number' ? 'number' : 'text' }}" value="{{ old($name, $values[$name]) }}"
                                       class="form-control @error($name) is-invalid @enderror">
                            @endif
                            @isset($def['help']) <div class="form-text">{{ $def['help'] }}</div> @endisset
                            @error($name) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    @endif
                @endforeach
                <button class="btn btn-primary">Save settings</button>
            </div></div>
        </form>
    </div></div>
@endsection
