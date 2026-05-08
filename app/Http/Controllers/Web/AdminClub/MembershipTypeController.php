<?php

namespace App\Http\Controllers\Web\AdminClub;

use Illuminate\Routing\Controller;
use App\Models\Catalogs\DocumentType;
use App\Models\Memberships\MembershipType;
use App\Rules\ExistsInSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MembershipTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:membership-types.index')->only('index');
        $this->middleware('permission:membership-types.store')->only('store');
        $this->middleware('permission:membership-types.update')->only(['update', 'syncDocuments']);
        $this->middleware('permission:membership-types.destroy')->only('destroy');
    }

    public function index(Request $request)
    {
        try {
            $clubId = (int) ($request->club_id ?? session('club_id'));
            $driver = DB::getDriverName();
            $prefix = 'membershipTypes';

            $query = MembershipType::query()
                ->with(['documentTypes'])
                ->where('club_id', $clubId);

            if ($search = $request->input("{$prefix}_search")) {
                $like = $driver === 'pgsql' ? 'ilike' : 'like';

                $query->where(function (Builder $builder) use ($search, $like) {
                    $builder->where('name', $like, "%{$search}%")
                        ->orWhere('code', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%");
                });
            }

            $sortMap = [
                'id' => 'id',
                'code' => 'code',
                'name' => 'name',
                'created_at' => 'created_at',
            ];

            $sort = $request->input("{$prefix}_sort", 'name');
            $order = $request->input("{$prefix}_order", 'asc');
            $sortColumn = $sortMap[$sort] ?? 'name';

            $membershipTypes = $query
                ->orderBy($sortColumn, $order)
                ->paginate(
                    $request->input("{$prefix}_per_page", 10),
                    ['*'],
                    "{$prefix}_page",
                    $request->input("{$prefix}_page", 1)
                )
                ->through(fn (MembershipType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description,
                    'requires_origin_family' => (bool) $type->requires_origin_family,
                    'show_in_listing' => (bool) $type->show_in_listing,
                    'is_spanish_descent' => (bool) $type->is_spanish_descent,
                    'allows_multiple_members' => (bool) $type->allows_multiple_members,
                    'validity_months' => $type->validity_months,
                    'document_types' => $type->documentTypes->map(fn (DocumentType $doc) => [
                        'id' => $doc->id,
                        'name' => $doc->name,
                        'is_required' => (bool) $doc->pivot->is_required,
                        'allow_multiple' => (bool) $doc->pivot->allow_multiple,
                        'number_files' => (int) $doc->pivot->number_files,
                    ])->values(),
                ])
                ->appends($request->all());

            $allDocumentTypes = DocumentType::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentType $doc) => [
                    'id' => $doc->id,
                    'name' => $doc->name,
                ]);

            return Inertia::render('AdminClubs/MembershipTypes/Index', [
                'membershipTypes' => $membershipTypes,
                'allDocumentTypes' => $allDocumentTypes,
                'filters' => [
                    'search' => $request->input("{$prefix}_search"),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return Inertia::render('AdminClubs/MembershipTypes/Index', [
                'membershipTypes' => ['data' => [], 'total' => 0],
                'allDocumentTypes' => [],
                'filters' => ['search' => $request->input('membershipTypes_search')],
                'messageError' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            $clubId = (int) session('club_id');
            $validated = $this->validateType($request, $clubId);

            DB::transaction(function () use ($validated, $clubId) {
                $type = MembershipType::create(array_merge(
                    $this->typeAttributes($validated),
                    ['club_id' => $clubId]
                ));

                $this->syncDocumentTypes($type, $validated['document_types'] ?? []);
            });

            return redirect()->back()->with('success', 'Tipo de membresía creado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al crear el tipo de membresía.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, MembershipType $membershipType)
    {
        try {
            $this->ensureBelongsToCurrentClub($membershipType);

            $clubId = (int) session('club_id');
            $validated = $this->validateType($request, $clubId, $membershipType);

            DB::transaction(function () use ($membershipType, $validated) {
                $membershipType->update($this->typeAttributes($validated));
                $this->syncDocumentTypes($membershipType, $validated['document_types'] ?? []);
            });

            return redirect()->back()->with('success', 'Tipo de membresía actualizado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors(array_merge($e->errors(), [
                'messageError' => collect($e->errors())->flatten()->first() ?? 'Ocurrió un error de validación.',
                'exception' => '',
            ]));
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al actualizar el tipo de membresía.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(MembershipType $membershipType)
    {
        try {
            $this->ensureBelongsToCurrentClub($membershipType);

            $membershipType->delete();

            return redirect()->back()->with('success', 'Tipo de membresía eliminado correctamente.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->withErrors([
                'messageError' => 'Ocurrió un error al eliminar el tipo de membresía.',
                'exception' => $e->getMessage(),
            ]);
        }
    }

    protected function validateType(Request $request, int $clubId, ?MembershipType $type = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(MembershipType::class, 'code')
                    ->where('club_id', $clubId)
                    ->ignore($type?->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(MembershipType::class, 'name')
                    ->where('club_id', $clubId)
                    ->ignore($type?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'requires_origin_family' => ['required', 'boolean'],
            'show_in_listing' => ['required', 'boolean'],
            'is_spanish_descent' => ['required', 'boolean'],
            'allows_multiple_members' => ['required', 'boolean'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:999'],
            'document_types' => ['nullable', 'array'],
            'document_types.*.document_type_id' => ['required', 'integer', new ExistsInSchema('catalogs', 'document_types')],
            'document_types.*.is_required' => ['required', 'boolean'],
            'document_types.*.allow_multiple' => ['required', 'boolean'],
            'document_types.*.number_files' => ['required', 'integer', 'min:1', 'max:99'],
        ]);
    }

    protected function typeAttributes(array $validated): array
    {
        return [
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'requires_origin_family' => (bool) $validated['requires_origin_family'],
            'show_in_listing' => (bool) $validated['show_in_listing'],
            'is_spanish_descent' => (bool) $validated['is_spanish_descent'],
            'allows_multiple_members' => (bool) $validated['allows_multiple_members'],
            'validity_months' => isset($validated['validity_months']) ? (int) $validated['validity_months'] : null,
        ];
    }

    protected function syncDocumentTypes(MembershipType $type, array $documentTypes): void
    {
        $syncData = [];

        foreach ($documentTypes as $doc) {
            $syncData[$doc['document_type_id']] = [
                'is_required' => (bool) $doc['is_required'],
                'allow_multiple' => (bool) $doc['allow_multiple'],
                'number_files' => (int) $doc['number_files'],
            ];
        }

        $type->documentTypes()->sync($syncData);
    }

    protected function ensureBelongsToCurrentClub(MembershipType $type): void
    {
        if ((int) $type->club_id !== (int) session('club_id')) {
            abort(404);
        }
    }
}
