<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\AuthHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (\App\Support\InstallService::isInstalled() && Auth::check()) {
            return redirect()->route('workbooks.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = AuthHelper::findUserByLogin($credentials['login']);

        if ($user && ! $user->isActive()) {
            return back()->withErrors(['login' => 'This account is deactivated. Contact an administrator.'])->onlyInput('login');
        }

        if (! AuthHelper::attempt($credentials['login'], $credentials['password'], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Invalid username or password.'])->onlyInput('login');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->tokens()->where('name', 'web-ui')->delete();
        $token = $user->createToken('web-ui')->plainTextToken;
        $request->session()->put('api_token', $token);

        return redirect()->intended(route('workbooks.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->user()?->tokens()->where('name', 'web-ui')->delete();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
