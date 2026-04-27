<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password — CheckinMe</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f4f8; margin: 0; padding: 20px; color: #333; }
        .card { max-width: 460px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.1); overflow: hidden; }
        .header { background: #1a56db; color: #fff; padding: 28px 32px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 8px 0 0; font-size: 14px; opacity: .85; }
        .body { padding: 28px 32px; }

        .state-notice { text-align: center; padding: 16px 0; }
        .state-notice .icon { font-size: 52px; margin-bottom: 14px; }
        .state-notice h2 { margin: 0 0 8px; }
        .state-notice p { color: #666; font-size: 15px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: #444; }
        .form-group input {
            width: 100%; padding: 10px 12px;
            border: 1px solid #ddd; border-radius: 6px;
            font-size: 14px; transition: border-color .2s;
        }
        .form-group input:focus { outline: none; border-color: #1a56db; }
        .field-error { color: #dc2626; font-size: 12px; margin-top: 4px; }

        .btn {
            width: 100%; padding: 13px;
            background: #1a56db; color: #fff;
            border: none; border-radius: 6px;
            font-size: 15px; font-weight: bold;
            cursor: pointer; transition: background .2s;
        }
        .btn:hover { background: #1648c0; }
        .btn:disabled { background: #93aaeb; cursor: not-allowed; }

        .password-wrapper { position: relative; }
        .toggle-pw {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; font-size: 13px; color: #666;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h1>CheckinMe</h1>
        <p>Reset your password</p>
    </div>
    <div class="body">

        @if($state === 'success')
            <div class="state-notice">
                <div class="icon">&#10004;&#65039;</div>
                <h2>Password Reset!</h2>
                <p>Your password has been updated. Redirecting to login in <strong id="countdown">3</strong>s…</p>
            </div>
            <script>
                (function () {
                    var seconds = 3;
                    var el = document.getElementById('countdown');
                    var interval = setInterval(function () {
                        seconds--;
                        if (el) el.textContent = seconds;
                        if (seconds <= 0) {
                            clearInterval(interval);
                            window.location.href = '{{ config('app.frontend_url') }}/login';
                        }
                    }, 1000);
                })();
            </script>

        @elseif($state === 'expired')
            <div class="state-notice">
                <div class="icon">&#9203;</div>
                <h2>Link Expired</h2>
                <p>This reset link has expired. Please request a new one from the login page.</p>
            </div>

        @elseif($state === 'invalid')
            <div class="state-notice">
                <div class="icon">&#10060;</div>
                <h2>Invalid Link</h2>
                <p>This reset link is invalid or has already been used. Please request a new one.</p>
            </div>

        @else
            {{-- state === 'form' --}}
            <form method="POST" action="/reset-password" id="resetForm">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                <input type="hidden" name="email" value="{{ $email }}" />

                <div class="form-group">
                    <label>Resetting password for</label>
                    <input type="text" value="{{ $email }}" disabled />
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password"
                               placeholder="Min. 8 characters" required minlength="8" />
                        <button type="button" class="toggle-pw" onclick="toggleVisibility('password', this)">Show</button>
                    </div>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               placeholder="Repeat new password" required minlength="8" />
                        <button type="button" class="toggle-pw" onclick="toggleVisibility('password_confirmation', this)">Show</button>
                    </div>
                    @error('password_confirmation')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    <p class="field-error" id="mismatch" style="display:none;">Passwords do not match.</p>
                </div>

                <button type="submit" class="btn" style="color: #ffffff !important; text-decoration: none;" id="submitBtn">Reset Password</button>
            </form>

            <script>
                function toggleVisibility(id, btn) {
                    const input = document.getElementById(id);
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    btn.textContent = isHidden ? 'Hide' : 'Show';
                }

                document.getElementById('resetForm').addEventListener('submit', function (e) {
                    const pw  = document.getElementById('password').value;
                    const cpw = document.getElementById('password_confirmation').value;
                    const msg = document.getElementById('mismatch');

                    if (pw !== cpw) {
                        e.preventDefault();
                        msg.style.display = 'block';
                        return;
                    }
                    msg.style.display = 'none';
                    document.getElementById('submitBtn').disabled = true;
                    document.getElementById('submitBtn').textContent = 'Resetting…';
                });
            </script>
        @endif

    </div>
</div>

</body>
</html>
