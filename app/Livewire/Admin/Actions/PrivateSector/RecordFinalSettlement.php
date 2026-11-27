<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Services\Separation\SeparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RecordFinalSettlement extends Action
{
    public string $name = 'Record Final Settlement';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Calculation Reference', 'calculation_reference')->rules('nullable', 'max:128'),
            Textarea::make('Earnings (JSON)', 'earnings')
                ->rules('required')
                ->help('[{"label":"Final pay","amount":10000}]'),
            Textarea::make('Deductions (JSON)', 'deductions')
                ->help('[{"label":"Loans","amount":1000}]'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $earnings = json_decode((string) ($fields['earnings'] ?? '[]'), true);
        $deductions = json_decode((string) ($fields['deductions'] ?? '[]'), true);

        if (! is_array($earnings) || ! is_array($deductions)) {
            throw ValidationException::withMessages([
                'earnings' => 'Earnings and deductions must be JSON arrays.',
            ]);
        }

        $service = app(SeparationService::class);

        foreach ($models as $model) {
            $service->recordSettlement($model, $earnings, $deductions, $request->user(), array_filter([
                'calculation_reference' => $fields['calculation_reference'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        return self::message('Final settlement recorded.');
    }
}
