<?php

namespace App\Traits;

use App\Models\Feedback\Attachment;
use App\Models\Feedback\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

trait HandlesFeedbackTickets {

    protected function createTicketNumber(int $clubId, int $maxAttempts = 3): ?string {
        $clubPrefix = 'C' . $clubId;
        $year = now()->format('y');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastTicket = Ticket::where('club_id', $clubId)
                ->whereYear('ticket_date', now()->year)
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = $lastTicket ? ((int) substr($lastTicket->ticket_number, -5)) + 1 : 1;
            $candidate = 'FB-' . $clubPrefix . '-' . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            if (!Ticket::where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return null;
    }

    protected function storeTicketAttachments(Ticket $ticket, array $files): void {
        $ticketFolder = 'Feedback/Tickets/' . $ticket->ticket_number;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store($ticketFolder, 'public');

            Attachment::create([
                'ticket_id' => $ticket->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'storage_disk' => 'public',
                'uploaded_by_user_id' => Auth::id(),
            ]);
        }
    }

    protected function getAttachmentFiles(Request $request): array {
        $files = $request->file('attachments', []);

        if ($files === null) {
            return [];
        }

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
    }
}
