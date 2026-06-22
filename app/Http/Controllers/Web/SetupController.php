<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Support\InstallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function show(): View|RedirectResponse
    {
        if (InstallService::isInstalled()) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if (InstallService::isInstalled()) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.regex' => 'Username may only contain letters, numbers, dots, dashes, and underscores.',
        ]);

        $admin = $this->userService->createAdmin(
            $data['name'],
            $data['email'],
            $data['password'],
        );

        Auth::login($admin);
        $request->session()->regenerate();

        $token = $admin->createToken('web-ui')->plainTextToken;
        $request->session()->put('api_token', $token);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Synexel is ready. Your administrator account was created — you can add more users below.');
    }
}
