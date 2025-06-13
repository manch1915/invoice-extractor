<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'demo' => (bool) config('invoices.demo.enabled'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** One-click entry for the public demo, without passwords. */
    public function demo(Request $request, Role $role): RedirectResponse
    {
        abort_unless(config('invoices.demo.enabled'), 404);

        $email = $role === Role::Owner ? config('invoices.demo.owner_email') : config('invoices.demo.accountant_email');
        $user = User::query()->where('email', $email)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
