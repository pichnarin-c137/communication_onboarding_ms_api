<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Onboarding Feedback</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f4f8; margin: 0; padding: 20px; color: #333; }
        .card { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.1); overflow: hidden; }
        .header { background: #1a56db; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; font-size: 14px; opacity: .85; }
        .body { padding: 28px 32px; }
        .state-notice { text-align: center; padding: 20px 0; }
        .state-notice .icon { font-size: 48px; margin-bottom: 16px; }
        .state-notice h2 { margin: 0 0 8px; }
        .state-notice p { color: #666; }

        /* Star rating */
        .star-group { display: flex; gap: 8px; justify-content: center; margin: 20px 0; }
        .star-group input[type="radio"] { display: none; }
        .star-group label { font-size: 36px; cursor: pointer; color: #ddd; transition: color .2s; }
        .star-group input[type="radio"]:checked ~ label,
        .star-group label:hover,
        .star-group label:hover ~ label { color: #f59e0b; }
        /* reverse order trick for CSS-only star rating */
        .star-group { flex-direction: row-reverse; }
        .star-group label:hover,
        .star-group label:hover ~ label { color: #f59e0b; }
        .star-group input[type="radio"]:checked ~ label { color: #f59e0b; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 14px; }
        .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; resize: vertical; min-height: 100px; }
        .btn { width: 100%; padding: 14px; background: #1a56db; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #1648c0; }
        .rating-label { text-align: center; font-size: 14px; color: #666; margin-top: -12px; margin-bottom: 16px; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h1>Rate Your Onboarding</h1>
        @isset($onboarding)
            <p>Reference: {{ $onboarding->request_code }}</p>
        @endisset
    </div>
    <div class="body">

        @if($state === 'expired')
            <div class="state-notice">
                <div class="icon">&#9203;</div>
                <h2>Link Expired</h2>
                <p>This feedback link has expired. Please contact your trainer to request a new link.</p>
            </div>

        @elseif($state === 'used')
            <div class="state-notice">
                <div class="icon">&#10003;</div>
                <h2>Already Submitted</h2>
                <p>You have already submitted feedback for this onboarding. Thank you!</p>
            </div>

        @elseif($state === 'success')
            <div class="state-notice">
                <div class="icon">&#127775;</div>
                <h2>Thank You!</h2>
                <p>Your feedback has been recorded. We appreciate you taking the time to share your experience.</p>
            </div>

        @else
            {{-- $state === 'form' --}}
            <form method="POST" action="{{ url()->current() }}">
                @csrf

                <div class="form-group">
                    <label>How would you rate your experience?</label>
                    <div class="star-group">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" name="rating" id="star{{ $i }}" value="{{ $i }}" {{ old('rating') == $i ? 'checked' : '' }} required />
                            <label for="star{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">&#9733;</label>
                        @endfor
                    </div>
                    <p class="rating-label">Tap a star to rate</p>
                    @error('rating')
                        <p style="color:red;font-size:13px;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="comment">Comments (optional)</label>
                    <textarea name="comment" id="comment" placeholder="Share any additional thoughts...">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p style="color:red;font-size:13px;">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" style="color: #ffffff !important; text-decoration: none;" class="btn">Submit Feedback</button>
            </form>
        @endif

    </div>
</div>

</body>
</html>
