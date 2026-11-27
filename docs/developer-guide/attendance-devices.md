# Attendance devices

This page explains how OpenHR PH accepts time logs from biometric devices: the
ADMS push protocol, the pull drivers for ZKTeco, Hikvision, Dahua and Suprema,
the on-premise agent, and the import path for any vendor export.

The feature lives in the `kawayanmedia/attendance` package (a path repository at
`kawayanmedia/attendance`), with Eloquent and Livewire glue in the application:

| Layer | Location |
| --- | --- |
| Protocol drivers, ADMS controller, parsers, commands | `kawayanmedia/attendance/src` |
| Device registry and audit models | `app/Models/Device.php`, `app/Models/AttendanceDeviceEvent.php` |
| Package contracts implemented by the app | `app/Services/Attendance/Devices` |
| Admin screens | `app/Livewire/Admin/Resources/Device.php`, `AttendanceDeviceEvent.php` |
| Package configuration | `kawayanmedia/attendance/config/attendance-devices.php` |
| Application overrides | `config/attendance-devices.php` |

## Architecture

Every ingestion path ends in the same place, so debounce, duplicate suppression,
lock and closed-period checks, the punch ledger and hour recomputation behave
identically no matter how a punch arrived.

```
               push                                    pull
  ┌──────────────────────────┐          ┌──────────────────────────────┐
  │ ADMS device  ── POST ──┐ │          │ attendance:devices:sync      │
  │ ZKTeco / eSSL / Anviz  │ │          │ attendance:agent (LAN)       │
  └────────────────────────┘ │          └───────────────┬──────────────┘
                             │                          │
  ┌──────────────────────────┴───┐          ┌───────────▼──────────────┐
  │ POST /api/attendance/logs    │          │ Driver::pull()           │
  │ GET|POST /iclock/*           │          │ ZktecoUdp / Hikvision /  │
  └──────────────┬───────────────┘          │ Dahua / Suprema          │
                 │                          └───────────┬──────────────┘
                 │                                      │
                 ▼                                      ▼
        ┌──────────────────────────────────────────────────────┐
        │ PunchSink  →  AttendanceLogIngestor                  │
        │   matches employee, resolves workday, debounces      │
        └──────────────┬───────────────────────────────────────┘
                       ▼
        ┌──────────────────────────────────────────────────────┐
        │ TimesheetLogRecorder                                 │
        │   timesheets.logs + attendance_punches ledger        │
        │   + TimesheetCalculator                              │
        └──────────────────────────────────────────────────────┘
```

Device-level audit rows are written to `attendance_device_events` for pushes,
imports, sync summaries and failures. Keepalive polling (`iclock/getrequest`) is
deliberately not recorded.

## Protocols

| Protocol value | Direction | Typical devices | Notes |
| --- | --- | --- | --- |
| `zkteco_adms` | Push | ZKTeco, eSSL, Anviz and rebadged iClock firmware | Device calls `/iclock/*`; identified by serial number |
| `generic_json` | Push | Middleware, scripts, custom terminals | `POST /api/attendance/logs` with a device or company key |
| `zkteco_udp` | Pull | ZKTeco/eSSL devices on the LAN | UDP 4370 through `rats/zkteco`; requires `ext-sockets` |
| `hikvision_isapi` | Pull | Hikvision access controllers | HTTP Digest, `POST /ISAPI/AccessControl/AcsEvent` |
| `dahua_cgi` | Pull | Dahua access controllers | HTTP Digest, `recordFinder.cgi` |
| `suprema_biostar` | Pull | Suprema BioStar 2 | REST session (`bs-session-id`), paged event collection |
| `csv_export` | File | Any device that can export a log file | CSV/TSV or the ZKTeco DAT layout |

Devices whose firmware cannot reach the internet (private LAN, NAT, CGNAT) use
one of the pull drivers from an on-premise agent. Push devices only need the
application to be reachable on HTTPS.

## Database

The migration `2026_11_27_001100_add_device_protocol_columns.php` adds to
`devices`:

| Column | Purpose |
| --- | --- |
| `serial_number` | ADMS identity; unique per company |
| `protocol` | Driver key from the table above |
| `port`, `timezone` | Connection and timestamp handling |
| `push_key` | Hashed ADMS push key (optional) |
| `credentials` | Encrypted JSON (`username`/`password` or `login_id`/`password`) |
| `config` | Driver options (paths, field maps, direction maps) |
| `office_id` | Optional office link |
| `sync_enabled`, `sync_interval_minutes` | Pull schedule |
| `last_synced_at`, `last_sync_status`, `last_sync_message` | Sync state shown in the admin |
| `last_pull_cursor` | Highest punch read, so the next pull resumes |

`2026_11_27_001200_create_attendance_device_events_table.php` creates the
`attendance_device_events` audit table (`kind`: `push`, `pull`, `import`, `test`,
`error`).

## ADMS / iClock push

Register the device first (admin **Attendance → Devices**, or a seeder) with its
serial number, then point the device at the application:

| Device setting | Value |
| --- | --- |
| Server address | `https://your-host` (port 80/443) |
| Server port | `443` |
| Communication | `ADMS` / `HTTP` |
| Push key (optional) | The value issued by **Rotate Push Key** |

The package registers these routes under `ATTENDANCE_ADMS_PREFIX`
(default `iclock`):

| Method | Path | Purpose | Reply |
| --- | --- | --- | --- |
| `GET`/`POST` | `/iclock/cdata?SN=…&options=all` | Handshake | `GET OPTION FROM: …` block |
| `POST` | `/iclock/cdata?SN=…&table=ATTLOG&Stamp=…` | Punch upload | `OK: <lines>` |
| `GET` | `/iclock/getrequest?SN=…` | Command poll | `OK` |
| `POST` | `/iclock/devicecmd?SN=…` | Command result | `OK` |
| `GET`/`POST` | `/iclock/ping?SN=…` | Keepalive | `OK` |

Example handshake reply:

```
GET OPTION FROM: ADMS-0001
Stamp=9999
OpStamp=0
ErrorDelay=30
Delay=10
TransTimes=00:00;12:00
TransInterval=1
TransFlag=TransData AttLog OpLog
TimeZone=8
Realtime=1
Encrypt=0
ServerTZ=8
```

ATTLOG lines are tab-separated:

```
PIN <TAB> YYYY-MM-DD HH:MM:SS <TAB> status <TAB> verify <TAB> workcode
```

`status` is mapped to the punch direction: `0` in, `1` out, `2` break-out,
`3` break-in, `4` overtime-in, `5` overtime-out. An unknown status leaves the
direction empty so the recorder sequences it.

`OPERLOG`, `OPLOG` and `ATTPHOTO` uploads are acknowledged but not stored as
punches. A resent batch is de-duplicated by the punch ledger.

Authentication:

- The serial number resolves the device and therefore the tenant. Unknown
  serials are rejected with `401`; disabled devices with `403`.
- If the device has a push key, the request must carry `?key=<plain>` or the
  `X-Push-Key` header. Set `ATTENDANCE_ADMS_PUSH_KEY_REQUIRED=true` to refuse
  devices without a key entirely.
- Requests are throttled (default 600 per minute per IP, `ATTENDANCE_ADMS_THROTTLE`).
  Raise it when many devices share one office NAT address.

## Pull sync

```bash
php artisan attendance:devices:sync                    # every due device, every company
php artisan attendance:devices:sync --company=3        # one company
php artisan attendance:devices:sync --device=12 --force
php artisan attendance:devices:sync --dry-run          # read but do not store
php artisan attendance:devices:test 12 --pull          # probe and read one page
```

The scheduler runs the sync every five minutes
(`routes/console.php`). Each device owner controls its own interval with
`sync_interval_minutes`; a device that is not due is reported as `skipped`.

The pull window starts at `last_pull_cursor` minus
`ATTENDANCE_PULL_OVERLAP_MINUTES` (default 10), or seven days back on a fresh
device registration (`ATTENDANCE_PULL_HISTORY_DAYS`). The cursor advances only
after the punches have been stored. Punches outside the window are ignored; the
ledger's unique key removes the overlap duplicates.

A failed device is isolated: the run continues, `last_sync_status` becomes
`failed`, the message is stored on the device and an `error` event is recorded.

## On-premise agent

When the application is hosted and the devices sit on a customer LAN, run the
agent on a small always-on machine inside that network:

```bash
php artisan attendance:agent \
  --server=https://your-host \
  --key=<company API key> \
  --once
```

Or in a service/loop:

```bash
ATTENDANCE_AGENT_SERVER=https://your-host \
ATTENDANCE_AGENT_COMPANY_KEY=<company API key> \
ATTENDANCE_AGENT_INTERVAL=60 \
php artisan attendance:agent
```

The agent:

1. calls `GET /api/attendance/devices` with `X-Company-Key` to fetch the devices
   assigned to that company (pull protocols only, with credentials);
2. pulls each device from the LAN with the same drivers;
3. posts punches to `POST /api/attendance/logs` in batches
   (`ATTENDANCE_AGENT_BATCH_SIZE`, default 500);
4. keeps its own cursor per device in
   `storage/app/attendance-agent-state.json` (override with `--state`).

Because the application stores the device credentials and the agent keeps only
a cursor file, nothing in the office needs database access. Serve the agent's
`--server` over HTTPS: the device list response includes credentials.

`--device=12 --device=13` restricts a pass to specific devices.

## File import

Upload an export from **Attendance → Devices → Import Device Log**. The parser is
chosen from the file itself:

| Parser | Recognises |
| --- | --- |
| `zkteco-dat` | `.dat`/`.txt`/`.log` tab-separated files (`PIN`, timestamp, flags, punch type) |
| `hikvision-csv` | Headers containing `Employee No.` with `Door No.`/`Card No.`/`Major` |
| `suprema-csv` | Headers containing `User ID` with `Device`/`TNA`/`User Name` |
| `generic-csv` | Any CSV/TSV with a recognisable employee and timestamp column, or a headerless `pin, datetime[, direction]` / `pin, date, time[, direction]` layout |

Column matching is alias-based (for example `Employee ID`, `Biometric ID`,
`PIN`, `User ID`, `Enroll Number`; `DateTime`, `Punch Time`, `Event Time`,
`Date` + `Time`; `Type`, `Direction`, `State`, `Status`). Punches go through the
same ingestor as live devices, so a re-upload is reported as duplicates and a
locked or closed day is rejected.

`MachineImport` in the package exposes the same parsers for code that only wants
the parsed records (`process()` / `getTimesheets()`).

## Security

- `api_key` and `push_key` are stored as SHA-256 hashes and hidden from
  serialisation; the plain values are shown once when rotated.
- `credentials` uses the `encrypted:array` cast, so it depends on `APP_KEY`.
  Rotating `APP_KEY` makes stored credentials unreadable; re-enter them.
- ADMS routes are stateless and CSRF-free (they are not in the `web` group) and
  are authenticated by serial number plus optional push key.
- Use HTTPS. The agent device-list endpoint returns credentials, so plain HTTP
  would expose them.
- Tenant isolation is enforced twice: the device serial resolves the tenant for
  pushes, and the API key middleware resolves it for the JSON and agent
  endpoints.

## Extending: adding a driver

1. Implement `KawayanMedia\Attendance\Contracts\Devices\Driver`.
2. Return `PunchRecord` objects from `pull()`; leave `direction` null when the
   vendor does not report in/out.
3. Register it in `config/attendance-devices.php`:

```php
'drivers' => [
    // ...
    'acme_terminal' => App\Attendance\Drivers\AcmeTerminalDriver::class,
],
```

4. Add the value to `App\Enums\DeviceProtocol` (the enum is the admin-facing
   list) and a factory state if you want one.
5. Unit-test parsing with recorded payloads; use `Http::fake()` for HTTP
   vendors. `tests/Unit/Attendance/DeviceProtocolTest.php` asserts every enum
   value has a registered driver.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Device gets `401` on `/iclock/cdata` | Serial number registered exactly as the device reports it; device active; push key matches |
| Device gets `403` | Device is disabled in the registry |
| Device shows `last_seen_at` but no punches | `table` is `ATTLOG`; device time zone; employee `biometric_id` matches the device PIN |
| `attendance:devices:sync` reports unreachable | Host/IP and port; agent or application must be on the same LAN/VPN |
| Sync says `skipped` | `sync_enabled`, `is_active`, and the per-device interval |
| Sync says failed with a TLS error | `config.verify_tls` is `true`; install a trusted certificate or set `verify_tls: false` for a lab device |
| Hikvision/Dahua says credentials invalid | Digest credentials; the device user needs remote access rights |
| Suprema login succeeds but events fail | Health: `events_path`, `rows_path`, `pin_path`, `time_path` overrides |
| Import says the file was not recognised | Export CSV/DAT; include an employee column and a date/time column |
| `Only the first N events were read` | Raise `ATTENDANCE_PULL_MAX_RECORDS`; the next run continues from the cursor |

Inspect **Attendance → Device Events** for the stored errors, or run
`php artisan attendance:devices:prune --days=30` manually to trim old rows.

## Tests

```bash
php artisan test --compact tests/Unit/Attendance tests/Feature/Attendance
```

Coverage includes the ADMS handshake and upload, tenant routing by serial,
push-key enforcement, duplicate resends, the sync command with a fake driver,
cursor and interval behaviour, the agent device list API, the DAT/CSV parsers,
and the Hikvision/Dahua/Suprema drivers against recorded responses.
