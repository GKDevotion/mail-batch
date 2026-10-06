# MailBatch — Phase 1: Architecture, Schema, Models, Routes

Tagline: **Smart Excel to Email Automation** · Laravel 12 · PHP 8.3+ · MySQL

## Install (fresh project)

```bash
composer create-project laravel/laravel mailbatch
cd mailbatch
composer require maatwebsite/excel
# copy this package's folders (app/, config/, database/, routes/) over the project
# merge .env.mailbatch.example into .env and set DB credentials
php artisan migrate
php artisan db:seed
```

> `routes/web.php` references controllers, the `active` / `admin` middleware and `routes/auth.php`
> delivered in Phases 2-8. Run `route:list` only after Phase 2. Migrations, models, factories
> and seeders work now.

## Folder structure (target, by phase)

```
app/
├── Enums/            CampaignStatus, UserRole, SmtpEncryption, RecipientState       [P1]
├── Models/           User, SmtpAccount, Campaign, CampaignRecipient,
│                     EmailLog, SystemSetting, Unsubscribe                           [P1]
├── Http/
│   ├── Controllers/  Dashboard, Campaign*, SmtpAccount, Unsubscribe, Admin/*        [P2-P8]
│   ├── Middleware/   EnsureUserIsActive (active), EnsureUserIsAdmin (admin)         [P2]
│   └── Requests/     StoreCampaignRequest, MappingRequest, SmtpRequest, ...         [P2-P6]
├── Services/
│   ├── ExcelImportService, ExcelColumnMappingService                                [P3]
│   ├── SmtpConnectionService                                                        [P4]
│   ├── EmailTemplateService                                                         [P5]
│   ├── CampaignService, EmailSendingService                                         [P6]
│   └── CampaignProgressService                                                      [P7]
├── Jobs/             ProcessCampaignBatchJob, SendCampaignEmailJob                  [P6]
├── Exports/          CampaignResultsExport (Maatwebsite)                            [P7]
├── Imports/          CampaignRecipientsImport                                       [P3]
└── Policies/         CampaignPolicy, SmtpAccountPolicy                              [P2]
config/mailbatch.php                                                                 [P1]
database/{migrations,factories,seeders}                                              [P1]
resources/views/{layouts,components,campaigns,admin,...}                             [P2+]
public/assets/{css,js}   app.js (jQuery/AJAX, no NPM build)                          [P2+]
```

## Database schema

| Table | Purpose / key design points |
|---|---|
| `users` | + `role` (admin/user), `is_active`, `daily_send_limit` (NULL = system default). Not mass-assignable. |
| `smtp_accounts` | `smtp_password_encrypted` uses Laravel's `encrypted` cast and is `$hidden`. Host/port/encryption fully custom (ssl/tls). |
| `campaigns` | Spec columns + `smtp_account_id`, `excel_path` (private disk), `excel_headers`, `column_mapping` (JSON), invalid/duplicate/valid counters, `batch_size`, `include_unsubscribe`, `sender_identification`. |
| `campaign_recipients` | Spec columns + `row_number`, `is_valid_email`, `skip_reason`, `retry_count`, `last_attempt_at`, `dedupe_key`. `metadata` JSON holds the **entire original row** so export preserves every Excel column. |
| `email_logs` | One row per attempt (`sent`/`failed`/`test`); `recipient_id` NULL for test emails; sanitised error/response only. |
| `system_settings` | Admin-editable key/value (cached). |
| `unsubscribes` | Per-sender opt-outs; unique `(user_id, email)`; opaque token for the public link. |

### Recipient state rules

| Condition | Displayed as | Eligible to send? |
|---|---|---|
| `skip_reason` set (already marked sent in Excel, invalid, duplicate, unsubscribed) | Skipped | No |
| `status = 1` or `sent_at` set | Sent | **Never** |
| `status` 0/NULL with `error_message` | Failed | Yes, while `retry_count < max_retries` |
| `status` 0/NULL, no error | Pending | Yes |

Excel status `1` is imported as `status=1, skip_reason='already_marked_sent'`; `0`, empty or NULL become `status=0`.

### Duplicate protection layers

1. Import: first occurrence of an email gets `dedupe_key = lower(email)`; later duplicates keep `dedupe_key = NULL` and `skip_reason='duplicate'`. `UNIQUE(campaign_id, dedupe_key)` enforces it in the database.
2. `SendCampaignEmailJob` (Phase 6) re-reads the row in a transaction with `lockForUpdate()` and aborts if `status = 1` or `sent_at` is set; queue `tries = 1`.
3. Retry is an explicit user action capped by `retry_count`.

## Routes

Wizard: `create → preview → mapping → smtp → compose → confirm → progress`. Everything is behind `auth`
and (Phase 2) policy-based ownership checks; admin lives under `/admin/*`. Two small adjustments to the brief:
`POST /campaigns` (`campaigns.store`) replaces `/campaigns/store`, and progress is split into a page
(`/progress`) and a JSON poll endpoint (`/progress/status`).

## Roadmap

2 Auth, dashboard, campaign CRUD · 3 Excel upload/preview/mapping · 4 SMTP + test · 5 Composer/variables ·
6 Queue, sending, batching · 7 Progress, logs, retry, export · 8 Security, rate limits, responsive polish.
