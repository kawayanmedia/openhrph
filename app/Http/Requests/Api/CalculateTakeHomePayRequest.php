<?php

namespace App\Http\Requests\Api;

/**
 * Validates the take-home pay calculator inputs. The form mirrors the
 * withholding tax calculator so both tools accept the same compensation lines.
 */
class CalculateTakeHomePayRequest extends CalculateCompensationRequest {}
