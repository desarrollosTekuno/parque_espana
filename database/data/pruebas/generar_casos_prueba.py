"""
Genera database/data/pruebas/Plantilla_Casos_Prueba.xlsx (las 19 pestañas de datos) a partir de la plantilla
real (database/data/Plantilla_Migracion_Cliente.xlsx) con 2 casos por cada tipo
de situacion de la carga 1, y los archivos de prueba en pruebas/ARCHIVOS.

Fecha de corte de la prueba: 2026-09-30.
"""
import os, sys, shutil, openpyxl

ORIGEN = sys.argv[1]
DESTINO = sys.argv[2]
CARPETA_ARCHIVOS = sys.argv[3]

wb = openpyxl.load_workbook(ORIGEN)
wb['Instrucciones']['B5'] = '2026-09-30'


def llenar(hoja, filas, borrar_existentes=True):
    ws = wb[hoja]
    cols = {ws.cell(2, c).value: c for c in range(1, ws.max_column + 1) if ws.cell(2, c).value}
    if borrar_existentes:
        for r in range(4, ws.max_row + 1):
            for c in cols.values():
                ws.cell(r, c).value = None
    for i, fila in enumerate(filas):
        for col, valor in fila.items():
            if col not in cols:
                raise KeyError(f'{hoja}: no existe la columna {col}')
            ws.cell(4 + i, cols[col]).value = valor


# ── Personal: 2 activos (completo y minimo) + 2 cajeros que ya no laboran ──
llenar('Personal', [
    {'NOMBRE': 'Laura', 'APELLIDO PATERNO': 'Mendez', 'APELLIDO MATERNO': 'Ruiz', 'CORREO': 'laura.mendez@prueba.local',
     'ROL': 'Cobranza', 'CLUBES': 'PE2', 'SERIE DE CAJA': 'K'},
    {'NOMBRE': 'Jorge', 'APELLIDO PATERNO': 'Ramos', 'CORREO': 'jorge.ramos@prueba.local', 'ROL': 'admin_club', 'CLUBES': 'PE1, PE2'},
    {'NOMBRE': 'Maria', 'APELLIDO PATERNO': 'Lopez', 'CLUBES': 'PE2', 'SERIE DE CAJA': 'A'},
    {'NOMBRE': 'Pedro', 'APELLIDO PATERNO': 'Sanchez', 'APELLIDO MATERNO': 'Gomez', 'CLUBES': 'Parque España I', 'SERIE DE CAJA': 'C'},
])

# ── Usuarios ──
# completo = todos los datos; minimo = solo lo obligatorio (ID, nombre, apellido paterno, nacimiento)
DOM_PUE = {'CALLE Y NUMERO': '5 de Mayo 120', 'COLONIA': 'Centro', 'CODIGO POSTAL': '72000', 'PAIS': 'México',
           'ESTADO': 'Puebla', 'CIUDAD': 'Puebla', 'AÑOS EN LA CIUDAD': 15}


def persona(id_, nombre, paterno, nacimiento, completo=False, **extra):
    p = {'ID DE USUARIO': id_, 'NOMBRE': nombre, 'APELLIDO PATERNO': paterno, 'FECHA DE NACIMIENTO': nacimiento}
    if completo:
        p.update({'APELLIDO MATERNO': 'Garcia', 'SEXO': 'H', 'TELEFONO': '2221234567',
                  'CORREO': f'{id_.lower()}@prueba.local', 'ESTADO CIVIL': 'Casado(a)', 'NACIONALIDAD': 'Mexicana',
                  'PAIS DE NACIMIENTO': 'México', 'ESTADO DE NACIMIENTO': 'Puebla', 'CIUDAD DE NACIMIENTO': 'Puebla',
                  'OCUPACION': 'Ingeniero', 'EMPRESA': 'Empresa de Prueba SA', 'DOMICILIO DE LA EMPRESA': 'Reforma 10, Puebla',
                  'TELEFONO DE LA EMPRESA': '2229876543', **DOM_PUE})
    p.update(extra)
    return p


usuarios = [
    # 1. Individual PE1
    persona('PRB-001', 'Ignacio', 'Caso', '1975-05-02', completo=True),
    persona('PRB-002', 'Ana', 'Bernal', '1980-02-25'),
    # 2. Individual PE2
    persona('PRB-003', 'Manuel', 'Flores', '1970-03-21', completo=True, **{'PAIS DE NACIMIENTO': 'España', 'ESTADO DE NACIMIENTO': 'Asturias', 'CIUDAD DE NACIMIENTO': 'Oviedo', 'NACIONALIDAD': 'Española'}),
    persona('PRB-004', 'Lucia', 'Serna', '1990-07-11'),
    # 3. Familiar PE1
    persona('PRB-005', 'Roberto', 'Diaz', '1978-01-15', completo=True),
    persona('PRB-006', 'Carmen', 'Vega', '1980-06-20', completo=True, SEXO='M', OCUPACION='Hogar'),
    persona('PRB-007', 'Diego', 'Diaz', '2014-09-10', ESCUELA='Colegio Americano', SEXO='H'),
    persona('PRB-008', 'Patricia', 'Morales', '1985-04-02', completo=True, SEXO='M'),
    persona('PRB-009', 'Sofia', 'Morales', '2021-03-30'),
    # 4. Familiar PE2 (con madre)
    persona('PRB-010', 'Fernando', 'Ortiz', '1972-11-05', completo=True),
    persona('PRB-011', 'Elena', 'Ruiz', '1974-08-19', SEXO='M'),
    persona('PRB-012', 'Pablo', 'Ortiz', '2008-12-01', ESCUELA='Instituto Oriente'),
    persona('PRB-013', 'Rosa', 'Garcia', '1948-02-14', SEXO='M'),
    persona('PRB-014', 'Hector', 'Luna', '1983-09-09', completo=True),
    persona('PRB-015', 'Mariana', 'Paz', '1986-10-10'),
    # 5. Individual en 2 parques
    persona('PRB-016', 'Andres', 'Castillo', '1969-12-12', completo=True),
    persona('PRB-017', 'Beatriz', 'Nunez', '1977-05-05', completo=True, SEXO='M'),
    # 6. Familiar en 2 parques
    persona('PRB-018', 'Ricardo', 'Herrera', '1968-03-03', completo=True),
    persona('PRB-019', 'Gabriela', 'Soto', '1970-04-04', SEXO='M'),
    persona('PRB-020', 'Tomas', 'Herrera', '2010-01-20'),
    persona('PRB-021', 'Silvia', 'Campos', '1982-08-08', completo=True, SEXO='M'),
    persona('PRB-022', 'Julio', 'Reyes', '1981-02-02'),
    persona('PRB-040', 'Marcos', 'Herrera', '2000-01-10', **{'ESTADO CIVIL': 'Soltero(a)'}),
    # 7. Paquete intermedio (individual en un parque, familiar en el otro)
    persona('PRB-023', 'Alberto', 'Mena', '1965-06-06', completo=True),
    persona('PRB-024', 'Claudia', 'Rios', '1967-07-07', SEXO='M'),
    persona('PRB-025', 'Oscar', 'Vidal', '1979-09-19', completo=True),
    persona('PRB-026', 'Ines', 'Prado', '1980-10-29'),
    # 8. Solidaria (24 a 26 años, viene de familiar)
    persona('PRB-027', 'Daniel', 'Diaz', '2001-05-05', completo=True, **{'ESTADO CIVIL': 'Soltero(a)'}),
    persona('PRB-028', 'Valeria', 'Ortiz', '2002-02-02', completo=True, SEXO='M', **{'ESTADO CIVIL': 'Soltero(a)'}),
    # 9. Pase mensual
    persona('PRB-029', 'Mario', 'Lara', '1992-01-01', completo=True),
    persona('PRB-030', 'Rocio', 'Salas', '1988-03-15', completo=True, SEXO='M'),
    persona('PRB-031', 'Emilio', 'Salas', '2016-06-16'),
    # 10. Tipos especiales (Beneficencia / Doctores)
    persona('PRB-032', 'Victor', 'Pineda', '1960-02-29', completo=True),
    persona('PRB-033', 'Laura', 'Cruz', '1962-12-24'),
    persona('PRB-034', 'Arturo', 'Guzman', '1975-11-11', completo=True, OCUPACION='Medico'),
    # 11. Canceladas
    persona('PRB-035', 'Jaime', 'Rangel', '1971-01-31', completo=True),
    persona('PRB-036', 'Monica', 'Tapia', '1984-05-25', completo=True, SEXO='M'),
    persona('PRB-037', 'Luis', 'Tapia', '2012-07-07'),
    # 12. Suspendida y pendiente
    persona('PRB-038', 'Raul', 'Ibarra', '1987-08-18', completo=True),
    persona('PRB-039', 'Teresa', 'Quiroz', '1991-09-29'),
]
llenar('Usuarios', usuarios)

# ── Informacion medica: 2 completas, 2 minimas ──
llenar('Informacion medica', [
    {'ID DE USUARIO': 'PRB-001', 'TIPO DE SANGRE': 'O', 'FACTOR RH': 'POSITIVO', 'DIABETES': 'SI', 'TIPO DE DIABETES': 'II',
     'CARDIOPATIA': 'NO', 'EPILEPSIA': 'NO', 'ASMA': 'NO', 'ALERGIA': 'SI', 'TOMA MEDICAMENTOS': 'SI', 'MEDICAMENTOS': 'Metformina',
     'ALERGENOS': 'SI', 'DETALLE DE ALERGENOS': 'Penicilina', 'PRESION ARTERIAL NORMAL': 'SI', 'HIPERTENSION': 'NO',
     'CONDICIONES ESPECIALES': 'Ninguna', 'MEDICO TRATANTE': 'Jose Perez', 'TELEFONO DEL MEDICO': '2225551111',
     'NUMERO DE SEGURIDAD SOCIAL': '12345678901', 'SEGURO DE GASTOS MEDICOS': 'Particular', 'COMPAÑIA DEL SEGURO': 'Aseguradora X',
     'NUMERO DE POLIZA': 'POL-0001', 'CELULAR DEL SEGURO': '2225552222'},
    {'ID DE USUARIO': 'PRB-005', 'TIPO DE SANGRE': 'A', 'FACTOR RH': 'NEGATIVO', 'ASMA': 'SI', 'TOMA MEDICAMENTOS': 'SI',
     'MEDICAMENTOS': 'Salbutamol', 'MEDICO TRATANTE': 'Ana Torres', 'TELEFONO DEL MEDICO': '2225553333'},
    {'ID DE USUARIO': 'PRB-007', 'ALERGIA': 'SI', 'ALERGENOS': 'SI', 'DETALLE DE ALERGENOS': 'Cacahuate'},
    {'ID DE USUARIO': 'PRB-010', 'TIPO DE SANGRE': 'AB'},
])

# ── Contactos de emergencia ──
llenar('Contactos de emergencia', [
    {'ID DE USUARIO': 'PRB-001', 'NOMBRE DEL CONTACTO': 'Maria Caso', 'TELEFONO DEL CONTACTO': '2221112222',
     'CELULAR DEL CONTACTO': '2223334444', 'EN CASO NECESARIO INFORMAR A': 'Jose Caso'},
    {'ID DE USUARIO': 'PRB-005', 'NOMBRE DEL CONTACTO': 'Carmen Vega', 'CELULAR DEL CONTACTO': '2225556666'},
    {'ID DE USUARIO': 'PRB-010', 'NOMBRE DEL CONTACTO': 'Elena Ruiz'},
])

# ── Documentos (archivos en pruebas/ARCHIVOS) ──
documentos = [
    ('PRB-001', 'INE', 'DOCUMENTOS/PRB-001/INE.pdf', None, 'SI', '2026-01-15'),
    ('PRB-005', 'INE', 'DOCUMENTOS/PRB-005/INE.pdf', None, 'NO', None),
    ('PRB-007', 'Acta de Nacimiento', 'DOCUMENTOS/PRB-007/acta.pdf', None, 'SI', None),
    ('PRB-010', 'Comprobante de Domicilio', 'DOCUMENTOS/PRB-010/comprobante.jpg', 'PE2', None, None),
]
llenar('Documentos', [
    {k: v for k, v in zip(['ID DE USUARIO', 'TIPO DE DOCUMENTO', 'RUTA DEL ARCHIVO', 'CLUB', 'VERIFICADO', 'FECHA DE VERIFICACION'], d) if v is not None}
    for d in documentos
])

# ── Membresias ──
FISCAL = {'NOMBRE O RAZON SOCIAL': 'IGNACIO CASO GARCIA', 'RFC': 'CAGI750502AB1', 'USO DE CFDI': 'G03',
          'REGIMEN FISCAL': '612', 'CODIGO POSTAL FISCAL': '72000'}


def cuenta(numero, club, tipo, inicio, estatus='ACTIVA', **extra):
    c = {'NUMERO DE CUENTA': numero, 'CLUB': club, 'TIPO DE MEMBRESIA': tipo, 'ESTATUS': estatus, 'FECHA DE INICIO': inicio}
    c.update(extra)
    return c


membresias = [
    # 1. Individual PE1 (completa con fiscales y cuota de Fox; minima)
    cuenta('PE1-IND-01', 'PE1', 'Individual', '2010-03-01', **{'INDIVIDUAL O FAMILIAR': 'INDIVIDUAL', 'CUOTA MENSUAL': 1500, **FISCAL}),
    cuenta('PE1-IND-02', 'Parque España I', 'Individual', '2018-07-15'),
    # 2. Individual PE2 (una con cuota de Fox distinta a la del sistema, para ver el aviso)
    cuenta('PE2-IND-01', 'PE2', 'Individual(Externos)', '2012-01-10', **{'CUOTA MENSUAL': 1750}),
    cuenta('PE2-IND-02', 'PE2', 'PE2_IND_ASC', '2020-05-20'),
    # 3. Familiar PE1
    cuenta('PE1-FAM-01', 'PE1', 'Familiar', '2005-09-01', **{'INDIVIDUAL O FAMILIAR': 'FAMILIAR', 'CUOTA MENSUAL': 3000}),
    cuenta('PE1-FAM-02', 'PE1', 'Familiar', '2019-02-01'),
    # 4. Familiar PE2
    cuenta('PE2-FAM-01', 'PE2', 'Familiar(Ascendencia Española)', '2000-06-01', **{'CUOTA MENSUAL': 3600,
           **{**FISCAL, 'NOMBRE O RAZON SOCIAL': 'FERNANDO ORTIZ GARCIA', 'RFC': 'OIGF721105XY2'}}),
    cuenta('PE2-FAM-02', 'PE2', 'Familiar(Externos)', '2021-08-15'),
    # 5. Individual en 2 parques: sin GENERA COBRO (cobra la mas reciente) / con GENERA COBRO explicito
    cuenta('PE1-IND-03', 'PE1', 'Individual', '2008-01-01', **{'CUENTA EN EL OTRO PARQUE': 'PE2-IND-03'}),
    cuenta('PE2-IND-03', 'PE2', 'Individual(Externos)', '2015-01-01', **{'CUENTA EN EL OTRO PARQUE': 'PE1-IND-03'}),
    cuenta('PE1-IND-04', 'PE1', 'Individual', '2016-04-01', **{'CUENTA EN EL OTRO PARQUE': 'PE2-IND-04', 'GENERA COBRO': 'SI'}),
    cuenta('PE2-IND-04', 'PE2', 'Individual(Externos)', '2017-04-01', **{'GENERA COBRO': 'NO'}),
    # 6. Familiar en 2 parques
    cuenta('PE1-FAM-03', 'PE1', 'Familiar', '2003-05-01', **{'CUENTA EN EL OTRO PARQUE': 'PE2-FAM-03'}),
    cuenta('PE2-FAM-03', 'PE2', 'Familiar(Externos)', '2011-05-01', **{'CUENTA EN EL OTRO PARQUE': 'PE1-FAM-03'}),
    cuenta('PE1-FAM-04', 'PE1', 'Familiar', '2022-01-10', **{'CUENTA EN EL OTRO PARQUE': 'PE2-FAM-04'}),
    cuenta('PE2-FAM-04', 'PE2', 'Familiar(Ascendencia Española)', '2022-02-10'),
    # 7. Paquete intermedio: individual en un parque y familiar en el otro
    cuenta('PE1-IND-05', 'PE1', 'Individual', '2010-10-10', **{'CUENTA EN EL OTRO PARQUE': 'PE2-FAM-05'}),
    cuenta('PE2-FAM-05', 'PE2', 'Familiar(Ascendencia Española)', '2014-10-10', **{'CUENTA EN EL OTRO PARQUE': 'PE1-IND-05'}),
    cuenta('PE2-FAM-06', 'PE2', 'Familiar(Externos)', '2009-03-03', **{'CUENTA EN EL OTRO PARQUE': 'PE1-IND-06'}),
    cuenta('PE1-IND-06', 'PE1', 'Individual', '2018-03-03', **{'CUENTA EN EL OTRO PARQUE': 'PE2-FAM-06'}),
    # 8. Solidaria (separada de una familiar)
    cuenta('PE1-SOL-01', 'PE1', 'Solidaria', '2026-06-01', **{'TIPO DE MEMBRESIA ANTERIOR': 'Familiar',
           'CUENTA DE ORIGEN': 'PE1-FAM-01', 'MOTIVO DE SEPARACION': 'Promocion por edad'}),
    cuenta('PE2-SOL-01', 'PE2', 'Solidaria(Ascendencia Española)', '2026-03-01', **{'TIPO DE MEMBRESIA ANTERIOR': 'Familiar(Ascendencia Española)',
           'CUENTA DE ORIGEN': 'PE2-FAM-01', 'MOTIVO DE SEPARACION': 'Promocion por edad'}),
    # 9. Pase mensual (sin fecha de termino: se calcula; con fecha de termino)
    cuenta('PE2-PM-01', 'PE2', 'Pase Mensual Individual', '2026-05-01'),
    cuenta('PE2-PM-02', 'PE2', 'Pase Mensual Familiar', '2026-01-15', **{'FECHA DE TERMINO': '2026-12-31', 'INDIVIDUAL O FAMILIAR': 'FAMILIAR'}),
    # 10. Tipos especiales
    cuenta('PE1-BEN-01', 'PE1', 'Familiar(Beneficencia Española)', '1999-09-09'),
    cuenta('PE2-DOC-01', 'PE2', 'Individual(Doctores Beneficencia Española)', '2004-04-04'),
    # 11. Canceladas (voluntaria con carta / sancion sin carta)
    cuenta('PE1-IND-07', 'PE1', 'Individual', '2001-01-01', 'CANCELADA', **{'FECHA DE CANCELACION': '2025-12-31',
           'TIPO DE CANCELACION': 'VOLUNTARIA', 'MOTIVO DE CANCELACION': 'Voluntaria',
           'ARCHIVO DE LA CARTA DE CANCELACION': 'CANCELACIONES/PE1-IND-07/carta.pdf'}),
    cuenta('PE2-FAM-07', 'PE2', 'Familiar(Externos)', '2013-06-06', 'CANCELADA', **{'FECHA DE CANCELACION': '2026-04-30',
           'TIPO DE CANCELACION': 'SANCION', 'MOTIVO DE CANCELACION': 'Falta de Pago'}),
    # 12. Suspendida y pendiente
    cuenta('PE2-IND-08', 'PE2', 'Individual(Externos)', '2019-09-09', 'SUSPENDIDA'),
    cuenta('PE1-IND-08', 'PE1', 'Individual', '2026-09-15', 'PENDIENTE'),
]
llenar('Membresias', membresias)

# ── Integrantes ──
def integ(cuenta_, persona_, titular, parentesco=None, tarjeta=None):
    d = {'NUMERO DE CUENTA': cuenta_, 'ID DE USUARIO': persona_, 'ES TITULAR': 'SI' if titular else 'NO'}
    if parentesco:
        d['PARENTESCO'] = parentesco
    if tarjeta:
        d['NUMERO DE TARJETA DE ACCESO'] = tarjeta
    return d


integrantes = [
    integ('PE1-IND-01', 'PRB-001', True, tarjeta='9000000001'),
    integ('PE1-IND-02', 'PRB-002', True),
    integ('PE2-IND-01', 'PRB-003', True),
    integ('PE2-IND-02', 'PRB-004', True),
    integ('PE1-FAM-01', 'PRB-005', True), integ('PE1-FAM-01', 'PRB-006', False, 'Cónyuge'), integ('PE1-FAM-01', 'PRB-007', False, 'Hijo(a)'),
    integ('PE1-FAM-02', 'PRB-008', True), integ('PE1-FAM-02', 'PRB-009', False, 'Hijo(a)'),
    integ('PE2-FAM-01', 'PRB-010', True, tarjeta='9000000010'), integ('PE2-FAM-01', 'PRB-011', False, 'Conyuge', '9000000011'),
    integ('PE2-FAM-01', 'PRB-012', False, 'Hijo(a)', '9000000012'), integ('PE2-FAM-01', 'PRB-013', False, 'Madre'),
    integ('PE2-FAM-02', 'PRB-014', True), integ('PE2-FAM-02', 'PRB-015', False, 'Cónyuge'),
    integ('PE1-IND-03', 'PRB-016', True), integ('PE2-IND-03', 'PRB-016', True),
    integ('PE1-IND-04', 'PRB-017', True), integ('PE2-IND-04', 'PRB-017', True),
    integ('PE1-FAM-03', 'PRB-018', True), integ('PE1-FAM-03', 'PRB-019', False, 'Cónyuge'), integ('PE1-FAM-03', 'PRB-020', False, 'Hijo(a)'), integ('PE1-FAM-03', 'PRB-040', False, 'Hijo(a)'),
    integ('PE2-FAM-03', 'PRB-018', True), integ('PE2-FAM-03', 'PRB-019', False, 'Cónyuge'), integ('PE2-FAM-03', 'PRB-020', False, 'Hijo(a)'),
    integ('PE1-FAM-04', 'PRB-021', True), integ('PE1-FAM-04', 'PRB-022', False, 'Cónyuge'),
    integ('PE2-FAM-04', 'PRB-021', True), integ('PE2-FAM-04', 'PRB-022', False, 'Cónyuge'),
    integ('PE1-IND-05', 'PRB-023', True), integ('PE2-FAM-05', 'PRB-023', True), integ('PE2-FAM-05', 'PRB-024', False, 'Cónyuge'),
    integ('PE2-FAM-06', 'PRB-025', True), integ('PE2-FAM-06', 'PRB-026', False, 'Cónyuge'), integ('PE1-IND-06', 'PRB-025', True),
    integ('PE1-SOL-01', 'PRB-027', True),
    integ('PE2-SOL-01', 'PRB-028', True),
    integ('PE2-PM-01', 'PRB-029', True),
    integ('PE2-PM-02', 'PRB-030', True), integ('PE2-PM-02', 'PRB-031', False, 'Hijo(a)'),
    integ('PE1-BEN-01', 'PRB-032', True), integ('PE1-BEN-01', 'PRB-033', False, 'Cónyuge'),
    integ('PE2-DOC-01', 'PRB-034', True),
    integ('PE1-IND-07', 'PRB-035', True),
    integ('PE2-FAM-07', 'PRB-036', True), integ('PE2-FAM-07', 'PRB-037', False, 'Hijo(a)'),
    integ('PE2-IND-08', 'PRB-038', True),
    integ('PE1-IND-08', 'PRB-039', True),
]
llenar('Integrantes', integrantes)

# ── Permisos: 25% vigente con archivo / 75% futuro sin archivo ──
llenar('Permisos de ausencia', [
    {'NUMERO DE CUENTA': 'PE2-FAM-01', 'INICIO DEL PERMISO': '2026-09-01', 'FIN DEL PERMISO': '2026-12-31', 'PORCENTAJE A COBRAR': 25,
     'BLOQUEA ACCESO': 'SI', 'BLOQUEA RESERVACIONES': 'SI', 'ARCHIVO DEL PERMISO': 'PERMISOS/PE2-FAM-01/permiso.pdf',
     'NOTAS DEL PERMISO': 'Viaje al extranjero'},
    {'NUMERO DE CUENTA': 'PE1-FAM-02', 'INICIO DEL PERMISO': '2026-11-01', 'FIN DEL PERMISO': '2026-12-31', 'PORCENTAJE A COBRAR': 75,
     'BLOQUEA RESERVACIONES': 'NO'},
])

exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'casos_dinero_amenidades.py'), encoding='utf-8').read())
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'casos_volumen.py'), encoding='utf-8').read())

wb.save(DESTINO)

# ── Archivos de prueba ──
PDF = b"%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"
JPG = bytes.fromhex('ffd8ffe000104a46494600010100000100010000ffd9')
for ruta in ['DOCUMENTOS/PRB-001/INE.pdf', 'DOCUMENTOS/PRB-005/INE.pdf', 'DOCUMENTOS/PRB-007/acta.pdf',
             'DOCUMENTOS/PRB-010/comprobante.jpg', 'CANCELACIONES/PE1-IND-07/carta.pdf', 'PERMISOS/PE2-FAM-01/permiso.pdf'] + ARCHIVOS_EXTRA:
    destino = os.path.join(CARPETA_ARCHIVOS, *ruta.split('/'))
    os.makedirs(os.path.dirname(destino), exist_ok=True)
    with open(destino, 'wb') as f:
        f.write(JPG if ruta.endswith('.jpg') else PDF)

print('ok', len(usuarios), 'usuarios', len(membresias), 'cuentas', len(integrantes), 'integrantes')
