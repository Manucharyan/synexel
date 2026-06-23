<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserCapabilitiesService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserCapabilitiesService $capabilitiesService,
    ) {}

    public function index(Request $request): View
    {
        $users = User::query()->orderBy('role')->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,user'],
        ], [
            'name.regex' => 'Username may only contain letters, numbers, dots, dashes, and underscores.',
        ]);

        $user = $this->userService->create(
            $data['name'],
            $data['email'],
            $data['password'],
            UserRole::from($data['role']),
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User \"{$user->name}\" created successfully. They can sign in with their username and password.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->userService->setActive($user, (bool) $data['is_active']);

        $label = $user->fresh()->isActive() ? 'activated' : 'deactivated';

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User \"{$user->name}\" was {$label}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $name = $user->name;
        $this->userService->delete($request->user(), $user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User \"{$name}\" was deleted.");
    }

    public function updateCapabilities(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'can_add_cells' => ['required', 'boolean'],
            'can_delete_cells' => ['required', 'boolean'],
        ]);

        try {
            $this->capabilitiesService->updateCapabilities(
                $request->user(),
                $user,
                (bool) $data['can_add_cells'],
                (bool) $data['can_delete_cells'],
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['capabilities' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Cell permissions updated for \"{$user->name}\".");
    }
}
