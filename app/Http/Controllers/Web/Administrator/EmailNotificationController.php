<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Models\Administrator\Club;
use App\Models\Notifications\EmailConfig;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationAttachment;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationStatusCatalog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $clubIds = Auth::user()->clubs()->pluck('clubs.id');
        $clubs = Club::query()->whereIn('id', $clubIds)->get();

        return Inertia::render('Administrator/EmailNotifications/Index', [
            'email_notifications' => $notifications,
            'clubs' => $clubs,
            'email_configs' => EmailConfig::query()
                ->select('id', 'entity_id', 'profile_name', 'from_address', 'is_active')
                ->where('is_active', true)
                ->whereIn('entity_id', $clubIds)
                ->orderBy('profile_name')
                ->get(),
        ]);
    }

    private function getEmailNotifications(Request $request) {
        $driver = DB::getDriverName();
        $prefix = 'email_notifications';
        $sessionClubId = session('club_id');
        $requestedClubId = $request->input("{$prefix}_club_id", $request->input('club_id'));

        $query = Notification::query()
            ->with(['creator:id,name', 'status:id,name,code', 'club:id,name'])
            ->withCount('recipients')
            ->whereHas('channel', function ($channelQuery) {
                $channelQuery->where('code', 'email');
            })
            ->where(function ($historyQuery) {
                $historyQuery->whereNotNull('sent_date')
                    ->orWhereHas('status', function ($statusQuery) {
                        $statusQuery->where('code', 'sent');
                    });
            });

        if ($requestedClubId > 0) {
            $query->where('club_id', $requestedClubId);
        } elseif ($sessionClubId > 0) {
            $query->where('club_id', $sessionClubId);
        }

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

        $allowedSorts = ['id', 'title', 'subject', 'created_at', 'sent_date'];
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
            'send_type' => ['nullable', 'in:now,scheduled'],
            'scheduled_date' => ['nullable'],
            'scheduled_time' => ['nullable'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file'],
        ]);

        $statusCode = 'sent';
        $channel = NotificationChannel::query()->where('code', 'email')->first();

        if (isset($validated['send_type']) && $validated['send_type'] === 'scheduled') {
            $statusCode = 'scheduled';
        }

        $status = NotificationStatusCatalog::query()->where('code', $statusCode)->first();

        DB::transaction(function () use ($request, $validated, $channel, $status) {
            $notificationUuid = (string) Str::uuid();
            $isScheduled = ($validated['send_type'] ?? null) === 'scheduled';

            $notification = Notification::create([
                'uuid' => $notificationUuid,
                'title' => $validated['title'],
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'type' => 0,
                'channel_id' => $channel->id,
                'status_id' => $status->id,
                'club_id' => $validated['club_id'] ?? null,
                'scheduled_date' => $isScheduled ? ($validated['scheduled_date'] ?? null) : null,
                'scheduled_time' => $isScheduled ? ($validated['scheduled_time'] ?? null) : null,
                'sent_date' => $isScheduled ? null : now()->toDateString(),
                'sent_time' => $isScheduled ? null : now()->toTimeString(),
                'created_by' => Auth::id(),
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store("Notificaciones/Emails/{$notificationUuid}", 'public');

                NotificationAttachment::create([
                    'notification_id' => $notification->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Correo registrado con exito.');
    }

    public function recipientsPreview(Request $request) {
        $validated = $request->validate([
            'scope' => ['required', 'in:all,by_club'],
            'club_id' => ['nullable', 'integer'],
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
}
