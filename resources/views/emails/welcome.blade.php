<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
</head>
<body>
    <h1>Welcome to {{ config('app.name') }}!</h1>
    <p>Hi {{ $user->name }},</p>
    <p>Thank you for creating an account with us. We're excited to have you on board!</p>
    <p>If you have any questions, feel free to reach out to our support team.</p>
    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
</body>
</html>
