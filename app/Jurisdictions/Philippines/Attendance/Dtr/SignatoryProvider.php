<?php

namespace App\Jurisdictions\Philippines\Attendance\Dtr;

use App\Models\Office;
use App\Models\Signatory as SignatoryModel;
use KawayanMedia\DtrPdf\Contract\SignatoryProviderInterface;
use KawayanMedia\DtrPdf\Model\Employee as DtrEmployee;
use KawayanMedia\DtrPdf\Model\Signatory;

/**
 * Office head printed under the DTR, falling back to the company signatory.
 */
final class SignatoryProvider implements SignatoryProviderInterface
{
    public function signatoryFor(DtrEmployee $employee): ?Signatory
    {
        $office = $employee->officeId === null
            ? null
            : Office::query()->find($employee->officeId);

        if ($office !== null && trim((string) $office->head) !== '') {
            return new Signatory((string) $office->head, (string) $office->position);
        }

        $signatory = SignatoryModel::query()->orderBy('id')->first();

        if ($signatory === null) {
            return null;
        }

        return new Signatory((string) $signatory->name, (string) $signatory->position);
    }
}
