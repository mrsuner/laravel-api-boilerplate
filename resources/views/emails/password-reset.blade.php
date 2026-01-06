<!DOCTYPE html>
<html>
<head>
    <title>Reset Your Password</title>
</head>
<body>
    <h1>Hello {{ $userName }},</h1>
    <p>You requested to reset your password. Click the link below:</p>
    <p><a href="{{ $resetUrl }}">Reset Password</a></p>
    <p>This link will expire in {{ config('boilerplate.auth.password_reset_expiry_minutes', 60) }} minutes.</p>
    <p>If you did not request a password reset, please ignore this email.</p>
</body>
</html>
