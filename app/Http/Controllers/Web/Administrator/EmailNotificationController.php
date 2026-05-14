<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Administrator\Club;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationStatusCatalog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmailNotificationController extends Controller {

    public function __construct() {
        $this->middleware('permission:email-notifications.index')->only('index');
        $this->middleware('permission:email-notifications.store')->only('store');
        $this->middleware('permission:email-notifications.update')->only('update');
        $this->middleware('permission:email-notifications.destroy')->only('destroy');
        $this->middleware('permission:email-notifications.store')->only('recipientsPreview');
    }

    public function index(Request $request) {
        $notifications = $this->getEmailNotifications($request);
        $clubs = Club::get();

        return Inertia::render('Administrator/EmailNotifications/Index', [
            'email_notifications' => $notifications,
            'clubs' => $clubs,
        ]);
    }

    private function getEmailNotifications(Request $request) {
        $driver = DB::getDriverName();
        $prefix = 'email_notifications';

        $query = Notification::query()
            ->with(['creator:id,name', 'status:id,name,code', 'club:id,name'])
            ->withCount('recipients')
            ->whereHas('channel', function ($channelQuery) {
                $channelQuery->where('code', 'email');
            })
            ->where(function ($historyQuery) {
                $historyQuery->whereNotNull('sent_at')
                    ->orWhereHas('status', function ($statusQuery) {
                        $statusQuery->where('code', 'sent');
                    });
            });

        if ($search = $request->input("{$prefix}_search")) {
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($subQuery) use ($search, $operator) {
                $subQuery->where('title', $operator, "%{$search}%")
                    ->orWhere('subject', $operator, "%{$search}%")
                    ->orWhere('body', $operator, "%{$search}%");
            });
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $allowedSorts = ['id', 'title', 'subject', 'created_at', 'sent_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $query->orderBy($sort, $order === 'asc' ? 'asc' : 'desc');

        return $query->paginate(
            $request->input("{$prefix}_per_page", 10),
            ['*'],
            "{$prefix}_page"
        )->appends($request->all());
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'club_id' => ['nullable', 'integer'],
        ]);

        $channel = NotificationChannel::query()->where('code', 'email')->first();
        $status = NotificationStatusCatalog::query()->where('code', 'scheduled')->first();

        if (!$channel || !$status) {
            return redirect()->back()->withErrors([
                'messageError' => 'No existe la configuracion base de canales o estatus.',
                'exception' => '',
            ]);
        }

        Notification::create([
            'title' => $validated['title'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'type' => 0,
            'channel_id' => $channel->id,
            'status_id' => $status->id,
            'club_id' => $validated['club_id'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Correo registrado con exito.');
    }

    public function recipientsPreview(Request $request) {
        $validated = $request->validate([
            'scope' => ['required', 'in:all,by_club'],
            'club_id' => ['nullable', 'integer', 'exists:clubs.clubs,id'],
        ]);

        $query = User::query()
            ->select('id', 'name', 'email')
            ->whereNotNull('email')
            ->where('email', '<>', '');

        if ($validated['scope'] === 'by_club') {
            if (empty($validated['club_id'])) {
                return response()->json([
                    'count' => 0,
                    'items' => [],
                ]);
            }

            $clubId = (int) $validated['club_id'];

            $query->whereHas('clubs', function ($clubQuery) use ($clubId) {
                $clubQuery->where('clubs.clubs.id', $clubId);
            });
        }

        $totalCount = (clone $query)->count();
        $items = $query->orderBy('name')->limit(200)->get();

        return response()->json([
            'count' => $totalCount,
            'items' => $items,
        ]);
    }

    public function update(Request $request, Notification $email_notification) {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'club_id' => ['nullable', 'integer', 'exists:clubs.clubs,id'],
        ]);

        $email_notification->update([
            'title' => $validated['title'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'club_id' => $validated['club_id'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Correo actualizado con exito.');
    }

    public function destroy(Notification $email_notification) {
        $email_notification->delete();

        return redirect()->back()->with('success', 'Correo eliminado con exito.');
    }
}
