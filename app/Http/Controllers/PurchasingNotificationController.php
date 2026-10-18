<?php

namespace App\Http\Controllers;

use App\Services\AccessService;
use Illuminate\Support\Facades\DB;

class PurchasingNotificationController extends Controller
{
    public function markRead(int $notificationId, AccessService $access)
    {
        $access->ensure(['purchasing']);

        $notification = DB::table('purchasing_notifications')
            ->where('id', $notificationId)
            ->where('recipient_id', (int) user()['id'])
            ->exists();

        abort_unless($notification, 404);

        DB::table('purchasing_notifications')
            ->where('id', $notificationId)
            ->where('recipient_id', (int) user()['id'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect('purchasing.php#notifikasi');
    }
}
