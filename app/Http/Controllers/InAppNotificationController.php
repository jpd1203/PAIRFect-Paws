<?php

namespace App\Http\Controllers;

use App\Models\HandoverNotification;
use Illuminate\Http\Request;

final class InAppNotificationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeActive($request);
        $notifications = $request->user()->inAppNotifications()
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Request $request, HandoverNotification $notification)
    {
        $this->authorizeActive($request);
        $this->authorizeOwner($request, $notification);
        $this->mark($notification);

        // Redirect only to a local path. The destination's normal middleware
        // remains responsible for authorizing the underlying resource.
        $destination = (string) $notification->action_url;
        $parts = parse_url($destination);
        if (! is_array($parts)) {
            return redirect()->route('notifications.index');
        }

        $path = $parts['path'] ?? (isset($parts['host']) ? '/' : '');
        $host = strtolower($parts['host'] ?? '');
        $configuredHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $baseHost = preg_replace('/^www\./', '', $configuredHost);
        $allowedHosts = $baseHost ? [$baseHost, 'www.'.$baseHost] : [];
        $requestHost = strtolower($request->getHost());
        if ($baseHost && preg_replace('/^www\./', '', $requestHost) === $baseHost) {
            $allowedHosts[] = $requestHost;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')
            || str_starts_with($path, '/\\') || str_starts_with($destination, '//')
            || (isset($parts['scheme']) && (! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || ! isset($parts['host'])))
            || isset($parts['user']) || isset($parts['pass'])
            || ($host !== '' && ! in_array($host, $allowedHosts, true))) {
            return redirect()->route('notifications.index');
        }

        return redirect()->to($path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : ''));
    }

    public function markRead(Request $request, HandoverNotification $notification)
    {
        $this->authorizeActive($request);
        $this->authorizeOwner($request, $notification);
        $this->mark($notification);

        return back();
    }

    public function markAllRead(Request $request)
    {
        $this->authorizeActive($request);
        $request->user()->inAppNotifications()->whereNull('read_at')
            ->update(['read' => true, 'read_at' => now()]);

        return back();
    }

    private function authorizeOwner(Request $request, HandoverNotification $notification): void
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
    }

    private function authorizeActive(Request $request): void
    {
        abort_unless($request->user()->is_active, 403);
    }

    private function mark(HandoverNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read' => true, 'read_at' => now()]);
        }
    }
}
