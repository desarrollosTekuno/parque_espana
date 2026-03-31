<?php

namespace App\Http\Controllers\Web\AdminClub;

use App\Http\Controllers\Controller;
use App\Models\Catalogs\DocumentType;
use App\Models\Catalogs\Relationship;
use App\Models\Memberships\MembershipType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MemberController extends Controller
{

    public function index()
    {
        //$items = Model::get();
        //return Inertia::render('Ruta/Vista', compact('items'));
    }
    public function create()
    {
        // membership types
        // exist session club_id
        $relationships = Relationship::select('id', 'name')->get();
        $membershipTypes = MembershipType::where('show_in_listing', true)
            ->with([
                'documentTypes:id,name,allowed_extensions',
                'documentTypes.relationships:id,name',
            ])
            ->where('club_id', session('club_id'))
            ->orderBy('created_at','desc')
            ->get();
        return Inertia::render('Members/Create', compact('membershipTypes', 'relationships'));
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {

        //$validated = $request->validate([
        //    'field1' => 'required|string|max:255',
        //    'field2' => 'required|email|unique:table,column',
        //]);

        //Model::create([
        //    'column' => $request->input
        //]);

        return redirect()->back()->with('success', 'Message');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //$validated = $request->validate([
        //    'field1' => 'required|string|max:255',
        //    'field2' => 'required|email|unique:table,column,' . $id,
        //]);

        //Model::where('column', $id)->update([
        //    'field1' => $validated['field1'],
        //    'field2' => $validated['field2'],
        //]);

        return redirect()->back()->with('success', 'Message');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return redirect()->back()->with('success', 'Message');
    }
}
