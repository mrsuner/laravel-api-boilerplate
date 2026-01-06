<!DOCTYPE html>
<html>
<head>
    <title>Login Notification</title>
</head>
<body>
    <h1>New Login Detected</h1>
    <p>Hi {{ $user->name }},</p>
    <p>We detected a new login to your account at {{ now()->format('Y-m-d H:i:s') }} UTC.</p>
    <p>If this was you, you can safely ignore this email.</p>
    <p>If you did not log in, please secure your account immediately by changing your password.</p>
    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
</body>
</html>
