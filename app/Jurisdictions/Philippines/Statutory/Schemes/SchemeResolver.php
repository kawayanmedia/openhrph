<?php

namespace App\Jurisdictions\Philippines\Statutory\Schemes;

use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\Sss\SssBracket;

/**
 * Builds the social-insurance scheme for an agency code.
 */
final class SchemeResolver
{
    /**
     * @param  list<SssBracket>  $sssBrackets
     */
    public static function make(string $agency, array $sssBrackets, GsisSchedule $gsis): SocialInsuranceScheme
    {
        return match (strtolower(trim($agency))) {
            'gsis' => new GsisScheme($gsis),
            default => new SssScheme($sssBrackets),
        };
    }
}
