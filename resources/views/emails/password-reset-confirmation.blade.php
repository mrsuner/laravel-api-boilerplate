<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Confirmation</title>
</head>
<body>
    <h1>Password Successfully Reset</h1>
    <p>Hi {{ $user->name }},</p>
    <p>Your password has been successfully reset at {{ now()->format('Y-m-d H:i:s') }} UTC.</p>
    <p>If you did not make this change, please contact our support team immediately.</p>
    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
</body>
</html>
