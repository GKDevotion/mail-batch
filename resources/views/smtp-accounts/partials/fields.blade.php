@php($a = $account ?? null)
<div class="row g-3">
    <div class="col-12">
        <label for="name" class="form-label">Account name</label>
        <input id="name" type="text" name="name" maxlength="100" required value="{{ old('name', $a?->name) }}"
               class="form-control smtp-field @error('name') is-invalid @enderror" placeholder="e.g. Company mailbox">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-8">
        <label for="smtp_host" class="form-label">SMTP host</label>
        <input id="smtp_host" type="text" name="smtp_host" maxlength="253" required autocomplete="off" value="{{ old('smtp_host', $a?->smtp_host) }}"
               class="form-control smtp-field @error('smtp_host') is-invalid @enderror" placeholder="smtp.example.com">
        @error('smtp_host') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-6 col-md-4">
        <label for="smtp_port" class="form-label">SMTP port</label>
        <input id="smtp_port" type="number" name="smtp_port" min="1" max="65535" required value="{{ old('smtp_port', $a?->smtp_port ?? 587) }}"
               class="form-control smtp-field @error('smtp_port') is-invalid @enderror">
        @error('smtp_port') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-6 col-md-4">
        <label for="encryption" class="form-label">Encryption</label>
        <select id="encryption" name="encryption" class="form-select smtp-field @error('encryption') is-invalid @enderror">
            @foreach (config('mailbatch.smtp.encryptions') as $enc)
                <option value="{{ $enc }}" @selected(old('encryption', $a?->encryption?->value ?? 'tls') === $enc)>{{ strtoupper($enc) }}</option>
            @endforeach
        </select>
        @error('encryption') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="smtp_username" class="form-label">SMTP username</label>
        <input id="smtp_username" type="text" name="smtp_username" maxlength="255" required autocomplete="off" value="{{ old('smtp_username', $a?->smtp_username) }}"
               class="form-control smtp-field @error('smtp_username') is-invalid @enderror">
        @error('smtp_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-4">
        <label for="smtp_password" class="form-label">SMTP password</label>
        {{-- Never pre-filled. The saved password is encrypted and cannot be displayed. --}}
        <input id="smtp_password" type="password" name="smtp_password" maxlength="500" autocomplete="new-password"
               @required(! $a) placeholder="{{ $a ? 'Saved (hidden) — leave blank to keep' : '' }}"
               class="form-control smtp-field @error('smtp_password') is-invalid @enderror">
        @error('smtp_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="from_name" class="form-label">From name</label>
        <input id="from_name" type="text" name="from_name" maxlength="100" required value="{{ old('from_name', $a?->from_name) }}"
               class="form-control smtp-field @error('from_name') is-invalid @enderror">
        @error('from_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="from_email" class="form-label">From email</label>
        <input id="from_email" type="email" name="from_email" maxlength="254" required value="{{ old('from_email', $a?->from_email) }}"
               class="form-control smtp-field @error('from_email') is-invalid @enderror">
        @error('from_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<div class="form-text mt-2">
    Common setups: SSL = port 465, TLS (STARTTLS) = port 587. Providers differ, so use what yours documents.
    The password is encrypted before it is stored and is never shown again.
</div>
