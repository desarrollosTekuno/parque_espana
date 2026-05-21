<?php

namespace App\Exports;

use App\Models\Notifications\Notification;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmailNotificationsExport implements WithMultipleSheets
{
    public function __construct(
        protected Request $request,
    ) {}

    public function sheets(): array
    {
        $notifications = $this->getNotifications();

        return [
            new NotificationsSheet($notifications),
            new RecipientsSheet($notifications->pluck('id')),
        ];
    }

    private function getNotifications()
    {
        $driver = DB::getDriverName();
        $prefix = 'email_notifications';
        $sessionClubId = (int) session('club_id');
        $requestedClubId = $this->request->input("{$prefix}_club_id", $this->request->input('club_id'));
        $clubIds = Auth::user()->clubs()->pluck('clubs.id');

        $query = Notification::query()
            ->with(['creator:id,name', 'status:id,name,code', 'club:id,name'])
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

        if ($search = $this->request->input("{$prefix}_search")) {
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($subQuery) use ($search, $operator) {
                $subQuery->where('title', $operator, "%{$search}%")
                    ->orWhere('body', $operator, "%{$search}%");
            });
        }

        $type = $this->request->input("{$prefix}_type");
        if ($type !== null && $type !== '') {
            $query->where('type', (int) $type);
        }

        $dateFrom = $this->request->input("{$prefix}_date_from");
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $this->request->input("{$prefix}_date_to");
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->orderBy('id', 'desc')->get();
    }
}

class NotificationsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected $notifications) {}

    public function title(): string { return 'Notificaciones'; }

    public function headings(): array
    {
        return [
            'ID',
            'Titulo',
            'Alcance',
            'Tipo',
            'Estado',
            'Parque',
            'Creador',
            'Fecha creacion',
            'Programada para',
            'Enviada el',
            'Destinatarios',
        ];
    }

    public function collection()
    {
        return $this->notifications->map(fn ($n) => [
            $n->id,
            $n->title,
            $n->scope === 'I' ? 'Individual' : 'General',
            $n->type === 0 ? 'Manual' : 'Automatica',
            $n->status?->name ?? '-',
            $n->club?->name ?? '-',
            $n->creator?->name ?? '-',
            $n->created_at?->format('d/m/Y H:i'),
            $n->scheduled_date ? ($n->scheduled_date . ' ' . ($n->scheduled_time ?? '')) : '-',
            $n->sent_date ? ($n->sent_date . ' ' . ($n->sent_time ?? '')) : '-',
            $n->recipients_count,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

class RecipientsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(protected $notificationIds) {}

    public function title(): string { return 'Destinatarios'; }

    public function headings(): array
    {
        return [
            'Notificacion ID',
            'Titulo',
            'Email',
            'Estado',
            'Enviado el',
            'Error',
        ];
    }

    public function collection()
    {
        return DB::table('notification_recipients')
            ->join('notifications', 'notification_recipients.notification_id', '=', 'notifications.id')
            ->whereIn('notification_recipients.notification_id', $this->notificationIds)
            ->orderBy('notification_recipients.notification_id')
            ->select('notification_recipients.*', 'notifications.title')
            ->get()
            ->map(fn ($r) => [
                $r->notification_id,
                $r->title,
                $r->destination,
                match ($r->status) {
                    'sent' => 'Enviado',
                    'failed' => 'Fallido',
                    'pending' => 'Pendiente',
                    'scheduled' => 'Programado',
                    default => $r->status,
                },
                $r->sent_at ?? '-',
                $r->error_message ?? '-',
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
