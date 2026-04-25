<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f4f4f4;
            border-radius: 5px;
            padding: 30px;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            padding: 14px 28px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
        .link-fallback {
            word-break: break-all;
            font-size: 12px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Reset Request</h2>
        <p>We received a request to reset the password for your CheckinMe account.</p>
        <p>Click the button below to set a new password. This link will expire in <strong>{{ $expiryMinutes }} minutes</strong>.</p>

        <a href="{{ $resetLink }}" class="btn">Reset My Password</a>

        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p class="link-fallback">{{ $resetLink }}</p>

        <p class="warning">If you did not request a password reset, please ignore this email. Your password will not change.</p>

        <hr>
        <p style="font-size: 12px; color: #666;">
            This is an automated email. Please do not reply.
        </p>
    </div>
</body>
</html>
