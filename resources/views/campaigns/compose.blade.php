@extends('layouts.app')
@section('title', 'Email composer')

@section('content')
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="{{ route('campaigns.index') }}">Campaigns</a></li>
        <li class="breadcrumb-item"><a href="{{ route('campaigns.show', $campaign) }}">{{ \Illuminate\Support\Str::limit($campaign->name, 40) }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Compose</li>
    </ol></nav>
    <h1 class="h3 mb-3">Email composer</h1>

    @unless ($canEdit)
        <div class="alert alert-secondary">This campaign can no longer be changed.</div>
    @endunless

    <form method="POST" action="{{ route('campaigns.compose.update', $campaign) }}" id="composeForm" novalidate
          data-preview-url="{{ route('campaigns.compose.preview', $campaign) }}"
          data-test-url="{{ route('campaigns.test-email', $campaign) }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input id="subject" type="text" name="subject" maxlength="255" required value="{{ $subject }}" @disabled(! $canEdit)
                                   class="form-control @error('subject') is-invalid @enderror">
                            @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-2">
                            <div class="form-label mb-1">Variables <span class="text-body-secondary small">— click to insert at the cursor</span></div>
                            <div class="d-flex flex-wrap gap-1" id="variableChips">
                                @foreach ($variables as $name => $info)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-insert-var="{{ $name }}"
                                            title="{{ $info['label'] }}{{ $info['header'] ? ' ← column "'.$info['header'].'"' : '' }}" @disabled(! $canEdit)>
                                        &#123;&#123;{{ $name }}&#125;&#125;
                                    </button>
                                @endforeach
                            </div>
                            <div class="form-text">
                                Fallback text: <code>&#123;&#123;name|there&#125;&#125;</code> is used when the value is empty.
                                For links use <code>&#123;&#123;website_url&#125;&#125;</code> (adds https://).
                            </div>
                        </div>

                        <ul class="nav nav-tabs mt-3" role="tablist">
                            <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-html" role="tab">HTML body</button></li>
                            <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-text" role="tab">Plain text fallback</button></li>
                        </ul>
                        <div class="tab-content border border-top-0 rounded-bottom p-3">
                            <div class="tab-pane fade show active" id="tab-html" role="tabpanel">
                                <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Formatting">
                                    <button type="button" class="btn btn-outline-secondary fw-bold" data-wrap data-open="<b>" data-close="</b>" @disabled(! $canEdit)>B</button>
                                    <button type="button" class="btn btn-outline-secondary fst-italic" data-wrap data-open="<i>" data-close="</i>" @disabled(! $canEdit)>I</button>
                                    <button type="button" class="btn btn-outline-secondary" data-wrap data-open="<p>" data-close="</p>" @disabled(! $canEdit)>&para;</button>
                                    <button type="button" class="btn btn-outline-secondary" data-wrap data-open='<a href="https://">' data-close="</a>" @disabled(! $canEdit)>Link</button>
                                    <button type="button" class="btn btn-outline-secondary" data-wrap data-open="<br>" data-close="" @disabled(! $canEdit)>&lt;br&gt;</button>
                                </div>
                                <textarea id="body_html" name="body_html" rows="12" required spellcheck="false" @disabled(! $canEdit)
                                          class="form-control font-monospace small @error('body_html') is-invalid @enderror">{{ $bodyHtml }}</textarea>
                                @error('body_html') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Scripts, <code>&lt;style&gt;</code> blocks, event handlers and unsafe links are removed on save. Use inline styles.
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tab-text" role="tabpanel">
                                <textarea id="body_text" name="body_text" rows="10" @disabled(! $canEdit)
                                          class="form-control font-monospace small @error('body_text') is-invalid @enderror"
                                          placeholder="Leave empty to generate it automatically from the HTML when sending.">{{ $bodyText }}</textarea>
                                @error('body_text') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="genText" @disabled(! $canEdit)>Generate from HTML</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="fw-semibold mb-2"><i class="bi bi-send me-1"></i>Send a test email</div>
                        @if ($campaign->smtpAccount)
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-7">
                                    <label for="test_email" class="form-label small mb-1">Test address</label>
                                    <input id="test_email" type="email" name="test_email" class="form-control" value="{{ auth()->user()->email }}" maxlength="254">
                                </div>
                                <div class="col-12 col-md-5">
                                    <button type="button" id="sendTest" class="btn btn-outline-primary w-100" @disabled(! $canEdit)>Send test</button>
                                </div>
                            </div>
                            <div class="form-text">Sent with <strong>{{ $campaign->smtpAccount->name }}</strong>, using the selected sample recipient's values.</div>
                            <div class="mt-2" id="testResult" role="status" aria-live="polite"></div>
                        @else
                            <p class="mb-0 text-body-secondary">Select an SMTP account first to send a test email.
                                <a href="{{ route('campaigns.smtp', $campaign) }}">SMTP step</a></p>
                        @endif
                    </div>
                </div>

                @if ($canEdit)
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Save template</button>
                        <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                @endif
            </div>

            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm position-sticky" style="top: 80px;">
                    <div class="card-header bg-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="fw-semibold">Live preview</span>
                        <select id="recipient_id" name="recipient_id" class="form-select form-select-sm w-auto" aria-label="Sample recipient">
                            <option value="">First eligible recipient</option>
                            @foreach ($samples as $s)
                                <option value="{{ $s->id }}">Row {{ $s->row_number }} — {{ \Illuminate\Support\Str::limit($s->name ?: $s->email, 28) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="card-body">
                        <div class="small text-body-secondary">Subject</div>
                        <div class="fw-semibold mb-2 text-break" id="previewSubject">&nbsp;</div>
                        <div id="previewWarnings"></div>
                        <iframe id="previewFrame" sandbox title="Email preview" class="w-100 border rounded bg-white" style="height: 420px;"></iframe>
                        <details class="mt-2">
                            <summary class="small text-body-secondary">Plain text version</summary>
                            <pre class="small mb-0 mt-2 text-wrap" id="previewText"></pre>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts') <script src="{{ asset('assets/js/composer.js') }}"></script> @endpush
