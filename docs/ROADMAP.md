# Roadmap: feature list → modules, in build order

Order follows dependencies: each step only needs what is above it.

| Step | Module (key) | Covers (your list) | Needs |
|---|---|---|---|
| **0** ✅ | Module framework, **System**, **Access**, **Activity Log** | 17 roles & permissions · 18 activity logs · module manager | – |
| **1** | **Legacy adoption**: wrap Campaigns / SMTP / Users in permissions | 17 enforce permissions on what exists | access |
| **2** | **Contacts** (`contacts`): contacts, lists, tags, custom fields, search, export, **import wizard** (7 steps), **validation** (syntax, empty, duplicates, domain/MX, disposable), **suppression, unsubscribe, bounce lists** | 4 · 12 · 13 · 14 | access |
| **3** | **Templates** (`templates`): library, categories, duplicate, preview, variables, **conditional content** | 10 · 11 | access |
| **4** | **Editor** (`editor`): drag-and-drop blocks (text, image, button, divider, columns, social, header/footer, custom HTML), reusable blocks, responsive output | 3 | templates |
| **5** | **Campaigns v2** (`campaigns2`): audience from lists, from/reply-to, CC/BCC, save draft, duplicate, cancel, history, test email | 2 | contacts, templates |
| **6** | **SMTP Pool** (`smtp_pool`): many accounts, daily/hourly quota, priority, health, **automatic failover** | 6 | access |
| **7** | **Sending engine v2** (`sending`): batch delay, concurrent workers, exponential retry, throttling per provider, pause/resume, ETA, queue dashboard | 5 · 7 | smtp_pool, campaigns2 |
| **8** | **Scheduling** (`scheduling`): send now/later, timezone, recurring, expiry, auto-cancel | 15 | campaigns2, sending |
| **9** | **Tracking** (`tracking`): sent/delivered/opened/clicked/bounced/complained/unsubscribed events, manage-preferences page | 8 · 14 | contacts, sending |
| **10** | **Analytics** (`analytics`): per-campaign page, charts, devices, browsers, geo, links, hour/day, top recipients; dashboard v2 | 1 · 9 | tracking |
| **11** | **Automation** (`automation`): triggers, wait, condition (opened?/clicked?), actions | 16 | contacts, tracking, scheduling |
| **12** | **Security & settings** (`security`): 2FA (TOTP), password policy, session timeout, API tokens, general/email/queue settings, logo | 19 · 20 | access |

## Coverage today (before step 1)

| # | Area | State |
|---|---|---|
| 1 | Admin dashboard | partial: sent / failed / pending / skipped, progress, recent campaigns, recent activity. Open/click/bounce rates need step 9 |
| 2 | Campaign management | partial: create, name/subject, HTML + text, preview, test email, pause/resume, history. Missing: reply-to, CC/BCC, duplicate, cancel, schedule |
| 3 | Email editor | textarea + toolbar. Drag-and-drop = step 4 |
| 4 | Contacts | per-campaign recipients only. Step 2 |
| 5 | Sending engine | partial: batches, retry cap, pause/resume, progress, daily limit. Missing: delay, concurrency, backoff, ETA |
| 6 | Multiple SMTP | partial: many accounts per user, test, enable/disable. Missing: quota, priority, failover (step 6) |
| 7 | Queue dashboard | partial (progress + logs). Step 7 |
| 8–9 | Tracking, analytics | none (steps 9, 10) |
| 10 | Templates | none (step 3) |
| 11 | Personalisation | partial: variables + fallback. Conditionals in step 3 |
| 12 | Import wizard | partial: upload, preview, map, validate (per campaign). Contacts import = step 2 |
| 13 | Email validation | partial: syntax, empty, duplicates, unsubscribed. Domain/MX, disposable, bounced = step 2 |
| 14 | Unsubscribe | partial: link, page, list. Reasons, preferences, resubscribe UI = steps 2 and 9 |
| 15–16 | Scheduling, automation | none (steps 8, 11) |
| 17 | Roles & permissions | **done** (matrix, roles, assignment); enforced inside new modules, adopted by old features in step 1 |
| 18 | Activity logs | **done** (user, action, IP, date, browser/device, filters, retention) |
| 19 | System settings | partial: batch size, retries, daily limit. Rest = step 12 |
| 20 | Security | have: CSRF, XSS escaping/sanitising, SQL injection safe queries, rate limiting, hashing, encrypted SMTP, audit log, CSP. Missing: 2FA, API auth, session timeout (step 12) |

## Things your hosting changes

- **No cron**: schedules and automations need a "tick". Plan: reuse the browser-driven worker (`worker.js`) to also run due schedules, plus an optional cron line for real servers.
- **Open/click tracking** only works when recipients can reach your server. On `127.0.0.1` the pixel and tracked links cannot be opened by real recipients; deploy on a public URL (HTTPS) first.
- **Bounces / complaints** arrive by mailbox (IMAP) or provider webhook (SES, Mailgun, SendGrid). Step 9 adds webhook endpoints first; IMAP polling is optional.
- **Geographic stats** need an IP→country database (free MaxMind GeoLite2 file, license key required).
- **Concurrent workers** need real processes; on shared hosting expect 1 worker. The engine will queue per campaign and rate-limit per SMTP account instead.
