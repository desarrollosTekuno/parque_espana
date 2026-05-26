<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$outputDir = __DIR__ . '/../storage/app/templates';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F4E78'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];

$exampleStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FFF2CC'],
    ],
    'alignment' => [
        'wrapText' => true,
    ],
];

$noteStyle = [
    'font' => [
        'italic' => true,
        'color' => ['rgb' => '666666'],
    ],
    'alignment' => [
        'wrapText' => true,
    ],
];

$readme = $spreadsheet->createSheet();
$readme->setTitle('README');
$readme->setCellValue('A1', 'Plantilla de migracion de usuarios y cobranza');
$readme->setCellValue('A3', 'Instrucciones generales');

$instructions = [
    'Entregar la informacion con un corte definido y validado por ambos parques.',
    'No usar N/A, -, SIN DATO o valores similares. Si el dato no existe, dejar la celda vacia.',
    'No cambiar los encabezados de las hojas.',
    'Conservar los IDs legacy exactamente como existen en el sistema origen.',
    'Usar fechas con formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS.',
    'Si un titular existe en PE1 y PE2, documentarlo en la hoja 10_vinculos_entre_parques.',
    'Las filas de ejemplo pueden borrarse antes de la entrega final.',
];

$row = 4;
foreach ($instructions as $index => $instruction) {
    $readme->setCellValue("A{$row}", ($index + 1) . '. ' . $instruction);
    $row++;
}

$readme->setCellValue('A13', 'Orden recomendado de entrega');
$delivery = [
    '01_personas',
    '02_cuentas_pe1',
    '03_cuentas_pe2',
    '04_integrantes_por_cuenta',
    '05_membresias',
    '06_saldos_pendientes',
    '10_vinculos_entre_parques',
    '07_pagos_historicos',
    '08_aplicacion_pagos',
    '09_equivalencias_catalogos',
];

$row = 14;
foreach ($delivery as $sheetName) {
    $readme->setCellValue("A{$row}", $sheetName);
    $row++;
}

$readme->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$readme->getStyle('A3:A13')->getFont()->setBold(true);
$readme->getColumnDimension('A')->setWidth(110);
$readme->getStyle('A1:A25')->getAlignment()->setWrapText(true);

$sheets = [
    '01_personas' => [
        'headers' => ['legacy_person_id','first_name','last_name','second_last_name','birthdate','sex','curp','rfc','email','phone','birth_place','city','state','nationality','marital_status','occupation','school_name','address_street','address_neighborhood','address_postal_code','address_country'],
        'example' => ['P-10234','Juan','Perez','Lopez','1985-04-10','M','PELJ850410HJCXXX09','PELJ850410AB1','juan@mail.com','3312345678','Guadalajara','Guadalajara','Jalisco','Mexicana','Casado','Contador','ITESO','Av. Patria 123','Providencia','44630','Mexico'],
        'note' => 'Incluir a todas las personas relacionadas con cuentas activas o con saldo vigente.',
    ],
    '02_cuentas_pe1' => [
        'headers' => ['legacy_account_id','membership_number','legacy_primary_holder_id','account_status','account_type','opened_at','notes'],
        'example' => ['PE1-ACC-9001','PE1-000123','P-10234','active','individual','2018-02-01','Cuenta reactivada'],
        'note' => 'Una fila por cuenta del parque Espana 1.',
    ],
    '03_cuentas_pe2' => [
        'headers' => ['legacy_account_id','membership_number','legacy_primary_holder_id','account_status','account_type','opened_at','notes'],
        'example' => ['PE2-ACC-2001','PE2-000456','P-10234','active','individual','2021-05-15','Pase mensual'],
        'note' => 'Una fila por cuenta del parque Espana 2.',
    ],
    '04_integrantes_por_cuenta' => [
        'headers' => ['park_code','legacy_account_id','legacy_person_id','relationship','is_primary_holder','joined_at','left_at'],
        'example' => ['PE1','PE1-ACC-9001','P-10235','Hijo(a)','0','2019-03-15',''],
        'note' => 'Relacion cuenta-persona-parentesco. Cada cuenta debe tener exactamente un titular.',
    ],
    '05_membresias' => [
        'headers' => ['legacy_membership_id','park_code','legacy_account_id','membership_type_code_legacy','membership_type_name_legacy','status','is_primary','is_billable','monthly_fee','inscription_fee','origin_membership_type_legacy','start_date','end_date','validity_months','club_seniority_years'],
        'example' => ['MEM-7788','PE2','PE2-ACC-2001','PE2_PM_IND','Pase mensual individual','active','1','1','3600.00','0.00','','2026-01-01','2026-12-31','12','5'],
        'note' => 'Incluir membresias activas y, si es posible, historicas.',
    ],
    '06_saldos_pendientes' => [
        'headers' => ['legacy_balance_id','park_code','legacy_account_id','legacy_membership_id','concept_code_legacy','concept_name_legacy','description','amount','balance','issue_date','due_date','period_year','period_month','allows_partial_payments','status'],
        'example' => ['BAL-1001','PE1','PE1-ACC-9001','MEM-7788','MONTHLY_FEE','Mensualidad','Mensualidad abril 2026','1850.00','350.00','2026-04-01','2026-04-10','2026','4','0','pending'],
        'note' => 'Solo cargos abiertos al corte. Para mensualidades, llenar period_year y period_month.',
    ],
    '07_pagos_historicos' => [
        'headers' => ['legacy_payment_id','park_code','legacy_account_id','payment_method_legacy','amount','paid_at','reference','bank_name','check_number','status','notes'],
        'example' => ['PAY-5001','PE2','PE2-ACC-2001','TRANSFERENCIA','1850.00','2026-04-10 13:45:00','SPEI12345','BBVA','','registered','Pago en caja'],
        'note' => 'Opcional en primera etapa. Si no hay detalle confiable, puede omitirse inicialmente.',
    ],
    '08_aplicacion_pagos' => [
        'headers' => ['legacy_payment_id','legacy_balance_id','applied_amount'],
        'example' => ['PAY-5001','BAL-1001','350.00'],
        'note' => 'Solo si el sistema origen conoce la aplicacion exacta del pago a cada saldo.',
    ],
    '09_equivalencias_catalogos' => [
        'headers' => ['catalog_type','legacy_value','new_value','comments'],
        'example' => ['membership_type','PASE MENSUAL INDIVIDUAL','PE2_PM_IND','Validado con administracion'],
        'note' => 'Sirve para homologar tipos de membresia, estatus, parentescos, conceptos y metodos de pago.',
    ],
    '10_vinculos_entre_parques' => [
        'headers' => ['primary_legacy_person_id','legacy_account_id_pe1','legacy_account_id_pe2','same_billing_group','comments'],
        'example' => ['P-10234','PE1-ACC-9001','PE2-ACC-2001','1','Mismo titular en ambos parques'],
        'note' => 'Hoja clave para vincular cuentas del mismo titular entre PE1 y PE2 sin perder cuentas separadas.',
    ],
];

foreach ($sheets as $title => $config) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($title);

    $headers = $config['headers'];
    $example = $config['example'];

    $sheet->setCellValue('A1', $config['note']);
    $sheet->mergeCellsByColumnAndRow(1, 1, count($headers), 1);
    $sheet->getStyle('A1')->applyFromArray($noteStyle);

    foreach ($headers as $index => $header) {
        $col = $index + 1;
        $sheet->setCellValueByColumnAndRow($col, 2, $header);
        $sheet->getStyleByColumnAndRow($col, 2)->applyFromArray($headerStyle);
        $sheet->getColumnDimensionByColumn($col)->setWidth(max(strlen($header) + 4, 18));
    }

    foreach ($example as $index => $value) {
        $col = $index + 1;
        $sheet->setCellValueByColumnAndRow($col, 3, $value);
        $sheet->getStyleByColumnAndRow($col, 3)->applyFromArray($exampleStyle);
    }

    $sheet->freezePane('A3');
    $sheet->setAutoFilterByColumnAndRow(1, 2, count($headers), 2);
    $sheet->getRowDimension(1)->setRowHeight(28);
    $sheet->getRowDimension(2)->setRowHeight(24);
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '3')->getAlignment()->setWrapText(true);
}

$spreadsheet->setActiveSheetIndex(0);
$outputFile = $outputDir . '/Plantilla_Migracion_Usuarios_Cobranza.xlsx';

$writer = new Xlsx($spreadsheet);
$writer->save($outputFile);

echo $outputFile . PHP_EOL;
