SMTP test successful

This message was sent by {{ config('mailbatch.name') }} to confirm that your SMTP settings work.

Server: {{ $host }}
Sent: {{ now()->toDayDateTimeString() }}

No action is needed. You can ignore this email.
