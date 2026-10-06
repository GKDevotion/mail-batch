<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #222;">
<h2 style="margin-bottom: 8px;">SMTP test successful</h2>
<p>This message was sent by {{ config('mailbatch.name') }} to confirm that your SMTP settings work.</p>
<p style="color: #666; font-size: 13px;">Server: {{ $host }}<br>Sent: {{ now()->toDayDateTimeString() }}</p>
<p style="color: #666; font-size: 13px;">No action is needed. You can ignore this email.</p>
</body>
</html>
