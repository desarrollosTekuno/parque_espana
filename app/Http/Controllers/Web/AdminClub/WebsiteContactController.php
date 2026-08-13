<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Models\Website\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class WebsiteContactController extends Controller {

    public function __construct(){
        $this->middleware('permission:website-contacts.index')->only('index');
    }

    public function index(Request $request) {
        $search = trim((string) $request->input('search', ''));

        $query = ContactMessage::where('club_id', session('club_id'));

        if ($search != '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('subject', 'ilike', "%{$search}%")
                    ->orWhere('message', 'ilike', "%{$search}%");
            });
        }

        $messages = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('AdminClubs/WebsiteContacts/Index', [
            'messages' => $messages,
            'search' => $search,
        ]);
    }
}
