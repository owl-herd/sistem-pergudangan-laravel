<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
    public function register(): void { require_once app_path('Support/warehouse_helpers.php'); }
    public function boot(): void { if(config('database.connections.mysql.database')===config('warehouse.source_database')) throw new \RuntimeException('Database sumber tidak diizinkan.'); \Illuminate\Support\Facades\DB::connection()->getPdo()->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE,\PDO::FETCH_ASSOC); }
}
