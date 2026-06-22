<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function hit(string $method, string $uri, array $data = [], ?App\Models\User $user = null): Symfony\Component\HttpFoundation\Response
{
    global $kernel, $app;

    if ($user) {
        Illuminate\Support\Facades\Auth::login($user);
    } else {
        Illuminate\Support\Facades\Auth::logout();
    }

    $session = $app->make('session.store');
    $session->start();

    if ($method === 'POST' || $method === 'PATCH' || $method === 'DELETE') {
        $data['_token'] = $session->token();
    }

    $req = Illuminate\Http\Request::create($uri, $method, $data);
    $req->setLaravelSession($session);

    $resp = $kernel->handle($req);
    $kernel->terminate($req, $resp);

    return $resp;
}

$passed = 0;
$failed = 0;

function check(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL')." {$label}\n";
    $ok ? $passed++ : $failed++;
}

echo "=== Install & User Management Debug ===\n\n";

check(App\Support\InstallService::isInstalled(), 'System is installed (existing DB)');

$r = hit('GET', '/setup');
check($r->isRedirection() && str_contains($r->headers->get('Location', ''), 'login'), 'GET /setup redirects to login when installed');

$r = hit('GET', '/admin/users');
check($r->isRedirection(), 'GET /admin/users redirects guest to login');

$admin = App\Models\User::where('role', 'admin')->first();
$r = hit('GET', '/admin/users', [], $admin);
check($r->getStatusCode() === 200, 'GET /admin/users returns 200 for admin');

// POST create user as admin via HTTP
$admin = App\Models\User::where('role', 'admin')->first();
$newName = 'httptest_'.time();
$r = hit('POST', '/admin/users', [
    'name' => $newName,
    'email' => $newName.'@example.com',
    'password' => 'httppass123',
    'password_confirmation' => 'httppass123',
    'role' => 'user',
], $admin);
check($r->isRedirection() && str_contains($r->headers->get('Location', ''), 'admin/users'), 'POST /admin/users creates user (redirect)');
$httpUser = App\Models\User::where('name', $newName)->first();
check($httpUser !== null && App\Support\AuthHelper::attempt($newName, 'httppass123'), 'HTTP-created user can login');
$httpUser?->delete();

$user = App\Models\User::where('role', 'user')->first();
if ($user) {
    $r = hit('GET', '/admin/users', [], $user);
    check($r->getStatusCode() === 403, 'GET /admin/users forbidden for regular user');
} else {
    echo "SKIP no regular user in DB\n";
}

$inactive = App\Models\User::firstOrCreate(
    ['name' => 'inactive_test'],
    ['email' => 'inactive_test@example.com', 'password' => 'pass12345', 'role' => 'user', 'is_active' => false]
);
$inactive->update(['is_active' => false]);
check(! App\Support\AuthHelper::attempt('inactive_test', 'pass12345'), 'Inactive user cannot login');

$testUser = App\Models\User::where('name', 'testuser')->first();
if ($testUser) {
    $pwdOk = Illuminate\Support\Facades\Hash::check('password123', $testUser->password);
    if (! $pwdOk) {
        $testUser->update(['password' => 'password123']);
    }
    check(App\Support\AuthHelper::attempt('testuser', 'password123'), 'Active user testuser can login');
} else {
    echo "SKIP testuser not in DB\n";
}

try {
    app(App\Services\UserService::class)->create('_dup_test', 'dup1@example.com', 'pass12345');
    app(App\Services\UserService::class)->create('_dup_test', 'dup2@example.com', 'pass12345');
    check(false, 'Duplicate username rejected');
} catch (Illuminate\Validation\ValidationException) {
    check(true, 'Duplicate username rejected');
} finally {
    App\Models\User::where('name', '_dup_test')->delete();
}

// Create user via service and verify
try {
    $newName = 'debuguser_'.time();
    $created = app(App\Services\UserService::class)->create($newName, $newName.'@example.com', 'debugpass123');
    check(App\Support\AuthHelper::attempt($newName, 'debugpass123'), "New user {$newName} can login after create");
    $created->delete();
} catch (Throwable $e) {
    check(false, 'Create user flow: '.$e->getMessage());
}

$r = hit('GET', '/api/v1/workbooks');
check($r->getStatusCode() !== 503, 'API available when installed (not 503)');

echo "\n--- Result: {$passed} passed, {$failed} failed ---\n";
exit($failed > 0 ? 1 : 0);
