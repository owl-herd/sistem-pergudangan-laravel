<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class ProfileController extends Controller {
    private function account() {
        require_role('super_admin','admin_pusat','admin_gudang','manager','purchasing');
        return auth()->user();
    }
    public function edit() {
        $account=$this->account();
        $hasPhoto=(bool)DB::table('user_profiles')->where('user_id',$account->id)->value('photo_path');
        return view('warehouse.profile',compact('account','hasPhoto'));
    }
    public function update(Request $request) {
        $account=$this->account();
        check_csrf();
        $request->merge(['nama'=>is_string($request->input('nama'))?trim($request->input('nama')):$request->input('nama')]);
        $data=$request->validate([
            'nama'=>['required','string','max:120'],
            'photo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096','dimensions:max_width=4096,max_height=4096'],
            'remove_photo'=>['sometimes','boolean'],
            'current_password'=>['nullable','string'],
            'password'=>['nullable','string','min:8','max:200','confirmed'],
        ]);
        $changePassword=isset($data['password']) && $data['password']!=='';
        $newPath=null; $oldPath=null;
        try {
            if ($request->hasFile('photo')) {
                $newPath=$request->file('photo')->store('profile-photos','local');
                if (!$newPath) throw new \RuntimeException('Foto profil tidak dapat disimpan.');
            }
            DB::transaction(function () use ($account,$data,$changePassword,$newPath,&$oldPath,$request) {
                $locked=DB::table('users')->where('id',$account->id)->lockForUpdate()->first();
                abort_unless($locked && $locked->aktif && $locked->role===$account->role,403);
                if ($changePassword && !password_verify($data['current_password']??'', $locked->password_hash)) {
                    throw ValidationException::withMessages(['current_password'=>'Kata sandi saat ini tidak sesuai.']);
                }
                $changes=['nama'=>$data['nama']];
                if ($changePassword) $changes['password_hash']=password_hash($data['password'],PASSWORD_DEFAULT);
                DB::table('users')->where('id',$account->id)->update($changes);
                $oldPath=DB::table('user_profiles')->where('user_id',$account->id)->value('photo_path');
                if ($newPath || $request->boolean('remove_photo')) DB::table('user_profiles')->updateOrInsert(['user_id'=>$account->id],['photo_path'=>$newPath]);
                record_user_management_audit(db(),$account->id,$data['nama'],$locked->email,'akun_diperbarui','Pengguna memperbarui profil sendiri'.($changePassword?' dan kata sandi':'').'.');
            });
        } catch (\Throwable $e) {
            if ($newPath) Storage::disk('local')->delete($newPath);
            throw $e;
        }
        if (($newPath || $request->boolean('remove_photo')) && $oldPath) Storage::disk('local')->delete($oldPath);
        $account->refresh();
        if ($changePassword) { $request->session()->regenerate(); $request->session()->regenerateToken(); }
        return redirect('profil.php')->with('status','Profil berhasil diperbarui.');
    }
    public function photo() {
        $account=$this->account();
        $path=DB::table('user_profiles')->where('user_id',$account->id)->value('photo_path');
        abort_unless($path && preg_match('~^profile-photos/[a-zA-Z0-9]+\.(jpg|jpeg|png|webp)$~',$path) && Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
