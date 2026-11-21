<?php

namespace App\Jurisdictions\Philippines\Http\Requests;

/**
 * Validates the take-home pay calculator inputs.
 *
 * The sector is validated by the shared compensation request: Private uses
 * SSS and Government uses GSIS.
 */
class CalculateTakeHomePayRequest extends CalculateCompensationRequest {}
