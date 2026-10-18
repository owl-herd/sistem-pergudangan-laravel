<?php

namespace App\Http\Controllers;

use App\Services\AccessService;
use Illuminate\Support\Facades\DB;

class WarehouseNotificationController extends Controller
{
    public function markRead(int $notificationId, AccessService $access)
    {
        $access->ensure(['admin_pusat', 'admin_gudang', 'manager', 'super_admin']);
        $query = DB::table('warehouse_notifications')->where('id', $notificationId)->where('recipient_id', (int) user()['id']);
        abort_unless((clone $query)->exists(), 404);
        $query->whereNull('read_at')->update(['read_at' => now()]);

        return redirect(user()['role'] === 'super_admin' ? 'super_admin.php#notifikasi' : 'dashboard.php#notifikasi');
    }
}
