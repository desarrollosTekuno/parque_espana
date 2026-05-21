<?php

namespace App\Http\Controllers\Web\Administrator;

use App\Exports\EmailNotificationsExport;
use App\Jobs\SendEmailNotificationJob;
use App\Models\Notifications\EmailConfig;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationAttachment;
use App\Models\Notifications\NotificationChannel;
use App\Models\Notifications\NotificationRecipient;
use App\Models\Notifications\NotificationStatusCatalog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class EmailNotificationController extends Controller {

    public function __construct() {
        $this->middleware('permission:email-notifications.index')->only('index');
        $this->middleware('permission:email-notifications.store')->only('store');
        $this->middleware('permission:email-notifications.update')->only('update');
        $this->middleware('permission:email-notifications.destroy')->only('destroy');
        $this->middleware('permission:email-notifications.store')->only('recipientsPreview');
        $this->middleware('permission:email-notifications.update')->only('cancel');
        $this->middleware('permission:email-notifications.index')->only('export');
    }

    public function index(Request $request) {
        $notifications = $this->getEmailNotifications($request);
        $club_id = session('club_id');

        return Inertia::render('Administrator/EmailNotifications/Index', [
            'email_notifications' => $notifications,
            'club_id' => $club_id,
        ]);
    }

    private function getEmailNotifications(Request $request) {
        $driver = DB::getDriverName();
        $prefix = 'email_notifications';
        $sessionClubId = (int) session('club_id');
        $requestedClubId = $request->input("{$prefix}_club_id", $request->input('club_id'));
        $clubIds = Auth::user()->clubs()->pluck('clubs.id');

        $query = Notification::query()
            ->with(['creator:id,name', 'status:id,name,code', 'club:id,name', 'emailLogs.emailConfig:id,profile_name,from_address,host'])
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
            'individual_email' => ['nullable', 'email', 'max:255'],
            'send_type' => ['nullable', 'in:now,scheduled'],
            'scheduled_date' => ['nullable'],
            'scheduled_time' => ['nullable'],
            'selected_recipient_ids' => ['nullable', 'array'],
            'selected_recipient_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file'],
        ]);

        $clubId = session('club_id');

        if (!$clubId) {
            return back()->withErrors(['club_id' => 'No hay un club activo en la sesion.']);
        }

        $emailConfig = EmailConfig::query()
            ->where('entity_id', $clubId)
            ->where('is_active', true)
            ->first();

        if (!$emailConfig) {
            return back()->withErrors(['email_config' => 'El club activo no tiene un SMTP activo configurado.']);
        }

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

        if (!$isScheduled && $notification) {
            SendEmailNotificationJob::dispatch($notification->id);
        }

        return redirect()->back()->with('success', 'Correo registrado con exito.');
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

        return Excel::download(new EmailNotificationsExport($request), $filename);
    }

    private function saveNotificationHistory(Notification $notification, Request $request, bool $isScheduled) {
        $scope = $request->input('scope', 'G');
        $status = $isScheduled ? 'scheduled' : 'pending';
        $sentAt = null;

        if ($scope === 'I') {
            $email = (string) $request->input('individual_email', '');
            if ($email !== '') {
                NotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'destination' => $email,
                    'status' => $status,
                    'sent_at' => $sentAt,
                ]);
            }
        } else {
            $selectedRecipientIds = $request->input('selected_recipient_ids', []);
            if (is_array($selectedRecipientIds)) {
                $users = User::query()
                    ->whereIn('id', $selectedRecipientIds)
                    ->whereNotNull('email')
                    ->where('email', '<>', '')
                    ->whereHas('clubs', function ($clubQuery) use ($notification) {
                        $clubQuery->where('clubs.clubs.id', $notification->club_id);
                    })
                    ->get();

                foreach ($users as $user) {
                    NotificationRecipient::create([
                        'notification_id' => $notification->id,
                        'user_id' => $user->id,
                        'destination' => $user->email,
                        'status' => $status,
                        'sent_at' => $sentAt,
                    ]);
                }
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
        $clubId = session('club_id');

        if (!$clubId) {
            return [
                'total' => 0,
                'recipients' => [],
            ];
        }

        $data = User::query()
            ->select('id', 'name', 'email')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereHas('clubs', function ($clubQuery) use ($clubId) {
                $clubQuery->where('clubs.clubs.id', $clubId);
            })
            ->orderBy('name')
            ->get();

        return [
            'total' => $data->count(),
            'recipients' => $data,
        ];
    }
}
