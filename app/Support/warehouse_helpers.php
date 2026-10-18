<?php
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
function db(): PDO {
    $pdo=DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
    return $pdo;
}
function user(): ?array { return auth()->user()?->toArray(); }
function csrf(): string { return csrf_token(); }
function check_csrf(): void {
    $submitted=request()->input('csrf',request()->input('_token',''));
    if (!is_string($submitted) || $submitted==='' || !hash_equals(csrf_token(),$submitted)) {
        throw new HttpResponseException(response('Sesi formulir kedaluwarsa. Muat ulang halaman.',419));
    }
}
function require_role(string ...$roles): void { app(App\Services\AccessService::class)->ensure($roles); }
function go(string $url): never { throw new HttpResponseException(redirect($url)); }
function flash(string $message,string $type='success'): void { session()->put('warehouse_flash',[$message,$type]); }
function take_flash(): ?array { return session()->pull('warehouse_flash'); }
function record_user_management_audit(PDO $pdo,int $targetId,string $targetName,string $targetEmail,string $action,string $detail): void {
    $actor=user();
    if (!$actor) throw new RuntimeException('Sesi administrator tidak tersedia untuk mencatat perubahan akun.');
    $pdo->prepare('INSERT INTO riwayat_pengelolaan_akun (pelaku_id,pelaku_nama,pelaku_email,sasaran_id,sasaran_nama,sasaran_email,aksi,detail) VALUES (?,?,?,?,?,?,?,?)')->execute([$actor['id'],$actor['nama'],$actor['email'],$targetId,$targetName,$targetEmail,$action,$detail]);
}
function receipt_photo_storage_directory(bool $create=false): string {
    $directory=storage_path('app/private/receipt-photos');
    if ($create && !is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
    if (!is_dir($directory)) throw new RuntimeException('Foto bukti tidak ditemukan.');
    return realpath($directory);
}
function warehouse_status(?int $status=null): int {
    $state=app(App\Support\ResponseState::class);
    if ($status!==null) $state->status=$status;
    return $state->status;
}
function warehouse_header(string $header): void {
    [$name,$value]=explode(':',$header,2);
    app(App\Support\ResponseState::class)->headers[trim($name)]=trim($value);
}
function warehouse_response(string $body) { $state=app(App\Support\ResponseState::class); return response($body,$state->status,$state->headers); }
function warehouse_abort(string $body=''): never { throw new HttpResponseException(warehouse_response($body)); }
function warehouse_finish(): never { warehouse_abort(); }
function warehouse_uploads(): array {
    $result=[];
    foreach(request()->allFiles() as $key=>$files) {
        $many=is_array($files); $entries=$many?$files:[$files];
        foreach($entries as $index=>$file) {
            if (!$file) continue;
            $fields=['name'=>$file->getClientOriginalName(),'tmp_name'=>$file->getPathname(),'error'=>$file->getError(),'size'=>$file->getSize(),'type'=>$file->getClientMimeType()];
            foreach($fields as $field=>$value) { if($many) $result[$key][$field][$index]=$value; else $result[$key][$field]=$value; }
        }
    }
    return $result;
}
