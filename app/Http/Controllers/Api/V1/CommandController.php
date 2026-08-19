<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommandResource;
use App\Models\Devices\Command;
use App\Rules\ExistsInSchema;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;


class CommandController extends Controller {

    public function index() {
        //$items = Model::get();
        //return Inertia::render('Ruta/Index', compact('items'));
    }

    public function store(Request $request)
    {
        try {
            $action = $request->input('action');

            $rules = [
                'action'             => 'required|string|max:30',
                'account_member_id'  => ['required', new ExistsInSchema('memberships', 'account_members', 'id')],
                'device_id'          => ['required', new ExistsInSchema('devices', 'devices', 'id')],
                'data'               => 'required|array',
            ];

            switch ($action) {
                case 'create_user':
                case 'update_user':
                    $rules += [
                        'data.users'                 => 'required|array|min:1',
                        'data.users.*.employee_id'    => 'required|string',
                        'data.users.*.name'           => 'required|string',
                        'data.users.*.user_type'      => 'required|string|in:normal,visitor',
                        'data.users.*.is_active'      => 'required|boolean',
                        'data.users.*.is_permanent'   => 'required|boolean',
                        'data.users.*.valid_from'     => 'required|date_format:Y-m-d H:i:s',
                        'data.users.*.valid_to'       => 'required|date_format:Y-m-d H:i:s|after:data.users.*.valid_from',
                    ];
                    break;

                case 'delete_user':
                    $rules += [
                        'data.users'                => 'required|array|min:1',
                        'data.users.*.employee_id'   => 'required|string',
                    ];
                    break;

                case 'create_card':
                case 'update_card':
                    $rules += [
                        'data.cards'                 => 'required|array|min:1',
                        'data.cards.*.employee_id'    => 'required|string',
                        'data.cards.*.card_no'        => 'required|string|max:32',
                    ];
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'La acción especificada no es válida',
                    ], 422);
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payload = $this->buildPayload($action, $request->input('data'));

            $command = Command::create([
                'action'             => $action,
                'status'             => 'pending',
                'device_id'          => $request->input('device_id'),
                'account_member_id'  => $request->input('account_member_id'),
                'data'               => $payload,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comando creado correctamente',
                'data' => $command
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al crear el comando',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    private function buildPayload(string $action, array $data): array
    {
        if (in_array($action, ['create_user', 'update_user'])) {
            $users = collect($data['users'])->map(function ($user) {
                return [
                    'employee_id'  => $user['employee_id'],
                    'name'         => $user['name'],
                    'user_type'    => $user['user_type'],
                    'is_active'    => $user['is_active'],
                    'is_permanent' => $user['is_permanent'],
                    'valid_from'   => Carbon::parse($user['valid_from'])->format('Y-m-d\TH:i:s'),
                    'valid_to'     => Carbon::parse($user['valid_to'])->format('Y-m-d\TH:i:s'),
                ];
            });
                    
            return ['users' => $users->toArray()];
        }
                    
        if ($action === 'delete_user') {
            return ['users' => collect($data['users'])->map(fn($u) => [
                'employee_id' => $u['employee_id'],
            ])->toArray()];
        }
                    
        if (in_array($action, ['create_card', 'update_card'])) {
            return ['cards' => collect($data['cards'])->map(fn($c) => [
                'employee_id' => $c['employee_id'],
                'card_no'     => $c['card_no'],
            ])->toArray()];
        }
                    
        return $data;
    }

    public function show(Request $request)
    {
        try
        {
            $validator = Validator::make($request->query(), [
                'ip'        => 'required|string',
                'club_code' => ['required', new ExistsInSchema('clubs', 'clubs', 'code')],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $commands = Command::whereIn('status', ['pending', 'error'])
                ->where('attempts', '<', 5)
                ->whereHas('device', function ($query) use ($request) {
                    $query->where('ip', $request->query('ip'))
                        ->whereHas('club', function ($q) use ($request) {
                             $q->where('code', $request->query('club_code'));
                         });
                })
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Comandos obtenidos correctamente',
                'data' => CommandResource::collection($commands)
            ], 200);

        }catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrio un error al obtener los comandos del dispositivo',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, Command $command)
    {
        $validator = Validator::make($request->all(), [
            'status'        => 'required|string|in:pending,processing,completed,error',
            'error_message' => 'nullable|string|required_if:status,error',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $command->status = $validated['status'];
        $command->error_message = $validated['error_message'] ?? null;

        // solo incrementamos intentos si fue error (indica que se intentó y falló)
        if ($validated['status'] === 'error') {
            $command->attempts++;
        }

        // solo marcamos processed_at cuando ya terminó (completado o error definitivo)
        if (in_array($validated['status'], ['completed', 'error'])) {
            $command->processed_at = now();
        }

        $command->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'data' => new CommandResource($command)
        ]);
    }
}
