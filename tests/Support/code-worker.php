<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(Illuminate\Support\Facades\DB::connection()->getDatabaseName()!=='sistem_pergudangan_laravel_test') throw new RuntimeException('Wrong test database');
$session=$app->make('session')->driver();$session->start();
$request=Illuminate\Http\Request::create('/aksi_admin.php','POST',['aksi'=>'barang_simpan','nama'=>$argv[1],'kategori'=>'Uji concurrency','kategori_barang'=>'barang_siap','satuan'=>'unit','stok_awal'=>'0','csrf'=>$session->token()]);$request->setLaravelSession($session);$app->instance('request',$request);
Illuminate\Support\Facades\Auth::login(App\Models\User::where('role','admin_pusat')->where('aktif',1)->firstOrFail());
$request->merge(['csrf'=>$session->token()]);
try { app(App\Services\WarehouseActions::class)->aksi_admin(); } catch(Illuminate\Http\Exceptions\HttpResponseException $e) { if($e->getResponse()->getStatusCode()!==302) throw $e; }
if(!Illuminate\Support\Facades\DB::table('barang')->where('nama',$argv[1])->exists()) throw new RuntimeException('Code allocation failed');

echo json_encode(['database'=>Illuminate\Support\Facades\DB::connection()->getDatabaseName(),'name'=>$argv[1],'code'=>Illuminate\Support\Facades\DB::table('barang')->where('nama',$argv[1])->value('kode')]);
