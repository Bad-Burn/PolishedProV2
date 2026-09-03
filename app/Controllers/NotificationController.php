<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('pages/notifications', [
            'title'         => 'Notifications',
            'notifications' => Notification::forUser((int) Auth::id(), 50),
        ]);
    }

    public function markRead(Request $request): never
    {
        Notification::markRead($request->intParam('id'), (int) Auth::id());
        $this->back();
    }

    public function markAllRead(Request $request): never
    {
        $count = Notification::markAllRead((int) Auth::id());

        if ($request->wantsJson()) {
            $this->json(['marked' => $count]);
        }

        $this->back();
    }

    public function destroy(Request $request): never
    {
        Notification::delete($request->intParam('id'), (int) Auth::id());
        $this->success('Notification removed.', '/notifications');
    }
}
