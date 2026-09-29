import fs from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { Workbook, SpreadsheetFile } from '@oai/artifact-tool';

const root = 'C:/Apache24/htdocs/ParquesEsp';
const py = 'C:/Users/OsirisTK/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe';
const output = `${root}/Plantilla_Migracion_Cliente.xlsx`;

const extract = String.raw`
import json,openpyxl,datetime
from pathlib import Path
root=Path(r'C:\Apache24\htdocs\ParquesEsp')
w=openpyxl.load_workbook(root/'database/seeders/data/01_CATALOGOS.xlsx',read_only=True,data_only=True)
def clean(v):
    if isinstance(v,(datetime.datetime,datetime.date)): return v.isoformat()
    return v
blocks=[]
for s in w:
    if s.title=='INSTRUCCIONES': continue
    rows=[[clean(v) for v in row] for row in s.iter_rows(min_row=4,values_only=True) if any(v is not None for v in row)]
    if not rows: continue
    blocks.append({'name':s.title,'headers':[clean(v) for v in next(s.iter_rows(min_row=2,max_row=2,values_only=True))], 'hints':[clean(v) for v in next(s.iter_rows(min_row=3,max_row=3,values_only=True))], 'rows':rows})
c=openpyxl.load_workbook(root/'database/seeders/data/02_CLUBES_Y_CONFIGURACION.xlsx',read_only=True,data_only=True)['CLUBES']
clubs=[[r[0],r[1],r[2]] for r in c.iter_rows(min_row=4,values_only=True) if r[0] is not None]
blocks.insert(0,{'name':'CLUBES','headers':['NOMBRE DEL CLUB','CODIGO DEL CLUB','ACTIVO'],'hints':['Nombre que se usara en otras hojas','Clave del club','SI o NO'],'rows':clubs})
print(json.dumps(blocks,ensure_ascii=False,separators=(',',':')))
`;

const extracted = spawnSync(py, ['-c', extract], { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 });
if (extracted.status !== 0) throw new Error(extracted.stderr || 'Fallo la lectura de catalogos');
const blocks = JSON.parse(extracted.stdout);

const order = [
  'CLUBES', 'TIPOS MEMBRESIA', 'METODOS DE PAGO', 'CONCEPTOS DE COBRO',
  'PARENTESCOS', 'ESTADOS CIVILES', 'TIPOS DOCUMENTO', 'DOCS POR PARENTESCO',
  'DOCS POR MEMBRESIA', 'MOTIVOS CANCELACION', 'MOTIVOS SEPARACION',
  'REGLAS DE DESCUENTO', 'REGLAS DE PRECIOS', 'ESPECIALIDADES',
  'ESTATUS RESERVACION', 'CATEGORIAS CASILLERO', 'PAISES', 'ESTADOS',
  'NACIONALIDADES'
];
const ordered = order.map(name => blocks.find(b => b.name === name)).filter(Boolean);
const cities = blocks.find(b => b.name === 'CIUDADES');
if (!cities || ordered.length + 1 !== blocks.length) {
  throw new Error(`Catalogos no contemplados: ${blocks.filter(b => !ordered.includes(b) && b !== cities).map(b => b.name)}`);
}

function noAccents(v) {
  if (v == null) return null;
  return String(v)
    .replace(/[áàäâ]/g, 'a').replace(/[ÁÀÄÂ]/g, 'A')
    .replace(/[éèëê]/g, 'e').replace(/[ÉÈËÊ]/g, 'E')
    .replace(/[íìïî]/g, 'i').replace(/[ÍÌÏÎ]/g, 'I')
    .replace(/[óòöô]/g, 'o').replace(/[ÓÒÖÔ]/g, 'O')
    .replace(/[úùüû]/g, 'u').replace(/[ÚÙÜÛ]/g, 'U')
    .replace(/01_CATALOGOS\s*-\s*/g, 'esta pestaña: ')
    .replace(/la hoja de Socios/g, 'Usuarios')
    .replace(/la hoja de clubes/g, 'la seccion CLUBES de esta pestaña');
}

const wb = Workbook.create();
const sheet = wb.worksheets.add('Catalogos');
sheet.showGridLines = false;
sheet.tabColor = '#C0392B';
sheet.getRange('A:K').format.columnWidth = 25;
sheet.getRange('L:L').format.columnWidth = 3;
sheet.getRange('M:O').format.columnWidth = 26;

function putBlock(block, startRow, startCol) {
  const width = block.headers.length;
  const title = sheet.getRangeByIndexes(startRow, startCol, 1, width);
  const headers = sheet.getRangeByIndexes(startRow + 1, startCol, 1, width);
  const hints = sheet.getRangeByIndexes(startRow + 2, startCol, 1, width);
  title.values = [[block.name, ...Array(width - 1).fill(null)]];
  headers.values = [block.headers];
  hints.values = [block.hints.map(noAccents)];
  title.format = { fill: '#1A252F', font: { name: 'Arial', size: 10, bold: true, color: '#FFFFFF' }, rowHeight: 22 };
  headers.format = { fill: '#C0392B', font: { name: 'Arial', size: 10, bold: true, color: '#FFFFFF' }, rowHeight: 32, wrapText: true, verticalAlignment: 'center' };
  hints.format = { fill: '#ECF0F1', font: { name: 'Arial', size: 9, color: '#555555' }, rowHeight: 62, wrapText: true, verticalAlignment: 'center' };
  for (let i = 0; i < block.rows.length; i += 1000) {
    const chunk = block.rows.slice(i, i + 1000);
    sheet.getRangeByIndexes(startRow + 3 + i, startCol, chunk.length, width).values = chunk;
  }
  return startRow + 3 + block.rows.length;
}

let row = 0;
for (const block of ordered) row = putBlock(block, row + (row ? 2 : 0), 0);
putBlock(cities, 0, 12);
sheet.freezePanes.freezeRows(3);
wb.recalculate();

const check = await wb.inspect({ kind: 'region', sheetId: 'Catalogos', range: 'A1:C9', maxChars: 900 });
console.log(check.ndjson);
const preview = await wb.render({ sheetName: 'Catalogos', range: 'A1:F12', scale: 1.4, format: 'png' });
await fs.writeFile(`${root}/.codex_tmp/catalogos_preview.png`, new Uint8Array(await preview.arrayBuffer()));
const xlsx = await SpreadsheetFile.exportXlsx(wb);
await xlsx.save(output);
console.log(JSON.stringify({output, sections: blocks.length, cities: cities.rows.length, otherRows: ordered.reduce((n,b)=>n+b.rows.length,0)}));
