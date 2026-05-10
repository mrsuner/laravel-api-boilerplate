<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email</title>
</head>
<body>
    <h1>Hello {{ $userName }},</h1>
    <p>Welcome! Please confirm your email address by clicking the link below:</p>
    <p><a href="{{ $verificationUrl }}">Verify Email</a></p>
    <p>This link will expire in {{ config('boilerplate.auth.email_verification.expire_minutes', 60) }} minutes.</p>
    <p>If you did not create an account, please ignore this email.</p>
</body>
</html>
