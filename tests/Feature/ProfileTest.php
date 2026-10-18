<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class ProfileTest extends TestCase {
    private array $ids=[];
    private function actor(): User {
        $id=DB::table('users')->insertGetId(['nama'=>'Profil Uji','email'=>'profile-'.bin2hex(random_bytes(6)).'@example.test','password_hash'=>password_hash('InitialPass123',PASSWORD_DEFAULT),'role'=>'manager','aktif'=>1]);
        $this->ids[]=$id;
        return User::findOrFail($id);
    }
    private function photo(): UploadedFile {
        return UploadedFile::fake()->createWithContent('avatar.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII='));
    }
    protected function tearDown(): void {
        foreach($this->ids as $id) {
            DB::table('riwayat_pengelolaan_akun')->where('sasaran_id',$id)->delete();
            DB::table('user_profiles')->where('user_id',$id)->delete();
            DB::table('users')->where('id',$id)->delete();
        }
        parent::tearDown();
    }
    private function submit(array $data) {
        return $this->withSession(['_token'=>'profile-test-token'])->post('/profil.php',$data+['_token'=>'profile-test-token']);
    }
    public function test_profile_requires_active_login_and_csrf(): void {
        $this->get('/profil.php')->assertRedirect('login.php');
        $this->get('/foto_profil.php')->assertRedirect('login.php');
        $actor=$this->actor();
        $this->actingAs($actor)->get('/profil.php')->assertOk()->assertSee('Profil saya');
        $this->post('/profil.php',['nama'=>'Changed'])->assertStatus(419);
        DB::table('users')->where('id',$actor->id)->update(['aktif'=>0]);
        $this->get('/profil.php')->assertForbidden();
    }
    public function test_session_cookie_can_be_marked_secure_for_https_deployments(): void {
        config(['session.secure'=>true]);
        $response=$this->get('/login.php')->assertOk();
        $cookie=$response->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }
    public function test_profile_updates_only_owner_and_password_needs_current_password(): void {
        $actor=$this->actor(); $other=$this->actor(); $this->actingAs($actor);
        $this->submit(['nama'=>'Changed','user_id'=>$other->id,'role'=>'super_admin'])->assertRedirect('profil.php');
        $this->assertSame('Changed',$actor->fresh()->nama);
        $this->assertSame('manager',$actor->fresh()->role);
        $this->assertSame('Profil Uji',$other->fresh()->nama);
        $this->submit(['nama'=>'Rejected','current_password'=>'wrong','password'=>'NewPass123','password_confirmation'=>'NewPass123'])->assertSessionHasErrors('current_password');
        $this->assertSame('Changed',$actor->fresh()->nama);
        $this->assertTrue(password_verify('InitialPass123',$actor->fresh()->password_hash));
        $this->submit(['nama'=>'Changed','current_password'=>'InitialPass123','password'=>'NewPass123','password_confirmation'=>'mismatch'])->assertSessionHasErrors('password');
        $this->submit(['nama'=>'Changed','current_password'=>'InitialPass123','password'=>'NewPass123','password_confirmation'=>'NewPass123'])->assertRedirect('profil.php');
        $this->assertTrue(password_verify('NewPass123',$actor->fresh()->password_hash));
    }
    public function test_photos_are_private_validated_replaced_and_removed(): void {
        Storage::fake('local'); $actor=$this->actor(); $other=$this->actor(); $this->actingAs($actor);
        $this->submit(['nama'=>'Profil Uji','photo'=>UploadedFile::fake()->create('payload.svg',1,'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->submit(['nama'=>'Profil Uji','photo'=>UploadedFile::fake()->create('too-large.png',4097,'image/png')])->assertSessionHasErrors('photo');
        $this->submit(['nama'=>'Profil Uji','photo'=>$this->photo()])->assertRedirect('profil.php');
        $first=DB::table('user_profiles')->where('user_id',$actor->id)->value('photo_path');
        Storage::disk('local')->assertExists($first);
        $publicRoot=realpath(public_path());
        $photoPath=realpath(Storage::disk('local')->path($first));
        $this->assertNotFalse($publicRoot);
        $this->assertNotFalse($photoPath);
        $this->assertStringStartsNotWith(strtolower($publicRoot.DIRECTORY_SEPARATOR),strtolower($photoPath));
        $this->get('/storage/'.$first)->assertNotFound();
        $this->get('/foto_profil.php')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->actingAs($other)->get('/foto_profil.php?user_id='.$actor->id)->assertNotFound();
        $this->actingAs($actor);
        $this->submit(['nama'=>'Profil Uji','photo'=>$this->photo()])->assertRedirect('profil.php');
        Storage::disk('local')->assertMissing($first);
        $second=DB::table('user_profiles')->where('user_id',$actor->id)->value('photo_path');
        $this->submit(['nama'=>'Profil Uji','remove_photo'=>1])->assertRedirect('profil.php');
        Storage::disk('local')->assertMissing($second);
        $this->get('/foto_profil.php')->assertNotFound();
    }
}
