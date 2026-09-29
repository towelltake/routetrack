// One-shot script: build journey-plan-template.xlsx — header row matches the
// importer's auto-suggest regex (validate.ts), example rows show acceptable
// formats, and an Instructions sheet documents each field's range/format.
const ExcelJS = require('../app/node_modules/exceljs');
const path = require('node:path');

const out = path.resolve(__dirname, '..', 'journey-plan-template.xlsx');

const wb = new ExcelJS.Workbook();
wb.creator = 'Journey Plan App';
wb.created = new Date();

// ---- Sheet 1: Customers (the import target) ----
const ws = wb.addWorksheet('Customers', { views: [{ state: 'frozen', ySplit: 1 }] });

const columns = [
  { header: 'external_code',        key: 'external_code',        width: 14 },
  { header: 'name',                 key: 'name',                 width: 32 },
  { header: 'address',              key: 'address',              width: 40 },
  { header: 'lat',                  key: 'lat',                  width: 11 },
  { header: 'lng',                  key: 'lng',                  width: 11 },
  { header: 'facetime_minutes',     key: 'facetime_minutes',     width: 16 },
  { header: 'monthly_frequency',    key: 'monthly_frequency',    width: 18 },
  { header: 'allowed_days',         key: 'allowed_days',         width: 24 },
  { header: 'visit_window_start',   key: 'visit_window_start',   width: 18 },
  { header: 'visit_window_end',     key: 'visit_window_end',     width: 18 },
  { header: 'area',                 key: 'area',                 width: 18 },
  { header: 'region',               key: 'region',               width: 18 },
  { header: 'channel',              key: 'channel',              width: 10 },
  { header: 'pinned_salesman_name', key: 'pinned_salesman_name', width: 22 },
];
ws.columns = columns;

ws.getRow(1).font = { bold: true };
ws.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE8F0FE' } };
ws.getRow(1).alignment = { vertical: 'middle' };

const examples = [
  {
    external_code: 'CCID-1001',
    name: 'Al Fair Mart — Qurum',
    address: 'Way 3017, Qurum, Muscat',
    lat: 23.6105, lng: 58.4737,
    facetime_minutes: 20,
    monthly_frequency: 4,
    allowed_days: 'sun,mon,tue,wed,thu',
    visit_window_start: '',
    visit_window_end: '',
    area: 'Qurum',
    region: 'Muscat',
    channel: 'TT',
    pinned_salesman_name: '',
  },
  {
    external_code: 'CCID-1002',
    name: 'LuLu Hypermarket — Bawshar',
    address: 'Bawshar, Muscat',
    lat: 23.5934, lng: 58.4036,
    facetime_minutes: 30,
    monthly_frequency: 2,
    allowed_days: '',
    visit_window_start: '09:00',
    visit_window_end: '12:00',
    area: 'Bawshar',
    region: 'Muscat',
    channel: 'MT',
    pinned_salesman_name: '',
  },
  {
    external_code: 'CCID-1003',
    name: 'Sultan Center — Dhank',
    address: '',
    lat: 23.5475, lng: 56.2683,
    facetime_minutes: 25,
    monthly_frequency: 4,
    allowed_days: '0,1,2,3,4',
    visit_window_start: '',
    visit_window_end: '',
    area: 'Dhank',
    region: 'Nizwa',
    channel: 'MT',
    pinned_salesman_name: 'Ahmed Said',
  },
  {
    external_code: 'CCID-1004',
    name: 'Khimji Mart — Ruwi',
    address: 'CBD Ruwi, Muscat',
    lat: 23.5859, lng: 58.5550,
    facetime_minutes: 15,
    monthly_frequency: 1,
    allowed_days: 'sun;wed',
    visit_window_start: '',
    visit_window_end: '',
    area: 'Ruwi',
    region: 'Muscat',
    channel: 'TT',
    pinned_salesman_name: '',
  },
];
examples.forEach((row) => ws.addRow(row));

// ---- Sheet 2: Instructions ----
const help = wb.addWorksheet('Instructions');
help.columns = [
  { header: 'Field', width: 22 },
  { header: 'Required?', width: 14 },
  { header: 'Type / format', width: 32 },
  { header: 'Range / examples', width: 56 },
  { header: 'Notes', width: 60 },
];
help.getRow(1).font = { bold: true };
help.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE8F0FE' } };

const fields = [
  ['external_code',      'Yes', 'Text', 'CCID-1001, ACCT-99, etc.',
   'Your unique customer ID. Becomes the natural key. Must be unique within the upload.'],
  ['name',               'Yes', 'Text', 'Al Fair Mart — Qurum',
   'Customer display name. Shown in the UI and on the calendar grid.'],
  ['address',            'No', 'Text',
   'Way 3017, Qurum, Muscat',
   'Display-only reference. The app routes purely on lat/lng — there is no geocoding.'],
  ['lat',                'Yes', 'Decimal degrees', '23.5880',
   'Must be within Oman bounds [16, 28]. Required — rows without coordinates are rejected.'],
  ['lng',                'Yes', 'Decimal degrees', '58.3829',
   'Must be within Oman bounds [52, 60]. Required — rows without coordinates are rejected.'],
  ['facetime_minutes',   'No', 'Integer', '1–240',
   'Time the salesman spends on-site per visit. Defaults to the app setting (15 min) if blank.'],
  ['monthly_frequency',  'No', 'Integer', '1–20',
   'Total visits per 4-week period. 1=monthly, 2=fortnightly, 4=weekly, 8=twice/week, 16=4×/week, 20=daily Sun–Thu. Defaults to the app setting if blank.'],
  ['allowed_days',       'No', 'Day list',
   '"sun,mon,tue,wed,thu" or "0,1,2,3,4" — separators: , ; | space',
   '0=Sun … 6=Sat. Names also accepted (sun/mon/.../sat). Leave blank to allow any working day.'],
  ['visit_window_start', 'No', 'Time (HH:MM)', '09:00',
   'Earliest acceptable visit start, e.g. MT receiving hours. Must be paired with visit_window_end. The optimizer treats the window as a strong preference — a visit it genuinely cannot fit inside the window shows red on the calendar instead of being dropped.'],
  ['visit_window_end',   'No', 'Time (HH:MM)', '12:00',
   'Latest acceptable visit start. Must be paired with visit_window_start and be after it. Leave both blank for "any time of day".'],
  ['area',               'No', 'Text', 'Dhank, Bawshar, Mirbat, Ibri',
   'Narrow free-text tag (typically a sub-wilayat). Each salesman with an "assigned areas" list covers customers whose area is in that list.'],
  ['region',             'No', 'Text', 'Nizwa, Muscat, Salalah, Sohar',
   'Broader parent grouping (wilayat / governorate). Each salesman can carry an "assigned regions" list independent of areas. A customer is eligible if EITHER its area OR its region is in the salesman\'s lists, so a Dhank customer matches a "Nizwa" region salesman without needing per-area coverage. Leave blank if you only track at one level.'],
  ['channel',            'No', 'Enum', 'MT, TT, WS, or blank',
   'FMCG trade channel. MT = Modern Trade (hypermarkets, supermarkets, chains). TT = Traditional Trade (small grocers, kiosks). WS = Wholesale (bulk distributors, cash-and-carry). Each salesman has a "channel skills" list (any subset of MT, TT, WS) and only serves customers whose channel they cover. Leave blank to let any salesman serve.'],
  ['pinned_salesman_name', 'Recommended for relationship-critical accounts', 'Text', 'Ahmed Said',
   'Hard pin — this customer will only ever be assigned to this salesman, every week. Use for accounts where customer-salesman relationship matters (key accounts, high-value clients, customers who insist on a specific rep). Must match the salesman name exactly. Pin overrides area AND channel. Leave blank for routine customers and let the optimizer pick.'],
];
fields.forEach((r) => help.addRow(r));

help.eachRow((row, n) => { if (n > 1) row.alignment = { vertical: 'top', wrapText: true }; });

const intro = help.getRow(help.rowCount + 2);
intro.getCell(1).value = 'How to use this template';
intro.getCell(1).font = { bold: true, size: 12 };
const notes = [
  '1. Fill in one row per customer on the "Customers" sheet. Keep the header row as-is.',
  '2. external_code, name, lat, and lng are required for every row.',
  '3. Address is reference-only — routing uses the lat/lng pin exclusively.',
  '4. Optional fields (facetime, frequency, allowed_days, visit windows, area, pinned_salesman_name) can be left blank — defaults apply.',
  '5. Save the file, then in the app go to Import → pick this file → confirm the column mapping → Commit.',
  '6. Each upload REPLACES the active customer set; the previous dataset is archived and restorable from Settings.',
];
notes.forEach((line, i) => {
  const r = help.getRow(help.rowCount + 1);
  r.getCell(1).value = line;
  r.getCell(1).alignment = { wrapText: true };
});

wb.xlsx.writeFile(out).then(() => console.log('wrote', out));
