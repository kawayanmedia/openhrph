<?php

namespace App\Enums;

/**
 * How a registered time clock talks to the application.
 *
 * Push protocols open a connection to the app (ADMS devices call `/iclock/*`);
 * pull protocols are opened by the sync command or the on-prem agent. A device
 * using `GenericJson` posts to the public attendance API.
 */
enum DeviceProtocol: string
{
    case GenericJson = 'generic_json';

    case ZktecoAdms = 'zkteco_adms';

    case ZktecoUdp = 'zkteco_udp';

    case HikvisionIsapi = 'hikvision_isapi';

    case DahuaCgi = 'dahua_cgi';

    case SupremaBiostar = 'suprema_biostar';

    case CsvExport = 'csv_export';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $protocol): array => [$protocol->value => $protocol->label()])
            ->all();
    }

    public static function tryFromValue(?string $value): ?self
    {
        $value = str_replace(['-', ' '], '_', strtolower(trim((string) $value)));

        return $value === '' ? null : self::tryFrom($value);
    }

    public function label(): string
    {
        return match ($this) {
            self::GenericJson => 'Generic JSON push API',
            self::ZktecoAdms => 'ZKTeco / eSSL ADMS push (iClock)',
            self::ZktecoUdp => 'ZKTeco UDP pull (port 4370)',
            self::HikvisionIsapi => 'Hikvision ISAPI pull',
            self::DahuaCgi => 'Dahua CGI pull',
            self::SupremaBiostar => 'Suprema BioStar 2 API pull',
            self::CsvExport => 'Export file upload (CSV / DAT)',
        };
    }

    /**
     * Whether the application can pull logs from the device over its own API.
     */
    public function isPullable(): bool
    {
        return match ($this) {
            self::ZktecoUdp, self::HikvisionIsapi, self::DahuaCgi, self::SupremaBiostar => true,
            default => false,
        };
    }

    /**
     * Whether the device connects to the application instead.
     */
    public function isPush(): bool
    {
        return match ($this) {
            self::GenericJson, self::ZktecoAdms => true,
            default => false,
        };
    }

    /**
     * Whether the device is normally reachable only from its own LAN.
     */
    public function requiresLocalNetwork(): bool
    {
        return $this->isPullable();
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::ZktecoUdp => 4370,
            self::HikvisionIsapi, self::DahuaCgi, self::SupremaBiostar => 80,
            default => null,
        };
    }
}
