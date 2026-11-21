<?php

namespace App\Jurisdictions\Philippines\Http\Requests;

use App\Enums\Sector;
use Illuminate\Validation\Rule;

/**
 * Validates the take-home pay calculator inputs. The form mirrors the
 * withholding tax calculator and adds the employment sector, which decides
 * whether SSS (private) or GSIS (government) applies.
 */
class CalculateTakeHomePayRequest extends CalculateCompensationRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $value = $this->input('sector');

        if (! is_string($value)) {
            return;
        }

        $sector = Sector::tryFromValue($value);

        if ($sector !== null) {
            $this->merge(['sector' => $sector->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'sector' => ['nullable', Rule::enum(Sector::class)],
        ]);
    }
}
