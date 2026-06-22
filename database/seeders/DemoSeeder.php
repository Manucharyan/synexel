<?php

namespace Database\Seeders;

use App\Domain\Spreadsheet\Services\CellBatchService;
use App\Domain\Spreadsheet\Services\WorkbookService;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command?->warn('Users already exist — skipping demo seed.');

            return;
        }

        $userService = app(UserService::class);
        $user = $userService->createAdmin('demo', 'demo@excel-api.local', 'password');

        $token = $user->createToken('demo-token');

        $workbookService = app(WorkbookService::class);
        $cellBatchService = app(CellBatchService::class);

        $workbook = $workbookService->create($user, 'Demo Budget');
        $sheet = $workbook->sheets->first();

        $cellBatchService->batchUpdate($sheet, [
            ['row' => 1, 'col' => 1, 'value' => 'Item'],
            ['row' => 1, 'col' => 2, 'value' => 'Amount'],
            ['row' => 2, 'col' => 1, 'value' => 'Revenue'],
            ['row' => 2, 'col' => 2, 'value' => '1000'],
            ['row' => 3, 'col' => 1, 'value' => 'Costs'],
            ['row' => 3, 'col' => 2, 'value' => '400'],
            ['row' => 4, 'col' => 1, 'value' => 'Profit'],
            ['row' => 4, 'col' => 2, 'formula' => '=B2-B3'],
        ]);

        $this->command?->info('Demo admin: demo / password');
        $this->command?->info('API token: '.$token->plainTextToken);
    }
}
