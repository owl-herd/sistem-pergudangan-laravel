<?php

use App\Models\User;
use App\Services\WarehouseStockAdjustment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
    throw new RuntimeException('Stocktake worker requires the isolated test database.');
}

$actor = User::query()
    ->where('role', 'admin_pusat')
    ->where('aktif', 1)
    ->firstOrFail();
Auth::login($actor);

app(WarehouseStockAdjustment::class)->adjust(
    (int) ($argv[1] ?? 0),
    (int) ($argv[2] ?? 0),
    'Concurrency stocktake test'
);
