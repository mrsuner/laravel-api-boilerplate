<!DOCTYPE html>
<html>
<head>
    <title>Logout Notification</title>
</head>
<body>
    <h1>You Have Been Logged Out</h1>
    <p>Hi {{ $user->name }},</p>
    <p>You have been logged out of your account at {{ now()->format('Y-m-d H:i:s') }} UTC.</p>
    <p>If this was not you, please secure your account immediately by changing your password.</p>
    <p>Best regards,<br>The {{ config('app.name') }} Team</p>
</body>
</html>
