<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Please wait before trying again.',
            ]);
        }

        $isAdministrator = $request->boolean('remember')
            && User::query()
                ->where('email', strtolower($credentials['email']))
                ->whereHas('role.permissions', fn ($query) => $query->where('name', 'admin.dashboard.view'))
                ->exists();

        if (! Auth::attempt([
            'email' => strtolower($credentials['email']),
            'password' => $credentials['password'],
            'status' => 'active',
        ], $request->boolean('remember') && ! $isAdministrator)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The sign-in details could not be verified.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        $isAdmin = $user->hasPermission('admin.dashboard.view');
        if ($isAdmin) {
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'action' => 'admin.session.started',
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->getKey(),
                'request_id' => (string) Str::uuid(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
                'properties' => [],
                'created_at' => now(),
            ]);
        }

        $destination = $isAdmin ? route('admin.dashboard') : route('investor.dashboard');

        return redirect()->intended($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user?->hasPermission('admin.dashboard.view')) {
            AuditLog::query()->create([
                'actor_id' => $user->id,
                'action' => 'admin.session.ended',
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->getKey(),
                'request_id' => (string) Str::uuid(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
                'properties' => [],
                'created_at' => now(),
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}