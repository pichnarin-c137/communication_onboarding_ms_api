<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
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
        .otp-code {
            background-color: #007bff;
            color: white;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            letter-spacing: 5px;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>OTP Verification Code</h2>
        <p>Your one-time password (OTP) for login is:</p>

        <div class="otp-code">{{ $otp }}</div>

        <p>This OTP will expire in <strong>{{ $expiryMinutes }} minutes</strong>.</p>

        <p class="warning">Do not share this code with anyone.</p>

        <p>If you did not request this OTP, please ignore this email.</p>

        <hr>
        <p style="font-size: 12px; color: #666;">
            This is an automated email. Please do not reply.
        </p>
    </div>
</body>
</html>
