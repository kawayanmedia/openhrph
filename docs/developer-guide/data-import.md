# Data import

The data import lets one Excel workbook add or update offices, departments, payroll groups, positions, schedules, and employees in a single all-or-nothing run. This page explains the code so you can change the template, add a sheet, or debug a rejected workbook.

Users see the feature under **Settings → Company → Data Import**; the [user guide](../user-guide/data-import.md) describes the screens.

## Entry point

`App\Livewire\Admin\Imports\DataImport` is a full-page Livewire component routed at `GET app/imports` (route name `admin.import`) behind the `web`, `auth:web,nova`, `can:<admin.gate>`, and `demo.verified` middleware. The workbook download links point at `App\Http\Controllers\Admin\ImportTemplateController` (`admin.import.template`, with `?sample=1` or `?blank=1`).

The component does very little:

| Action | What it does |
| --- | --- |
| `updatedFile` | Resets the previous result and validates the upload (`xlsx`/`xls`, `imports.max_upload_kb`). |
| `previewWorkbook` | Calls `WorkbookImporter::preview()` and stores the payload in `$preview`, `$problems`, and `$previewed`. |
| `import` | Refuses unless the last preview was clean, then calls `WorkbookImporter::import()` and shows the summary. |
| `downloadProblems` | Streams the problem list as CSV. |

> **Livewire naming rule.** A public property and an action must never share a name. Livewire's browser-side proxy resolves `wire:model` data first, so an action called `preview()` alongside a `$preview` property is silently uncallable — the header button did nothing at all. This is why the action is named `previewWorkbook`. `tests/Feature/WorkbookImportTest.php` asserts no public property collides with an action.

## Pipeline

```
DataImport (Livewire)
  └── WorkbookImporter::preview() / import()
        ├── analyse()            reset → read → match sheets → validate every sheet → resolve references → sort problems
        ├── buildPreview()       ImportPreview (counts, sample rows, warnings) — no writes
        └── persist()            DB::transaction() → ImportReport
WorkbookReader                   PhpSpreadsheet, setReadDataOnly(true), row cap
OpenHrWorkbook                   WorkbookDefinition: the sheet list, in insert order
  └── SheetDefinition            one sheet: columns, keys, validation, fill, afterSave
        └── Column               heading, type, rules, aliases, options, template example/help
```

Import order is the order of `OpenHrWorkbook::sheets()`. Referenced records (offices, departments, payroll groups, positions) are created before the employee rows that point at them, so the workbook is self-sufficient.

`WorkbookImporter` keeps per-run state (`data`, `rows`, `letters`, `problems`, …) and is resolved from the container per action call; `analyse()` resets it first. Do not bind it as a singleton.

### Validation phases

1. **Read** — `WorkbookReader` accepts only `xlsx`/`xls` and refuses unreadable or corrupt files with `ImportFormatException`. A sheet with more than `imports.max_rows` data rows is not read; the importer reports the count as a problem so the user can split the file.
2. **Match sheets** — titles are matched against each definition's `title()` and `aliases()` using `Column::signature()` (case, spacing, and punctuation insensitive). `ignoredSheetNames()` (the `Instructions` sheet) is skipped. A known but empty sheet is allowed; an unknown sheet with data is a problem.
3. **Map columns** — every heading is matched with `Column::matches()`. Unknown headings, duplicate headings, and missing required headings are problems, and the sheet stops there.
4. **Validate rows** — unlabelled cells, formulas (any cell starting with `=`), type normalisation (`Column::normalise()`), required cells, then the Laravel `rules` on the row and `SheetDefinition::validateValues()` for cross-field rules. Duplicate natural keys and `secondaryUniqueColumns()` (for example biometric ID) are reported against the later row.
5. **Resolve references** — values such as Office, Department, Payroll Group, Position, and the workbook-level `externalReferences()` (employment type) must exist in the workbook or in the tenant.
6. **Sort problems** — workbook-level first, then by sheet order, row, and column, so the report reads top to bottom.

`preview()` runs steps 1–6 and then `buildPreview()`; `import()` runs the same steps and throws `ImportValidationException` with the collected `ImportProblem`s before writing anything. `persist()` wraps the whole write in one transaction, so a failure never leaves a partial workbook.

## Key classes

| Class | Responsibility |
| --- | --- |
| `WorkbookDefinition` (`OpenHrWorkbook`) | The sheet list in insert order, sheet-name matching, ignored sheets, external references. Bound as an interface in `AppServiceProvider`. |
| `SheetDefinition` + `app/Imports/Sheets/*Sheet.php` | One sheet: `columns()`, `keyColumns()` (natural key), `model()`, `fill()`, plus hooks `aliases()`, `secondaryUniqueColumns()`, `sampleRows()`, `referenceType()`/`referenceNameColumn()`/`referenceCodeColumn()`, `validateValues()`, `validateReferences()`, `warnings()`, `afterSave()`, `registerReference()`. |
| `Column`, `ColumnType` | One object builds the template header **and** normalises/validates the cell, so the sample workbook and the importer cannot drift. Types: Text, Integer, Decimal, Date, Time, Boolean. |
| `ReferenceIndex` | Tenant lookup for referenced records, and the `new`/`existing` label the preview shows. |
| `ImportPreview`, `ImportReport`, `ImportProblem`, `SheetData` | Read-only result objects. `ImportProblem::toArray()` includes a `location` string. |
| `SampleWorkbook` | Builds the downloadable blank template and the filled sample from each sheet's `columns()` and `sampleRows()`. |
| Exceptions | `ImportFormatException` (unreadable/unsupported file) and `ImportValidationException` (workbook rejected, carries every problem). |

## Add a column

1. Add a `Column` to the sheet's `columns()`. Required columns must be present in the header row; `aliases` accept alternative headings; `rules` and `messages` are Laravel validation rules; `options` drive the template dropdown.
2. Map the normalised key to the model in the sheet's `fill()` (and to the display in `sampleRows()` if the sample should show it).
3. If the column participates in matching, update `keyColumns()`/`secondaryUniqueColumns()` instead of adding ad-hoc checks.
4. Run `php artisan test --compact tests/Feature/WorkbookImportTest.php`. The sample-workbook round-trip test fails if a new sample value is invalid.

## Add a sheet

1. Create `app/Imports/Sheets/<Name>Sheet.php` extending `SheetDefinition` and implement `key()`, `title()`, `columns()`, `keyColumns()`, `model()`, and `fill()`.
2. Register it in `OpenHrWorkbook::sheets()` **in dependency order** — referenced records before the rows that point at them.
3. Add `aliases()` (singular sheet name), `sampleRows()` so the template is filled, and `referenceType()`/`referenceNameColumn()` if other sheets may point at it.
4. Add `afterSave()` for derived data (payroll groups generate their payroll dates there) and `warnings()` for changes the preview should call out.
5. Add `externalReferences()` entries in `OpenHrWorkbook` for values that are never imported (employment types today).

## Configuration

`config/imports.php`, all overridable by environment:

| Key | Env | Default | Used by |
| --- | --- | --- | --- |
| `max_upload_kb` | `IMPORT_MAX_UPLOAD_KB` | `10240` | Upload validation in `DataImport::rules()` |
| `max_rows` | `IMPORT_MAX_ROWS` | `5000` | `WorkbookReader` row cap and the per-sheet check |
| `preview_rows` | `IMPORT_PREVIEW_ROWS` | `5` | Rows shown per sheet in the preview |
| `display_problems` | `IMPORT_DISPLAY_PROBLEMS` | `200` | Problems rendered on the page (the CSV has all of them) |

## Testing

`tests/Feature/WorkbookImportTest.php` covers the importer and the component: full import, preview without writes, preview updates for existing records, reference resolution, every problem shape, idempotent re-imports, the generated sample workbook, and the Livewire action/property rule.

Helpers in that file build workbooks in memory (`importWorkbookBytes()`, `importWorkbookFile()`) and `importTenant()` creates a company with the tenant context set and the `Regular` employment type. When you change a sheet, extend `validImportSheets()` rather than adding a second fixture.

## Related pages

- [Admin panel](admin-panel.md) — the Livewire resource pattern the rest of the workspace uses.
- [Multi-tenancy](../tenancy.md) — `TenantContext` and why `analyse()` refuses to run without a company.
- [Testing](testing.md) — suite layout and commands.
