<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Club;
use App\Models\Website\ContactMessage;
use Illuminate\Http\Request;

class WebsiteContactController extends Controller
{
    public function store(Request $request, Club $club)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'subject' => ['required', 'string', 'max:150'],
                'message' => ['required', 'string'],
            ]);

            $contactMessage = ContactMessage::create([
                'club_id' => $club->id,
                'name' => $validated['name'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
            ]);

            return $this->created('Mensaje enviado correctamente.', $contactMessage);
        } catch (\Exception $e) {
            report($e);

            return $this->serverError('No se pudo enviar el mensaje.');
        }
    }
}
