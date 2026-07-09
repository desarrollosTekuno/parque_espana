<?php

namespace Database\Seeders;

use App\Models\Catalogs\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $documentTypes = [
            [
                'code' => 'acta_nacimiento_espanola',
                'name' => 'Acta de Nacimiento Española',
                'description' => 'Documento oficial que acredita el nacimiento de una persona en España.',
                'allowed_extensions' => 'pdf',
            ],
            // acta de matrimonio
            [
                'code' => 'acta_matrimonio',
                'name' => 'Acta de Matrimonio o Concubinato',
                'description' => 'Documento oficial que acredita el matrimonio o concubinato entre dos personas.',
                'allowed_extensions' => 'pdf',
            ],
            // acta de nacimiento
            [
                'code' => 'acta_nacimiento',
                'name' => 'Acta de Nacimiento',
                'description' => 'Documento oficial que acredita el nacimiento de una persona.',
                'allowed_extensions' => 'pdf',
            ],
            //    fotografias tamaño infantil
            [
                'code' => 'fotografia_infantil',
                'name' => 'Fotografía Infantil',
                'description' => 'Fotografía reciente de tamaño infantil, utilizada para trámites oficiales.',
                'allowed_extensions' => 'jpg,png',

            ],
            // constancia de trabajo
            [
                'code' => 'constancia_trabajo',
                'name' => 'Constancia de Trabajo',
                'description' => 'Documento que acredita la relación laboral entre un empleado y su empleador.',
                'allowed_extensions' => 'pdf',

            ],
            // constrancia de estudios
            [
                'code' => 'constancia_estudios',
                'name' => 'Constancia de Estudios',
                'description' => 'Documento que acredita la inscripción o finalización de estudios en una institución educativa.',
                'allowed_extensions' => 'pdf',
            ],
            // carta de recomendacion
            [
                'code' => 'carta_recomendacion',
                'name' => 'Carta de Recomendación',
                'description' => 'Documento que respalda las habilidades y cualidades de una persona, emitido por un tercero.',
                'allowed_extensions' => 'pdf',

            ],
            // ine
            [
                'code' => 'ine',
                'name' => 'INE',
                'description' => 'Documento oficial de identificación emitido por el Instituto Nacional Electoral en México.',
                'allowed_extensions' => 'pdf,jpg,png',
                'min_age' => 18, // Solo se requiere para mayores de edad
            ],
            // comprobante de domicilio
            [
                'code' => 'comprobante_domicilio',
                'name' => 'Comprobante de Domicilio',
                'description' => 'Documento que acredita la residencia de una persona en un lugar específico.',
                'allowed_extensions' => 'pdf,jpg,png',
            ],
            [
                'code' => 'carta_baja',
                'name' => 'Carta de Baja',
                'description' => 'Carta firmada por el titular solicitando la baja de la cuenta.',
                'allowed_extensions' => 'pdf,jpg,png',
            ],
            [
                'code' => 'documento_permiso_ausencia',
                'name' => 'Documento de Permiso por Ausencia',
                'description' => 'Documento que respalda la solicitud de permiso por ausencia del titular.',
                'allowed_extensions' => 'pdf,jpg,png',
            ],
            // carta de no adeudo
            [
                'code' => 'carta_no_adeudo',
                'name' => 'Carta de No Adeudo',
                'description' => 'Documento que certifica que una persona no tiene deudas pendientes con una entidad.',
                'allowed_extensions' => 'pdf',
            ],
            // carta doctor activo
            [
                'code' => 'carta_doctor_activo',
                'name' => 'Carta de Doctor Activo',
                'description' => 'Documento que certifica que un doctor está activo en la Beneficencia Española.',
                'allowed_extensions' => 'pdf',
            ],
            // recibo pe1 sin adeudo
            [
                'code' => 'recibo_pe1_sin_adeudo',
                'name' => 'Recibo del Parque España I sin Adeudo',
                'description' => 'Documento que certifica que una persona no tiene adeudos pendientes con el Parque España I.',
                'allowed_extensions' => 'pdf',
            ],
            // acta de divorcio en separacion
            [
                'code' => 'acta_divorcio',
                'name' => 'Acta de Divorcio',
                'description' => 'Documento que respalda la separación del cónyuge por divorcio.',
                'allowed_extensions' => 'pdf,jpg,png',
            ],
        ];

        foreach ($documentTypes as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'description' => $type['description'], 'allowed_extensions' => $type['allowed_extensions'], 'min_age' => $type['min_age'] ?? null]
            );
        }
    }
}
