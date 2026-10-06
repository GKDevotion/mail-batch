<?php

return [
    'name' => 'MailBatch',

    // Self-registration is disabled by default; admins create users.
    'registration_enabled' => (bool) env('MAILBATCH_REGISTRATION_ENABLED', false),
    'tagline' => 'Smart Excel to Email Automation',

    // Hard ceiling for one "Start Sending" action / one ProcessCampaignBatchJob.
    'max_batch_size' => (int) env('MAILBATCH_MAX_BATCH_SIZE', 50),

    // Absolute ceiling an administrator can configure under Admin > Settings.
    'hard_max_batch_size' => (int) env('MAILBATCH_HARD_MAX_BATCH_SIZE', 50),

    // Applied when a user has no personal daily_send_limit. Admin can override per user.
    'default_daily_limit' => (int) env('MAILBATCH_DEFAULT_DAILY_LIMIT', 500),

    // A failed recipient can be retried at most this many times.
    'max_retries' => (int) env('MAILBATCH_MAX_RETRIES', 3),

    'upload' => [
        'disk' => env('MAILBATCH_UPLOAD_DISK', 'local'),   // private disk, never public
        'directory' => 'mailbatch/uploads',
        'exports_directory' => 'mailbatch/exports',
        'extensions' => ['xls', 'xlsx'],
        'mimes' => [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',                 // some servers detect xlsx as a zip container
            'application/x-zip-compressed',
            'application/CDFV2',               // legacy .xls detected via libmagic
            'application/x-ole-storage',
            'application/octet-stream',
        ],
        'max_kb' => (int) env('MAILBATCH_UPLOAD_MAX_KB', 5120),
        'max_rows' => (int) env('MAILBATCH_MAX_ROWS', 5000),
        'preview_rows' => 10,
    ],

    'smtp' => [
        'encryptions' => ['ssl', 'tls'],
        'suggested_ports' => ['ssl' => 465, 'tls' => 587],
        'timeout_seconds' => 15,
        // SSRF guard: keep false in production. true only for local dev (e.g. Mailpit on 127.0.0.1).
        'allow_private_hosts' => (bool) env('MAILBATCH_SMTP_ALLOW_PRIVATE_HOSTS', false),
    ],

    // Variables usable in templates in addition to dynamic mapped/Excel columns.
    'reserved_variables' => ['name', 'email', 'website', 'contact', 'date', 'reply', 'note'],

    'template' => [
        'max_html_length' => 100000,   // characters
        'max_text_length' => 50000,
    ],

    'unsubscribe' => [
        'enabled' => (bool) env('MAILBATCH_UNSUBSCRIBE_ENABLED', true),
        'force' => false,   // set by Admin > Settings: add the link to every campaign
    ],

    'queue' => [
        'name' => env('MAILBATCH_QUEUE', 'mailbatch'),   // prefix: every campaign uses its own queue "{name}-c{id}"
        'job_tries' => 1,          // duplicate protection: retries are explicit user actions
        'job_timeout' => 60,
        'delay_between_emails_seconds' => (int) env('MAILBATCH_DELAY_SECONDS', 2),
        'stale_sending_minutes' => 15,   // an attempt stuck this long is marked failed (result unknown)
        'stale_queued_minutes' => 30,    // claimed-but-never-run rows are released after this long
    ],

    'worker' => [
        // Browser-driven worker (no artisan / Supervisor / cron): seconds one /campaigns/{id}/work request
        // may keep sending. Keep it well below PHP max_execution_time (30 s on many hosts).
        'request_seconds' => (int) env('MAILBATCH_WORK_SECONDS', 15),
    ],

    'security' => [
        // enforce | report (Content-Security-Policy-Report-Only, for testing) | off
        'csp' => env('MAILBATCH_CSP', 'enforce'),
    ],

    'retention' => [
        'email_logs_days' => (int) env('MAILBATCH_LOG_RETENTION_DAYS', 180),
        'daily_counts_days' => 90,
    ],

    'admin' => [
        'name' => env('MAILBATCH_ADMIN_NAME', 'Administrator'),
        'email' => env('MAILBATCH_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('MAILBATCH_ADMIN_PASSWORD'), // if null, seeder generates one
    ],
];
