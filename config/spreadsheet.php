<?php

return [
    'max_cells_per_request' => (int) env('SPREADSHEET_MAX_CELLS_PER_REQUEST', 10000),
    'webhook_timeout' => (int) env('WEBHOOK_TIMEOUT', 10),
    'webhook_max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 5),
];
