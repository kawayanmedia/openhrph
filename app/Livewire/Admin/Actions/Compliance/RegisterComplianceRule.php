<?php

namespace App\Livewire\Admin\Actions\Compliance;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\ComplianceRule;
use App\Models\ComplianceSource;
use App\Services\Compliance\ComplianceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RegisterComplianceRule extends Action
{
    public string $name = 'Register Rule';

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Source')
                ->options(ComplianceSource::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->rules('required'),
            Text::make('Code')->rules('required', 'max:96'),
            Text::make('Name')->rules('required', 'max:191'),
            Select::make('Sector')
                ->options(['Private' => 'Private', 'Government' => 'Government'])
                ->nullable(),
            Date::make('Effective From', 'effective_from')->rules('required'),
            Date::make('Effective Until', 'effective_until'),
            Text::make('Version')->rules('required', 'max:64')->default('1.0.0'),
            Textarea::make('Configuration (JSON)', 'configuration')
                ->rules('required')
                ->help('Inert JSON only; formulas are not executed.'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $source = ComplianceSource::query()->findOrFail($fields['source']);
        $configuration = json_decode((string) $fields['configuration'], true);

        if (! is_array($configuration)) {
            throw ValidationException::withMessages([
                'configuration' => 'Configuration must be valid JSON.',
            ]);
        }

        app(ComplianceRegistry::class)->registerRule($source, [
            'code' => $fields['code'],
            'name' => $fields['name'],
            'sector' => $fields['sector'] ?? null,
            'effective_from' => $fields['effective_from'],
            'effective_until' => $fields['effective_until'] ?? null,
            'version' => $fields['version'] ?? '1.0.0',
            'status' => ComplianceRule::STATUS_DRAFT,
        ], $configuration, $request->user());

        return self::message('Compliance rule registered.');
    }
}
