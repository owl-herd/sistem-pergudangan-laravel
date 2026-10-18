<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class WarehouseRequest {
    public function handle(Request $request, Closure $next) {
        app()->instance(\App\Support\ResponseState::class,new \App\Support\ResponseState);
        if (!$request->has('_token') && $request->has('csrf')) $request->merge(['_token'=>$request->input('csrf')]);
        if (config('database.connections.mysql.database')===config('warehouse.source_database')) abort(503,'Koneksi database aplikasi lama tidak diizinkan pada proyek migrasi.');
        return $next($request);
    }
}
