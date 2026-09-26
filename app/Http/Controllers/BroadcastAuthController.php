<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * POST /broadcasting/auth - Laravel's own BroadcastController, except that the channel rules (routes/channels.php) are registered HERE,
 * when somebody actually asks to listen, and not while the app boots. Registering them at boot builds the broadcaster then: with
 * BROADCAST_CONNECTION=pusher and no keys set (a development machine, a test) every request of the app would fail before it began.
 */
class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->reflash();
        }

        require base_path('routes/channels.php');

        return Broadcast::auth($request);
    }
}
