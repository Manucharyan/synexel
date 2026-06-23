<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function hit(string $method, string $uri, array $data = [], ?App\Models\User $user = null, array $headers = []): Symfony\Component\HttpFoundation\Response
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
    foreach ($headers as $key => $value) {
        $req->headers->set($key, $value);
    }

    $resp = $kernel->handle($req);
    $kernel->terminate($req, $resp);

    return $resp;
}

function api(string $method, string $uri, array $data, App\Models\User $user): Symfony\Component\HttpFoundation\Response
{
    $token = $user->createToken('debug')->plainTextToken;

    return hit($method, $uri, $data, $user, [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]);
}

$passed = 0;
$failed = 0;

function check(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL')." {$label}\n";
    $ok ? $passed++ : $failed++;
}

echo "=== Spreadsheet Restrictions Debug ===\n\n";

$settings = app(App\Services\SpreadsheetSettingsService::class);
$settings->setBlockAdding(false);
$settings->setBlockDeleting(false);

$admin = App\Models\User::factory()->create(['role' => App\Enums\UserRole::Admin]);
$user = App\Models\User::factory()->create(['role' => App\Enums\UserRole::User]);

$r = hit('GET', '/admin/settings', [], $user);
check($r->getStatusCode() === 403, 'Regular user cannot open admin settings');

$r = hit('GET', '/admin/settings', [], $admin);
check($r->getStatusCode() === 200 && str_contains($r->getContent(), 'Spreadsheet restrictions'), 'Admin can open settings page');

$r = hit('PATCH', '/admin/settings', ['block_adding' => '1', 'block_deleting' => '0'], $admin);
check($r->isRedirection(), 'Admin can enable block adding');

check($settings->isBlockAddingEnabled() && ! $settings->isBlockDeletingEnabled(), 'Block adding persisted');

$r = api('POST', '/api/v1/workbooks', ['name' => 'Blocked'], $user);
check($r->getStatusCode() === 403, 'User blocked from creating workbook');

$r = api('POST', '/api/v1/workbooks', ['name' => 'Admin OK'], $admin);
check($r->getStatusCode() === 403, 'Admin also blocked from creating workbook when adding disabled');

$workbook = app(App\Domain\Spreadsheet\Services\WorkbookService::class)->create($user, 'User WB');
$sheet = $workbook->sheets->first();

$r = api('PATCH', "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells", [
    'updates' => [['row' => 1, 'col' => 1, 'value' => 'hello']],
], $user);
check($r->getStatusCode() === 200, 'User can edit existing cell before new-cell block check');

$r = api('PATCH', "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells", [
    'updates' => [['row' => 2, 'col' => 1, 'value' => 'new']],
], $user);
check($r->getStatusCode() === 403, 'User blocked from adding new cell');

$r = api('PATCH', "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells", [
    'updates' => [['row' => 3, 'col' => 1, 'value' => '', 'style' => ['bold' => true]]],
], $user);
check($r->getStatusCode() === 200, 'User can still apply style-only update when adding blocked');

$settings->setBlockAdding(false);
$settings->setBlockDeleting(true);

$r = api('DELETE', "/api/v1/workbooks/{$workbook->id}", [], $user);
check($r->getStatusCode() === 403, 'User blocked from deleting workbook');

$r = api('PATCH', "/api/v1/workbooks/{$workbook->id}/sheets/{$sheet->id}/cells", [
    'updates' => [['row' => 1, 'col' => 1, 'clear' => true]],
], $user);
check($r->getStatusCode() === 403, 'User blocked from clearing cell');

$settings->setBlockAdding(true);
$addResponse = app(App\Domain\Spreadsheet\Services\CellBatchService::class)->batchUpdate($sheet, [
    ['row' => 4, 'col' => 1, 'value' => 'undo me'],
]);
$operationId = $addResponse['operation_id'] ?? null;

$r = api('POST', "/api/v1/operations/{$operationId}/revert", [], $user);
check($r->getStatusCode() === 200, 'User can undo an add when only adding is blocked');

$r = api('GET', '/api/v1/settings/spreadsheet', [], $user);
$payload = json_decode($r->getContent(), true)['data'] ?? [];
check(
    $r->getStatusCode() === 200
    && ($payload['can_add'] ?? true) === false
    && ($payload['can_delete'] ?? true) === false,
    'Settings API reports effective access for user'
);

$settings->setBlockAdding(false);
$settings->setBlockDeleting(false);
$admin->delete();
$user->delete();
App\Domain\Spreadsheet\Models\Workbook::query()->where('id', $workbook->id)->delete();

echo "\n--- Result: {$passed} passed, {$failed} failed ---\n";
exit($failed > 0 ? 1 : 0);
