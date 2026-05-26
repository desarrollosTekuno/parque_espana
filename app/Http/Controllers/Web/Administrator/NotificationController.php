<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Exports\NotificationsExport;
use App\Jobs\SendEmailNotificationJob;
use App\Models\Notifications\EmailConfig;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationAttachment;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationRecipient;
use App\Models\Notifications\NotificationStatusCatalog;
use App\Models\Members\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class NotificationController extends Controller {

    public function __construct() {
        $this->middleware('permission:notifications.index')->only('index');
        $this->middleware('permission:notifications.store')->only('store');
        $this->middleware('permission:notifications.update')->only('update');
        $this->middleware('permission:notifications.destroy')->only('destroy');
        $this->middleware('permission:notifications.store')->only('recipientsPreview');
        $this->middleware('permission:notifications.update')->only('cancel');
        $this->middleware('permission:notifications.index')->only('export');
    }

    public function index(Request $request) {
        $club_id = session('club_id');
        $notifications = $this->getNotifications($request);
        $channels = NotificationChannel::get();

        return Inertia::render('Administrator/Notifications/Index', compact('notifications', 'channels', 'club_id'));
    }

    private function getNotifications(Request $request) {
        $driver = DB::getDriverName();
        $prefix = 'notifications';
        $sessionClubId = (int) session('club_id');
        $requestedClubId = $request->input("{$prefix}_club_id", $request->input('club_id'));
        $clubIds = Auth::user()->clubs()->pluck('clubs.id');

        $query = Notification::query()
            ->with(['creator:id,name', 'status:id,name,code', 'club:id,name', 'deliveryLogs'])
            ->withCount('recipients')
            ->whereHas('channel', function ($channelQuery) {
                $channelQuery->where('code', 'email');
            })
            ->whereIn('club_id', $clubIds);

        if ($requestedClubId > 0) {
            $query->where('club_id', $requestedClubId);
        } elseif ($sessionClubId > 0) {
            $query->where('club_id', $sessionClubId);
        }

        if ($search = $request->input("{$prefix}_search")) {
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($subQuery) use ($search, $operator) {
                $subQuery->where('title', $operator, "%{$search}%")
                    ->orWhere('body', $operator, "%{$search}%");
            });
        }

        $type = $request->input("{$prefix}_type");
        if ($type !== null && $type !== '') {
            $query->where('type', (int) $type);
        }

        $dateFrom = $request->input("{$prefix}_date_from");
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $request->input("{$prefix}_date_to");
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sort = $request->input("{$prefix}_sort", 'id');
        $order = $request->input("{$prefix}_order", 'desc');

        $allowedSorts = ['id', 'title', 'created_at', 'sent_date'];
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
            'body' => ['required', 'string'],
            'scope' => ['required', 'in:I,G'],
            'send_type' => ['nullable', 'in:now,scheduled'],
            'scheduled_date' => ['nullable'],
            'scheduled_time' => ['nullable'],
            'selected_recipient_ids' => ['nullable', 'array'],
            'selected_recipient_ids.*' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file'],
            'channels_to_send' => ['required', 'array'],
            'channels_to_send.*' => ['required', 'string'],
        ]);

        $channelsToSend = $validated['channels_to_send'];

        $clubId = session('club_id');
        $statusCode = 'pending';
        $channel = NotificationChannel::query()->where('code', 'email')->first();

        if (isset($validated['send_type']) && $validated['send_type'] === 'scheduled') {
            $statusCode = 'scheduled';
        }

        $status = NotificationStatusCatalog::query()->where('code', $statusCode)->first();

        $notification = null;
        $isScheduled = false;

        DB::transaction(function () use ($request, $validated, $channel, $status, $clubId, &$notification, &$isScheduled) {
            $notificationUuid = (string) Str::uuid();
            $isScheduled = ($validated['send_type'] ?? null) === 'scheduled';

            $notification = Notification::create([
                'uuid' => $notificationUuid,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'scope' => $validated['scope'],
                'type' => 0,
                'channel_id' => $channel->id,
                'status_id' => $status->id,
                'club_id' => $clubId,
                'scheduled_date' => $isScheduled ? ($validated['scheduled_date'] ?? null) : null,
                'scheduled_time' => $isScheduled ? ($validated['scheduled_time'] ?? null) : null,
                'sent_date' => null,
                'sent_time' => null,
                'created_by' => Auth::id(),
            ]);

            $this->saveNotificationHistory($notification, $request, $isScheduled);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store("Notificaciones/Emails/{$notificationUuid}", 's3');

                NotificationAttachment::create([
                    'notification_id' => $notification->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        });

        if (!$isScheduled && $notification) {
            $this->dispatchEmailNotification($notification, $channelsToSend);
        }

        return redirect()->back()->with('success', 'Correo registrado con exito.');
    }

    private function dispatchEmailNotification(Notification $notification, array $channelsToSend) {
        $sendEmail = false;

        foreach ($channelsToSend as $channel) {
            if ($channel === 'email') {
                $sendEmail = true;
            }

            if ($channel === 'push') {

            }
        }

        if ($sendEmail) {
            SendEmailNotificationJob::dispatch($notification->id);
        }
    }

    public function cancel($id) {
        $notification = Notification::with('status')->findOrFail($id);

        if (!in_array($notification->status?->code, ['scheduled', 'pending'])) {
            return back()->withErrors(['cancel' => 'Solo se pueden cancelar notificaciones programadas o pendientes.']);
        }

        $cancelledStatus = NotificationStatusCatalog::query()->where('code', 'cancelled')->first();
        $notification->update(['status_id' => $cancelledStatus?->id]);

        return redirect()->back()->with('success', 'Notificacion cancelada.');
    }

    public function export(Request $request) {
        $filename = 'notificaciones-correo-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new NotificationsExport($request), $filename);
    }

    private function saveNotificationHistory(Notification $notification, Request $request, bool $isScheduled) {
        $scope = $request->input('scope', 'G');
        $status = $isScheduled ? 'scheduled' : 'pending';
        $sentAt = null;
        $selectedRecipientIds = $request->input('selected_recipient_ids', []);

        $membersQuery = Member::query()
            ->whereHas('accountMemberships.membershipAccount.memberships', function ($membershipQuery) use ($notification) {
                $membershipQuery->where('club_id', $notification->club_id)
                    ->where('status', 'active');
            })
            ->with('user:id,email');

        if ($scope === 'I') {
            if (!is_array($selectedRecipientIds) || empty($selectedRecipientIds)) {
                return;
            }

            $membersQuery->whereIn('id', $selectedRecipientIds);
        }

        $members = $membersQuery->get();

        foreach ($members as $member) {
            $destination = $member->user?->email ?: $member->email;

            if ($destination) {
                NotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'user_id' => $member->user_id,
                    'destination' => $destination,
                    'status' => $status,
                    'sent_at' => $sentAt,
                ]);
            }
        }

    }

    public function recipientsPreview(Request $request) {
        $validated = $request->validate([
            'scope' => ['required', 'in:by_club'],
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

    public function getMembers(Request $request) {
        $clubId = (int) $request->input('club_id', session('club_id'));

        if (!$clubId) {
            return [
                'total' => 0,
                'recipients' => [],
            ];
        }

        $data = Member::query()
            ->select('id', 'first_name', 'last_name', 'second_last_name', 'email', 'user_id')
            ->whereHas('user', function ($userQuery) {
                $userQuery->whereNotNull('email')
                    ->where('email', '<>', '');
            })
            ->whereHas('accountMemberships.membershipAccount.memberships', function ($membershipQuery) use ($clubId) {
                $membershipQuery->where('club_id', $clubId)
                    ->where('status', 'active');
            })
            ->with('user:id,email')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $recipients = $data->map(function (Member $member) {
            $destination = $member->user?->email;

            return [
                'id' => $member->id,
                'name' => trim(collect([$member->first_name, $member->last_name, $member->second_last_name])->filter()->implode(' ')),
                'email' => $destination,
            ];
        })->values();

        return [
            'total' => $recipients->count(),
            'recipients' => $recipients,
        ];
    }
}
