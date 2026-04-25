<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rate Your Onboarding Experience</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; color: #333; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { background: #1a56db; color: #fff; padding: 32px 40px; }
        .header h1 { margin: 0; font-size: 22px; }
        .body { padding: 32px 40px; }
        .body p { line-height: 1.6; }
        .cta { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; padding: 14px 36px; background: #1a56db; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold; }
        .notice { font-size: 13px; color: #888; margin-top: 24px; border-top: 1px solid #eee; padding-top: 16px; }
        .footer { background: #f9f9f9; padding: 20px 40px; font-size: 12px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>How Was Your Onboarding?</h1>
    </div>
    <div class="body">
        <p>Dear <strong>{{ $companyName }}</strong>,</p>

        <p>
            Thank you for completing your onboarding session with us (Reference: <strong>{{ $onboarding->request_code }}</strong>).
            We value your experience and would appreciate a moment of your time to share your feedback.
        </p>

        <p>Please click the button below to rate your experience. It only takes a minute.</p>

        <div class="cta">
            <a href="{{ $feedbackUrl }}" class="btn">Share Your Feedback</a>
        </div>

        <div class="notice">
            <strong>Note:</strong> This link will expire in {{ $expiryDays }} days. If you have any questions, please contact your assigned trainer.
        </div>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} CheckinMe. All rights reserved.
    </div>
</div>
</body>
</html>
