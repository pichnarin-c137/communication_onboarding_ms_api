<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTokenException;
use App\Exceptions\TokenExpiredException;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * GET /reset-password?token=...&email=...
     * Show the reset password form.
     */
    public function showForm(Request $request): View
    {
        $token = $request->query('token', '');
        $email = $request->query('email', '');

        if (! $token || ! $email) {
            return view('auth.reset-password', [
                'state' => 'invalid',
                'token' => '',
                'email' => '',
            ]);
        }

        return view('auth.reset-password', [
            'state' => 'form',
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * POST /reset-password
     * Process the reset form submission.
     */
    public function handleForm(Request $request): View
    {
        $request->validate([
            'email'                 => ['required', 'email'],
            'token'                 => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        try {
            $this->authService->resetPassword(
                $request->email,
                $request->token,
                $request->password,
            );
        } catch (TokenExpiredException) {
            return view('auth.reset-password', [
                'state' => 'expired',
                'token' => $request->token,
                'email' => $request->email,
            ]);
        } catch (InvalidTokenException) {
            return view('auth.reset-password', [
                'state' => 'invalid',
                'token' => $request->token,
                'email' => $request->email,
            ]);
        }

        return view('auth.reset-password', [
            'state' => 'success',
            'token' => '',
            'email' => '',
        ]);
    }
}
