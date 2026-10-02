# Se ejecuta dentro de generar_casos_prueba.py despues de casos_dinero_amenidades.py.
# Agrega VOLUMEN: muchas cuentas mas (familiares PE1, PE2, de ambos parques, individuales)
# y registra cosas para todos: informacion medica, contactos, documentos, historial de
# cargos y pagos de enero a septiembre 2026, notas, casilleros, reservaciones y listas.
import random
from datetime import date

rnd = random.Random(2026)


def agregar(hoja, filas):
    ws = wb[hoja]
    cols = {ws.cell(2, c).value: c for c in range(1, ws.max_column + 1) if ws.cell(2, c).value}
    ultima = 3
    for r in range(4, ws.max_row + 1):
        if any(ws.cell(r, c).value not in (None, '') for c in cols.values()):
            ultima = r
    for i, fila in enumerate(filas):
        for col, valor in fila.items():
            if col not in cols:
                raise KeyError(f'{hoja}: no existe la columna {col}')
            if valor is not None:
                ws.cell(ultima + 1 + i, cols[col]).value = valor


NOMBRES_H = ['Alejandro', 'Eduardo', 'Francisco', 'Gerardo', 'Javier', 'Sergio', 'Rodrigo', 'Enrique', 'Gustavo', 'Adrian',
             'Rafael', 'Armando', 'Ernesto', 'Hugo', 'Ivan', 'Joaquin', 'Leonardo', 'Mauricio', 'Nicolas', 'Salvador']
NOMBRES_M = ['Adriana', 'Alejandra', 'Brenda', 'Cecilia', 'Daniela', 'Fernanda', 'Guadalupe', 'Isabel', 'Karla', 'Lorena',
             'Marisol', 'Natalia', 'Paola', 'Regina', 'Susana', 'Veronica', 'Ximena', 'Yolanda', 'Claudia', 'Martha']
NINOS_H = ['Santiago', 'Mateo', 'Sebastian', 'Emiliano', 'Leonardo', 'Diego', 'Rodrigo', 'Bruno', 'Iker', 'Gael']
NINAS_M = ['Valentina', 'Regina', 'Camila', 'Renata', 'Ximena', 'Sofia', 'Victoria', 'Romina', 'Paula', 'Natalia']
APELLIDOS = ['Aguilar', 'Alvarez', 'Bravo', 'Cabrera', 'Cardenas', 'Cervantes', 'Contreras', 'Dominguez', 'Escobar', 'Espinosa',
             'Estrada', 'Fuentes', 'Gallardo', 'Gil', 'Guerrero', 'Ibañez', 'Juarez', 'Leon', 'Lozano', 'Marin', 'Medina', 'Mejia',
             'Miranda', 'Molina', 'Montes', 'Navarro', 'Ochoa', 'Orozco', 'Pacheco', 'Padilla', 'Peña', 'Quintero', 'Robles',
             'Rosales', 'Saldaña', 'Sandoval', 'Solis', 'Suarez', 'Trejo', 'Valdez', 'Valencia', 'Velazquez', 'Villa', 'Zamora']
COLONIAS = [('La Paz', '72160'), ('Angelopolis', '72830'), ('Centro', '72000'), ('Huexotitla', '72534'), ('Las Animas', '72400'),
            ('Lomas de Angelopolis', '72830'), ('El Carmen', '72530'), ('Zavaleta', '72150')]
CALLES = ['Av. Juarez', 'Calle 25 Sur', 'Blvd. Atlixco', 'Av. Reforma', 'Calle 11 Sur', 'Av. Zavaleta', 'Calle 31 Poniente', 'Blvd. Hermanos Serdan']
OCUPACIONES = ['Abogado', 'Contador', 'Arquitecto', 'Medico', 'Ingeniero', 'Empresario', 'Docente', 'Comerciante', 'Diseñador', 'Hogar']
ESCUELAS = ['Colegio Americano', 'Instituto Oriente', 'Colegio Humboldt', 'Colegio Benavente', 'Instituto Mexico']

usuarios_nuevos, cuentas_nuevas, integrantes_nuevos = [], [], []
seq = {'p': 100, 'tarjeta': 9000001000}
NAC = 'Mexicana'


def nueva_persona(sexo, nacimiento, apellido, materno=None, adulto=True, completo=True, nombre=None):
    seq['p'] += 1
    id_ = f'PRB-{seq["p"]:03d}'
    if nombre is None:
        pool = (NOMBRES_H if sexo == 'H' else NOMBRES_M) if adulto else (NINOS_H if sexo == 'H' else NINAS_M)
        nombre = rnd.choice(pool)
    p = {'ID DE USUARIO': id_, 'NOMBRE': nombre, 'APELLIDO PATERNO': apellido, 'APELLIDO MATERNO': materno or rnd.choice(APELLIDOS),
         'FECHA DE NACIMIENTO': nacimiento, 'SEXO': sexo}
    if completo:
        col, cp = rnd.choice(COLONIAS)
        p.update({'TELEFONO': f'222{rnd.randint(1000000, 9999999)}', 'NACIONALIDAD': NAC, 'PAIS DE NACIMIENTO': 'México',
                  'ESTADO DE NACIMIENTO': 'Puebla', 'CIUDAD DE NACIMIENTO': 'Puebla', 'CALLE Y NUMERO': f'{rnd.choice(CALLES)} {rnd.randint(10, 3000)}',
                  'COLONIA': col, 'CODIGO POSTAL': cp, 'PAIS': 'México', 'ESTADO': 'Puebla', 'CIUDAD': 'Puebla',
                  'AÑOS EN LA CIUDAD': rnd.randint(3, 40)})
        if adulto:
            p.update({'CORREO': f'{id_.lower()}@prueba.local', 'ESTADO CIVIL': 'Casado(a)', 'OCUPACION': rnd.choice(OCUPACIONES)})
            if rnd.random() < 0.5:
                p.update({'EMPRESA': f'{rnd.choice(APELLIDOS)} y Asociados SC', 'DOMICILIO DE LA EMPRESA': f'{rnd.choice(CALLES)} {rnd.randint(1, 900)}, Puebla',
                          'TELEFONO DE LA EMPRESA': f'222{rnd.randint(1000000, 9999999)}'})
        else:
            p['ESCUELA'] = rnd.choice(ESCUELAS)
    usuarios_nuevos.append(p)
    return id_


def tarjeta():
    seq['tarjeta'] += 1
    return str(seq['tarjeta'])


def familia(n_hijos, con_madre=False):
    """Crea una familia y regresa [(id, parentesco)] con el titular primero."""
    ap = rnd.choice(APELLIDOS)
    ap_conyuge = rnd.choice(APELLIDOS)
    titular_h = rnd.random() < 0.7
    anio = rnd.randint(1965, 1990)
    t = nueva_persona('H' if titular_h else 'M', f'{anio}-{rnd.randint(1, 12):02d}-{rnd.randint(1, 28):02d}', ap)
    miembros = [(t, None)]
    if n_hijos >= 0:
        c = nueva_persona('M' if titular_h else 'H', f'{anio + rnd.randint(-3, 3)}-{rnd.randint(1, 12):02d}-{rnd.randint(1, 28):02d}',
                          ap_conyuge, completo=rnd.random() < 0.6)
        miembros.append((c, 'Cónyuge'))
    for _ in range(max(n_hijos, 0)):
        hijo_anio = rnd.randint(max(anio + 22, 2003), 2024)
        s = rnd.choice('HM')
        h = nueva_persona(s, f'{hijo_anio}-{rnd.randint(1, 12):02d}-{rnd.randint(1, 28):02d}', ap, ap_conyuge,
                          adulto=hijo_anio < 2008, completo=rnd.random() < 0.5)
        miembros.append((h, 'Hijo(a)'))
    if con_madre:
        m = nueva_persona('M', f'{anio - rnd.randint(22, 30)}-{rnd.randint(1, 12):02d}-{rnd.randint(1, 28):02d}', ap, completo=False)
        miembros.append((m, 'Madre'))
    return miembros


def alta_cuenta(numero, club, tipo, inicio, miembros, con_tarjetas, **extra):
    cuentas_nuevas.append({'NUMERO DE CUENTA': numero, 'CLUB': club, 'TIPO DE MEMBRESIA': tipo, 'ESTATUS': 'ACTIVA',
                           'FECHA DE INICIO': inicio, **extra})
    for i, (pid, parentesco) in enumerate(miembros):
        integrantes_nuevos.append({'NUMERO DE CUENTA': numero, 'ID DE USUARIO': pid, 'ES TITULAR': 'SI' if i == 0 else 'NO',
                                   'PARENTESCO': parentesco, 'NUMERO DE TARJETA DE ACCESO': tarjeta() if con_tarjetas else None})


def fecha_inicio():
    return f'{rnd.randint(1995, 2024)}-{rnd.randint(1, 12):02d}-{rnd.choice([1, 1, 1, 10, 15])}'


# Cuentas que generan cobro: numero -> (concepto, cuota, club donde se cobra)
cobro = {}

# ── Familiares PE1 (12) ──
for i in range(1, 13):
    num = f'PE1-FAM-{10 + i:02d}'
    alta_cuenta(num, 'PE1', 'Familiar', fecha_inicio(), familia(rnd.randint(0, 3), con_madre=(i % 6 == 0)), i % 2 == 0,
                **({'INDIVIDUAL O FAMILIAR': 'FAMILIAR', 'CUOTA MENSUAL': 3000} if i % 3 == 0 else {}))
    cobro[num] = ('CUOTA MENSUALIDAD', 3000, 'PE1')

# ── Familiares PE2 (12): externos y con ascendencia ──
for i in range(1, 13):
    num = f'PE2-FAM-{10 + i:02d}'
    tipo = 'Familiar(Externos)' if i % 2 else 'Familiar(Ascendencia Española)'
    alta_cuenta(num, 'PE2', tipo, fecha_inicio(), familia(rnd.randint(0, 3), con_madre=(i % 5 == 0)), i % 3 == 0)
    cobro[num] = ('CUOTA MENSUALIDAD', 3600, 'PE2')

# ── Familiares en ambos parques (6 pares): los mismos usuarios en las dos cuentas ──
for i in range(1, 7):
    n1, n2 = f'PE1-FAM-{30 + i:02d}', f'PE2-FAM-{30 + i:02d}'
    miembros = familia(rnd.randint(1, 3))
    a1 = rnd.randint(1998, 2015)
    a2 = rnd.randint(a1 + 1, 2024)
    f1, f2 = f'{a1}-03-01', f'{a2}-06-01'
    if i % 2:
        alta_cuenta(n1, 'PE1', 'Familiar', f1, miembros, True, **{'CUENTA EN EL OTRO PARQUE': n2})
        alta_cuenta(n2, 'PE2', 'Familiar(Externos)' if i % 3 else 'Familiar(Ascendencia Española)', f2, miembros, True,
                    **{'CUENTA EN EL OTRO PARQUE': n1})
        cobro[n2] = ('Cuota Mens Parques', 3700, 'PE2')  # cobra la de inicio mas reciente
    else:
        # GENERA COBRO explicito en la cuenta mas antigua
        alta_cuenta(n1, 'PE1', 'Familiar', f1, miembros, False, **{'CUENTA EN EL OTRO PARQUE': n2, 'GENERA COBRO': 'SI'})
        alta_cuenta(n2, 'PE2', 'Familiar(Externos)', f2, miembros, False, **{'CUENTA EN EL OTRO PARQUE': n1, 'GENERA COBRO': 'NO'})
        cobro[n1] = ('Cuota Mens Parques', 3700, 'PE1')

# ── Individuales PE1 (10) y PE2 (10) ──
for i in range(1, 11):
    for club, tipo, cuota in (('PE1', 'Individual', 1500), ('PE2', 'Individual(Externos)' if i % 2 else 'Individual(Ascendencia Española)', 1800)):
        num = f'{club}-IND-{20 + i:02d}'
        s = rnd.choice('HM')
        pid = nueva_persona(s, f'{rnd.randint(1960, 2001)}-{rnd.randint(1, 12):02d}-{rnd.randint(1, 28):02d}', rnd.choice(APELLIDOS),
                            completo=i % 4 != 0)
        if i % 4 != 0:
            usuarios_nuevos[-1]['ESTADO CIVIL'] = rnd.choice(['Soltero(a)', 'Casado(a)', 'Divorciado(a)'])
        alta_cuenta(num, club, tipo, fecha_inicio(), [(pid, None)], i % 2 == 1)
        cobro[num] = ('CUOTA MENSUALIDAD', cuota, club)

# ── Individuales en ambos parques (4 pares) ──
for i in range(1, 5):
    n1, n2 = f'PE1-IND-{40 + i:02d}', f'PE2-IND-{40 + i:02d}'
    pid = nueva_persona(rnd.choice('HM'), f'{rnd.randint(1960, 1995)}-{rnd.randint(1, 12):02d}-{rnd.randint(1, 28):02d}', rnd.choice(APELLIDOS))
    alta_cuenta(n1, 'PE1', 'Individual', f'{2000 + i}-01-01', [(pid, None)], True, **{'CUENTA EN EL OTRO PARQUE': n2})
    alta_cuenta(n2, 'PE2', 'Individual(Externos)', f'{2010 + i}-01-01', [(pid, None)], True, **{'CUENTA EN EL OTRO PARQUE': n1})
    cobro[n2] = ('Cuota Mens Parques', 1850, 'PE2')

# Datos fiscales en algunas cuentas
for c in cuentas_nuevas[::7]:
    titular = next(u for u in usuarios_nuevos if u['ID DE USUARIO'] == next(
        x['ID DE USUARIO'] for x in integrantes_nuevos if x['NUMERO DE CUENTA'] == c['NUMERO DE CUENTA'] and x['ES TITULAR'] == 'SI'))
    c.update({'NOMBRE O RAZON SOCIAL': f"{titular['NOMBRE']} {titular['APELLIDO PATERNO']} {titular['APELLIDO MATERNO']}".upper(),
              'RFC': (titular['APELLIDO PATERNO'][:2] + titular['APELLIDO MATERNO'][0] + titular['NOMBRE'][0]).upper().replace('Ñ', 'X')
              + titular['FECHA DE NACIMIENTO'][2:4] + titular['FECHA DE NACIMIENTO'][5:7] + titular['FECHA DE NACIMIENTO'][8:10] + 'AB1',
              'USO DE CFDI': 'G03', 'REGIMEN FISCAL': '612', 'CODIGO POSTAL FISCAL': '72000'})

agregar('Usuarios', usuarios_nuevos)
agregar('Membresias', cuentas_nuevas)
agregar('Integrantes', integrantes_nuevos)

todos_usuarios = usuarios + usuarios_nuevos
todos_integrantes = integrantes + integrantes_nuevos

# ── Informacion medica y contacto de emergencia para TODOS los que aun no tienen ──
ya_medica = {'PRB-001', 'PRB-005', 'PRB-007', 'PRB-010'}
ya_contacto = {'PRB-001', 'PRB-005', 'PRB-010'}
medica, contactos = [], []
MEDS = [('NO', None), ('NO', None), ('NO', None), ('SI', 'Losartan'), ('SI', 'Levotiroxina'), ('SI', 'Omeprazol')]
for u in todos_usuarios:
    pid = u['ID DE USUARIO']
    if pid not in ya_medica:
        toma, med = rnd.choice(MEDS)
        alergia = rnd.random() < 0.2
        fila = {'ID DE USUARIO': pid, 'TIPO DE SANGRE': rnd.choice(['O', 'O', 'A', 'B', 'AB']),
                'FACTOR RH': rnd.choice(['POSITIVO', 'POSITIVO', 'POSITIVO', 'NEGATIVO']),
                'DIABETES': 'NO', 'CARDIOPATIA': 'NO', 'EPILEPSIA': 'NO', 'ASMA': rnd.choice(['NO'] * 9 + ['SI']),
                'ALERGIA': 'SI' if alergia else 'NO', 'TOMA MEDICAMENTOS': toma, 'MEDICAMENTOS': med,
                'ALERGENOS': 'SI' if alergia else 'NO', 'DETALLE DE ALERGENOS': rnd.choice(['Polen', 'Mariscos', 'Ibuprofeno']) if alergia else None,
                'PRESION ARTERIAL NORMAL': 'SI', 'HIPERTENSION': 'SI' if med == 'Losartan' else 'NO'}
        if rnd.random() < 0.5:
            fila.update({'MEDICO TRATANTE': f'Dr. {rnd.choice(NOMBRES_H)} {rnd.choice(APELLIDOS)}', 'TELEFONO DEL MEDICO': f'222{rnd.randint(1000000, 9999999)}'})
        if rnd.random() < 0.4:
            fila.update({'NUMERO DE SEGURIDAD SOCIAL': str(rnd.randint(10 ** 10, 10 ** 11 - 1)), 'SEGURO DE GASTOS MEDICOS': 'Particular',
                         'COMPAÑIA DEL SEGURO': rnd.choice(['GNP', 'AXA', 'Metlife', 'Seguros Monterrey']),
                         'NUMERO DE POLIZA': f'POL-{rnd.randint(10000, 99999)}', 'CELULAR DEL SEGURO': f'800{rnd.randint(1000000, 9999999)}'})
        medica.append(fila)
    if pid not in ya_contacto:
        contactos.append({'ID DE USUARIO': pid, 'NOMBRE DEL CONTACTO': f'{rnd.choice(NOMBRES_H + NOMBRES_M)} {u["APELLIDO PATERNO"]}',
                          'TELEFONO DEL CONTACTO': f'222{rnd.randint(1000000, 9999999)}' if rnd.random() < 0.6 else None,
                          'CELULAR DEL CONTACTO': f'221{rnd.randint(1000000, 9999999)}',
                          'EN CASO NECESARIO INFORMAR A': f'{rnd.choice(NOMBRES_H + NOMBRES_M)} {rnd.choice(APELLIDOS)}' if rnd.random() < 0.3 else None})
agregar('Informacion medica', medica)
agregar('Contactos de emergencia', contactos)

# ── Documentos: INE y comprobante de cada titular nuevo, acta de cada hijo ──
documentos_nuevos = []
for x in integrantes_nuevos:
    pid, club = x['ID DE USUARIO'], x['NUMERO DE CUENTA'][:3]
    if any(d['ID DE USUARIO'] == pid and d['TIPO DE DOCUMENTO'] in ('INE', 'Acta de Nacimiento') for d in documentos_nuevos):
        continue
    if x['ES TITULAR'] == 'SI':
        documentos_nuevos.append({'ID DE USUARIO': pid, 'TIPO DE DOCUMENTO': 'INE', 'RUTA DEL ARCHIVO': f'DOCUMENTOS/{pid}/INE.pdf',
                                  'VERIFICADO': 'SI', 'FECHA DE VERIFICACION': '2026-02-10'})
        documentos_nuevos.append({'ID DE USUARIO': pid, 'TIPO DE DOCUMENTO': 'Comprobante de Domicilio', 'CLUB': club,
                                  'RUTA DEL ARCHIVO': f'DOCUMENTOS/{pid}/comprobante.jpg'})
    elif x['PARENTESCO'] == 'Hijo(a)':
        documentos_nuevos.append({'ID DE USUARIO': pid, 'TIPO DE DOCUMENTO': 'Acta de Nacimiento', 'RUTA DEL ARCHIVO': f'DOCUMENTOS/{pid}/acta.pdf',
                                  'VERIFICADO': 'NO'})
agregar('Documentos', documentos_nuevos)
ARCHIVOS_EXTRA += [d['RUTA DEL ARCHIVO'] for d in documentos_nuevos]

# ── Historial de cargos y pagos (enero a septiembre 2026) ──
# Tambien completa enero-junio de las cuentas de prueba originales que solo traian julio-septiembre.
cobro_original = {
    'PE1-IND-01': ('CUOTA MENSUALIDAD', 1500, 'PE1', 7), 'PE2-IND-01': ('CUOTA MENSUALIDAD', 1800, 'PE2', 7),
    'PE2-FAM-01': ('CUOTA MENSUALIDAD', 3600, 'PE2', 7), 'PE1-FAM-01': ('CUOTA MENSUALIDAD', 3000, 'PE1', 7),
    'PE2-FAM-03': ('Cuota Mens Parques', 3700, 'PE2', 7), 'PE2-IND-03': ('Cuota Mens Parques', 1850, 'PE2', 7),
    'PE1-IND-04': ('Cuota Mens Parques', 1850, 'PE1', 9), 'PE2-FAM-05': ('Cuota Mens Parques Intermedio', 3650, 'PE2', 7),
    'PE1-IND-06': ('Cuota Mens Parques Intermedio', 3650, 'PE1', 7), 'PE2-FAM-02': ('CUOTA MENSUALIDAD', 3600, 'PE2', 7),
    'PE1-IND-02': ('CUOTA MENSUALIDAD', 1500, 'PE1', 7), 'PE2-DOC-01': ('CUOTA MENSUALIDAD', 1800, 'PE2', 8),
    'PE1-FAM-02': ('CUOTA MENSUALIDAD', 3000, 'PE1', 7), 'PE2-FAM-04': ('Cuota Mens Parques', 3700, 'PE2', 10),
}
folio_n = {'C': 6000, 'K': 8000, 'A': 9500, 'APP': 100}
METODOS = ['Efectivo', 'Efectivo', 'Tarjeta de crédito', 'Tarjeta de débito', 'Transferencia', 'Cheque', 'Pago en app']
BANCOS = ['BBVA', 'Banorte', 'Santander', 'HSBC', 'Banamex', 'Scotiabank']
cargos_nuevos, recibos_nuevos = [], {}


def nuevo_folio(club, metodo):
    if metodo == 'Pago en app':
        folio_n['APP'] += 1
        return f'APP-{folio_n["APP"]:05d}', None
    serie = 'C' if club == 'PE1' else rnd.choice(['K', 'A'])
    folio_n[serie] += 1
    return f'{serie}{folio_n[serie]}', serie


def linea_pago(metodo, importe):
    l = {'METODO DE PAGO': metodo, 'IMPORTE': importe}
    if metodo in ('Tarjeta de crédito', 'Tarjeta de débito'):
        l['REFERENCIA'] = str(rnd.randint(100000, 999999))
    elif metodo == 'Transferencia':
        l.update({'REFERENCIA': f'SPEI-{rnd.randint(10000, 99999)}', 'BANCO': rnd.choice(BANCOS)})
    elif metodo == 'Cheque':
        l.update({'BANCO': rnd.choice(BANCOS), 'NUMERO DE CHEQUE': f'{rnd.randint(1, 9999):04d}'})
    elif metodo == 'Pago en app':
        l['REFERENCIA'] = f'APP-{rnd.randint(100000, 999999)}'
    return l


def cobrar(cuenta, club, fecha, meses_cargos, metodo=None, dos_metodos=False, notas=None):
    """meses_cargos = lista de filas de cargo (sin folio) que paga este recibo."""
    metodo = metodo or rnd.choice(METODOS)
    folio, serie = nuevo_folio(club, metodo)
    total = 0
    for c in meses_cargos:
        c['FOLIO DEL RECIBO'] = folio
        total += c.get('IMPORTE PAGADO', c['IMPORTE'] - c.get('DESCUENTO', 0))
    if dos_metodos and metodo != 'Pago en app' and total > 1000:
        efectivo = round(total * 0.4 / 100) * 100
        lineas = [linea_pago('Efectivo', efectivo), linea_pago(rnd.choice(['Tarjeta de crédito', 'Tarjeta de débito']), total - efectivo)]
    else:
        lineas = [linea_pago(metodo, total)]
    extra = {}
    if serie:
        extra['SERIE DE CAJA'] = serie
    if rnd.random() < 0.6:
        extra['HORA DEL PAGO'] = f'{rnd.randint(8, 19):02d}:{rnd.choice([0, 10, 15, 25, 30, 45, 50]):02d}'
    if notas:
        extra['NOTAS'] = notas
    recibos_nuevos[folio] = {'cuenta': cuenta, 'club': club, 'fecha': fecha, 'lineas': lineas, 'extra': extra}


def mes_cargo(cuenta, concepto, cuota, anio, mes):
    c = {'REFERENCIA DEL CARGO': f'{cuenta}-M{anio}{mes:02d}', 'NUMERO DE CUENTA': cuenta, 'CONCEPTO DE COBRO': concepto,
         'IMPORTE': cuota, 'AÑO DEL PERIODO': anio, 'MES DEL PERIODO': mes}
    cargos_nuevos.append(c)
    return c


def dia_pago(mes):
    return f'2026-{mes:02d}-{rnd.randint(1, 10):02d}'


PATRONES = ['mensual', 'mensual', 'trimestral', 'debe_3', 'debe_1', 'app', 'anual', 'semestral', 'mensual_dos_metodos', 'atrasado']

for i, (cuenta, (concepto, cuota, club)) in enumerate(cobro.items()):
    patron = PATRONES[i % len(PATRONES)]
    if patron == 'anual':
        meses = [mes_cargo(cuenta, concepto, cuota, 2026, m) for m in range(1, 13)]
        meses[-1]['IMPORTE PAGADO'] = 0
        meses[-1]['DESCUENTO'] = cuota
        cobrar(cuenta, club, f'2026-01-{rnd.randint(5, 20):02d}', meses, 'Transferencia', notas='Pago anual: diciembre gratis')
        continue
    meses = [mes_cargo(cuenta, concepto, cuota, 2026, m) for m in range(1, 10)]
    if patron in ('mensual', 'app', 'mensual_dos_metodos'):
        for m, c in enumerate(meses, 1):
            cobrar(cuenta, club, dia_pago(m), [c], 'Pago en app' if patron == 'app' else None, dos_metodos=(patron == 'mensual_dos_metodos'))
    elif patron == 'trimestral':
        for t in range(3):
            cobrar(cuenta, club, dia_pago(1 + 3 * t), meses[3 * t:3 * t + 3])
    elif patron == 'semestral':
        cobrar(cuenta, club, dia_pago(1), meses[:6])
        cobrar(cuenta, club, dia_pago(7), meses[6:])
    elif patron == 'debe_3':       # pagado hasta junio, debe julio-septiembre
        for m, c in enumerate(meses[:6], 1):
            cobrar(cuenta, club, dia_pago(m), [c])
    elif patron == 'debe_1':       # debe solo septiembre
        cobrar(cuenta, club, dia_pago(1), meses[:4])
        cobrar(cuenta, club, dia_pago(5), meses[4:8])
    elif patron == 'atrasado':     # paga dos meses juntos con atraso; agosto con descuento parcial; septiembre pendiente
        meses[7]['DESCUENTO'] = round(cuota * 0.1)
        for m in range(0, 8, 2):
            cobrar(cuenta, club, dia_pago(m + 2), meses[m:m + 2], notas='Pago atrasado de dos meses')

for cuenta, (concepto, cuota, club, primer_mes) in cobro_original.items():
    meses = [mes_cargo(cuenta, concepto, cuota, 2026, m) for m in range(1, primer_mes)]
    for m in range(0, len(meses), 3):
        cobrar(cuenta, club, dia_pago(m + 1), meses[m:m + 3])

# Otros cargos para las cuentas nuevas
for i, cuenta in enumerate(list(cobro)[::3]):
    club = cobro[cuenta][2]
    tipo = i % 4
    if tipo == 0:
        c = {'REFERENCIA DEL CARGO': f'{cuenta}-CRED', 'NUMERO DE CUENTA': cuenta, 'CONCEPTO DE COBRO': 'Cuota repo. credencia',
             'IMPORTE': 200, 'FECHA DE EMISION': '2026-05-12'}
        cargos_nuevos.append(c)
        cobrar(cuenta, club, '2026-05-12', [c], 'Efectivo')
    elif tipo == 1:
        c = {'REFERENCIA DEL CARGO': f'{cuenta}-CURSO', 'NUMERO DE CUENTA': cuenta, 'CONCEPTO DE COBRO': 'Cuota curso de verano',
             'IMPORTE': 4500, 'FECHA DE EMISION': '2026-06-25'}
        cargos_nuevos.append(c)
        cobrar(cuenta, club, '2026-06-25', [c])
    elif tipo == 2:
        cargos_nuevos.append({'REFERENCIA DEL CARGO': f'{cuenta}-ADE', 'NUMERO DE CUENTA': cuenta, 'CONCEPTO DE COBRO': 'Cuota adeudo anterior',
                              'IMPORTE': rnd.choice([1500, 2800, 6000]), 'FECHA DE EMISION': '2026-01-01', 'DESCRIPCION': 'Adeudo de Fox 2025'})
    else:
        c = {'REFERENCIA DEL CARGO': f'{cuenta}-PASE', 'NUMERO DE CUENTA': cuenta,
             'CONCEPTO DE COBRO': 'Pase por día' if club == 'PE1' else 'Cuota pase diario', 'IMPORTE': 300 if club == 'PE1' else 400,
             'FECHA DE EMISION': '2026-08-09'}
        cargos_nuevos.append(c)
        cobrar(cuenta, club, '2026-08-09', [c], 'Efectivo')

# ── Casilleros nuevos con su cargo anual ──
cuenta_de = {}
for x in todos_integrantes:
    cuenta_de.setdefault(x['ID DE USUARIO'], x['NUMERO DE CUENTA'])
sexo_de = {u['ID DE USUARIO']: u.get('SEXO') for u in todos_usuarios}
nac_de = {u['ID DE USUARIO']: u['FECHA DE NACIMIENTO'] for u in todos_usuarios}
ocupados = {('PE1', 'Caballeros', 10), ('PE1', 'Damas', 15), ('PE1', 'Niños', 7), ('PE2', 'Damas', 5), ('PE2', 'Caballeros', 20), ('PE2', 'Niños', 3)}
casilleros_nuevos = []
candidatos = [x for x in integrantes_nuevos if x['NUMERO DE CUENTA'] in cobro or x['NUMERO DE CUENTA'][:3] == 'PE1'][::4][:24]
usados = set()
for k, x in enumerate(candidatos):
    pid, cuenta = x['ID DE USUARIO'], x['NUMERO DE CUENTA']
    if pid in usados:
        continue
    usados.add(pid)
    club = cuenta[:3]
    categoria = 'Niños' if nac_de[pid] > '2012' else ('Caballeros' if sexo_de[pid] == 'H' else 'Damas')
    numero = 21 + k
    while (club, categoria, numero) in ocupados:
        numero += 1
    ocupados.add((club, categoria, numero))
    ref = f'L-{100 + k:03d}'
    fila = {'CLUB': club, 'CATEGORIA': categoria, 'NUMERO DE CASILLERO': numero, 'ID DE USUARIO': pid,
            'FECHA DE INICIO': f'{rnd.randint(2015, 2025)}-{rnd.randint(1, 12):02d}-01'}
    cuenta_cobro = cuenta
    if k % 5 != 4:   # uno de cada cinco sin cargo
        c = {'REFERENCIA DEL CARGO': ref, 'NUMERO DE CUENTA': cuenta_cobro, 'CONCEPTO DE COBRO': 'CUOTA CASILLERO', 'IMPORTE': 1100,
             'FECHA DE EMISION': '2026-01-10'}
        cargos_nuevos.append(c)
        fila['REFERENCIA DEL CARGO'] = ref
        if k % 3 != 2:  # pagado; los demas quedan pendientes
            cobrar(cuenta_cobro, club, f'2026-01-{rnd.randint(10, 30):02d}', [c])
    casilleros_nuevos.append(fila)
agregar('Casilleros', casilleros_nuevos)

# ── Cuadre de los nuevos recibos ──
pagado = {}
for c in cargos_nuevos:
    if c.get('FOLIO DEL RECIBO'):
        pagado[c['FOLIO DEL RECIBO']] = pagado.get(c['FOLIO DEL RECIBO'], 0) + c.get('IMPORTE PAGADO', c['IMPORTE'] - c.get('DESCUENTO', 0))
for folio, r in recibos_nuevos.items():
    assert abs(sum(l['IMPORTE'] for l in r['lineas']) - pagado.get(folio, 0)) < 0.01, folio
assert not (set(recibos_nuevos) & set(recibos)), 'folio repetido'

agregar('Cargos', cargos_nuevos)
agregar('Pagos', [{'FOLIO DEL RECIBO': f, 'NUMERO DE CUENTA': r['cuenta'], 'CLUB DONDE SE COBRO': r['club'], 'FECHA DEL PAGO': r['fecha'],
                   **r['extra'], **l} for f, r in recibos_nuevos.items() for l in r['lineas']])

# ── Notas de cobranza para las cuentas que deben ──
notas = []
for i, (cuenta, _) in enumerate(cobro.items()):
    patron = PATRONES[i % len(PATRONES)]
    if patron == 'debe_3':
        notas.append({'NUMERO DE CUENTA': cuenta, 'NOTA': 'Debe julio a septiembre. Se envio recordatorio por correo.', 'FECHA DE LA NOTA': '2026-09-15'})
        notas.append({'NUMERO DE CUENTA': cuenta, 'NOTA': 'Promete pagar en la primera semana de octubre.'})
    elif patron == 'debe_1':
        notas.append({'NUMERO DE CUENTA': cuenta, 'NOTA': 'Septiembre pendiente; paga normalmente por adelantado.', 'FECHA DE LA NOTA': '2026-09-20'})
    elif patron == 'atrasado':
        notas.append({'NUMERO DE CUENTA': cuenta, 'NOTA': 'Descuento del 10% en agosto autorizado por gerencia.', 'FECHA DE LA NOTA': '2026-08-12'})
agregar('Notas de cobranza', notas)

# ── Reservaciones de usuarios de PE1 (o de ambos parques) ──
pe1 = [x['ID DE USUARIO'] for x in integrantes_nuevos if x['NUMERO DE CUENTA'].startswith('PE1')]
adultos_pe1 = [p for p in dict.fromkeys(pe1) if nac_de[p] < '2008']
menores_pe1 = [p for p in dict.fromkeys(pe1) if nac_de[p] >= '2010']
reservas = []
# Horarios ya usados por las reservaciones de casos_dinero_amenidades.py
ocup = {('Jardines', 'Jardín 1', '2026-10-17', '12:00'), ('Jardines', 'Jardín 2', '2026-11-07', '13:00'),
        ('Canchas de tenis', 'Cancha 1', '2026-10-05', '07:00'), ('Canchas de tenis', 'Cancha 3', '2026-10-07', '17:00'),
        ('Canchas de pádel', 'Cancha 1', '2026-09-20', '10:00'), ('Canchas de pádel', 'Cancha 3', '2026-09-21', '09:00'),
        ('Canchas de frontón', 'Cancha 1', '2026-09-10', '18:00'), ('Canchas de frontón', 'Cancha 2', '2026-09-29', '18:00'),
        ('Canchas de tenis', 'Cancha 2', '2026-10-10', '08:00'), ('Canchas de tenis', 'Cancha 3', '2026-10-07', '18:00'), ('Canchas de pádel', 'Cancha 2', '2026-09-15', '19:00'), ('Canchas de pádel', 'Cancha 1', '2026-09-20', '11:00'), ('Canchas de pádel', 'Cancha 2', '2026-09-15', '18:00')}
seq_r = [100]


def reservar(persona, amenidad, recurso, fecha, ini, fin, estatus, ref=False, **extra):
    clave = (amenidad, recurso, fecha, ini)
    if clave in ocup and amenidad != 'Alberca':
        return None
    ocup.add(clave)
    r = {'CLUB': 'PE1', 'ID DE USUARIO': persona, 'AMENIDAD': amenidad, 'RECURSO': recurso, 'FECHA': fecha,
         'HORA DE INICIO': ini, 'HORA DE FIN': fin, 'ESTATUS': estatus}
    if ref:
        seq_r[0] += 1
        r['REFERENCIA DE LA RESERVACION'] = f'R-{seq_r[0]:04d}'
    r.update({k: v for k, v in extra.items() if v is not None})
    reservas.append(r)
    return r


# Canchas por hora: pasadas (finalizada, asistido, inasistencia, cancelada) y futuras (activa, cancelada)
for k, p in enumerate(adultos_pe1):
    for j in range(2):
        pasada = (k + j) % 2 == 0
        dia = date(2026, 9, 1 + (k * 3 + j * 7) % 28) if pasada else date(2026, 10, 1 + (k * 3 + j * 5) % 30)
        hora = 8 + (k + j * 4) % 11
        amenidad, recurso = rnd.choice([('Canchas de tenis', f'Cancha {rnd.randint(1, 4)}'), ('Canchas de pádel', f'Cancha {rnd.randint(1, 5)}'),
                                        ('Canchas de frontón', f'Cancha {rnd.randint(1, 2)}')])
        if pasada:
            estatus = rnd.choice(['FINALIZADA', 'FINALIZADA', 'ASISTIDO', 'ASISTIDO', 'INASISTENCIA', 'CANCELADA'])
        else:
            estatus = rnd.choice(['ACTIVA'] * 5 + ['CANCELADA'])
        canc = (dia.replace(day=max(dia.day - 2, 1)).isoformat()) if estatus == 'CANCELADA' else None
        reservar(p, amenidad, recurso, dia.isoformat(), f'{hora:02d}:00', f'{hora + 1:02d}:00', estatus,
                 **{'FECHA DE CANCELACION': canc})

# Clases con entrenador, dentro de su horario
CLASES = [('Carlos Mendoza Ruiz', 'Canchas de tenis', 0, (7, 11)), ('Carlos Mendoza Ruiz', 'Canchas de tenis', 2, (16, 19)),
          ('Carlos Mendoza Ruiz', 'Canchas de tenis', 5, (8, 12)), ('Ana Torres', 'Canchas de pádel', 1, (9, 13)),
          ('Ana Torres', 'Canchas de pádel', 3, (9, 13)), ('Sofia Ramirez', 'Alberca', 4, (15, 18))]
alumnos = menores_pe1 + adultos_pe1[::3]
# Un horario libre distinto por clase: el entrenador nunca tiene dos clases a la misma hora
ocupado_coach = {('Carlos Mendoza Ruiz', '2026-10-05', 7), ('Carlos Mendoza Ruiz', '2026-10-07', 17), ('Ana Torres', '2026-09-21', 9)}
slots = []
for semana in range(7):   # semanas del 7 de septiembre al 19 de octubre
    for coach, amenidad, dia_semana, (h1, h2) in CLASES:
        dia = date.fromordinal(date(2026, 9, 7).toordinal() + 7 * semana + dia_semana)
        for hora in range(h1, h2):
            if (coach, dia.isoformat(), hora) not in ocupado_coach:
                slots.append((coach, amenidad, dia, hora))
rnd.shuffle(slots)
for k, p in enumerate(alumnos):
    coach, amenidad, dia, hora = slots[k]
    recurso = 'Alberca' if amenidad == 'Alberca' else f'Cancha {1 + k % 3}'
    estatus = ('ASISTIDO' if k % 4 else 'INASISTENCIA') if dia < date(2026, 9, 30) else 'ACTIVA'
    reservar(p, amenidad, recurso, dia.isoformat(), f'{hora:02d}:00', f'{hora + 1:02d}:00', estatus, ENTRENADOR=coach)

# Alberca por cupo: varias personas en el mismo horario (maximo 5)
for k, p in enumerate(adultos_pe1[:12]):
    reservar(p, 'Alberca', 'Alberca', f'2026-10-{10 + k // 4:02d}', '09:00', '10:00', 'ACTIVA')

# Jardines por dia (algunos con asador y con lista de invitados)
jardines = []
for k, p in enumerate(adultos_pe1[:10]):
    fecha = date(2026, 10, 3 + 7 * (k // 4)) if k < 8 else date(2026, 8, 15 + k)
    estatus = 'ACTIVA' if fecha > date(2026, 9, 30) else 'FINALIZADA'
    r = reservar(p, 'Jardines', f'Jardín {1 + k % 4}', fecha.isoformat(), '12:00', '20:00', estatus, ref=True,
                 ASADOR=f'Asador {1 + k % 7}' if k % 2 == 0 else None,
                 **{'REQUIERE CARPA': 'SI' if k % 3 == 0 else 'NO', 'MESAS': 4 + k, 'SILLAS': 30 + 5 * k, 'NOTAS': rnd.choice(['Cumpleaños', 'Comida familiar', 'Bautizo', 'Aniversario'])})
    if r:
        jardines.append(r)
agregar('Reservaciones', reservas)

# ── Listas de invitados para los jardines ──
INV_NOMBRES = NOMBRES_H + NOMBRES_M
listas = []
for k, r in enumerate(jardines):
    ref = f'L-{1000 + k}'
    estatus = ['ACEPTADA', 'ACEPTADA', 'PENDIENTE', 'RECHAZADA'][k % 4] if r['ESTATUS'] == 'ACTIVA' else 'ACEPTADA'
    invitados = []
    sub = 0
    for j in range(rnd.randint(2, 7)):
        edad = rnd.choice([3, 6, 9, 15, 28, 35, 42, 60])
        apellido = rnd.choice(APELLIDOS)
        inv_ = {'NOMBRE DEL INVITADO': rnd.choice(INV_NOMBRES), 'APELLIDO DEL INVITADO': apellido, 'EDAD': edad}
        if j % 3 != 2:
            inv_['CORREO DEL INVITADO'] = f'inv{k}{j}@prueba.local'
        if j == 0 and k % 3 == 0:
            inv_['CORTESIA'] = 'SI'
        else:
            sub += 300 if edad >= 7 else 150
        invitados.append(inv_)
    datos = {'REFERENCIA DE LA LISTA': ref, 'CLUB': 'PE1', 'ID DE USUARIO': r['ID DE USUARIO'], 'FECHA': r['FECHA'], 'ESTATUS': estatus,
             'REFERENCIA DE LA RESERVACION': r['REFERENCIA DE LA RESERVACION'], 'TITULO': r.get('NOTAS', 'Evento'), 'HORA': '12:00'}
    if estatus == 'ACEPTADA':
        datos['FECHA DE APROBACION'] = '2026-09-20' if r['ESTATUS'] == 'ACTIVA' else r['FECHA']
    if estatus == 'RECHAZADA':
        datos['COMENTARIOS'] = 'Excede el cupo del jardin'
    listas += [{**datos, **i} for i in invitados]
    if estatus == 'ACEPTADA' and sub:
        cuenta = cuenta_de[r['ID DE USUARIO']]
        c = {'REFERENCIA DEL CARGO': f'{ref}-CARGO', 'NUMERO DE CUENTA': cuenta, 'CONCEPTO DE COBRO': 'Lista de invitados', 'IMPORTE': sub,
             'FECHA DE EMISION': datos['FECHA DE APROBACION'], 'DESCRIPCION': f'Lista de invitados {ref}'}
        extra_cargos = [c]
        if k % 2 == 0:
            recibos_antes = set(recibos_nuevos)
            cobrar(cuenta, 'PE1', datos['FECHA DE APROBACION'], [c], 'Efectivo')
            folio = (set(recibos_nuevos) - recibos_antes).pop()
            r_ = recibos_nuevos[folio]
            agregar('Pagos', [{'FOLIO DEL RECIBO': folio, 'NUMERO DE CUENTA': r_['cuenta'], 'CLUB DONDE SE COBRO': r_['club'],
                               'FECHA DEL PAGO': r_['fecha'], **r_['extra'], **l} for l in r_['lineas']])
        agregar('Cargos', extra_cargos)
        cargos_nuevos += extra_cargos
agregar('Listas de invitados', listas)

print('volumen:', len(usuarios_nuevos), 'usuarios,', len(cuentas_nuevas), 'cuentas,', len(integrantes_nuevos), 'integrantes,',
      len(medica), 'medica,', len(contactos), 'contactos,', len(documentos_nuevos), 'documentos,', len(cargos_nuevos), 'cargos,',
      len(recibos_nuevos), 'recibos,', len(casilleros_nuevos), 'casilleros,', len(reservas), 'reservaciones,', len(listas), 'invitados,',
      len(notas), 'notas')
