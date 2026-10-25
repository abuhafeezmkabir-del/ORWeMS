<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * DemoAuthController
 *
 * Authentication for the demonstration area only. Credentials are validated
 * against environment variables, never against the `users` table. No database
 * queries are performed by any method on this controller.
 */
class DemoAuthController extends Controller
{
    public function loginView(Request $request)
    {
        return view('demo.auth');
    }

    public function login(Request $request)
    {
        $expectedEmail = config('orwems.demo.email', 'demo@wicov.org');
        $expectedPassword = config('orwems.demo.password', 'demo123');

        $email = $request->input('email');
        $password = $request->input('password');

        if ($email === $expectedEmail && $password === $expectedPassword) {
            $request->session()->put('demo-logged-in', true);
            $request->session()->put('demo-logged-in-at', now()->toDateTimeString());

            // Regenerate session ID to prevent session fixation attacks.
            $request->session()->regenerate();

            return redirect()->route('demo.dashboard');
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', 'demo-login-failed');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('demo-logged-in');
        $request->session()->forget('demo-logged-in-at');
        return redirect()->route('demo.login');
    }
}