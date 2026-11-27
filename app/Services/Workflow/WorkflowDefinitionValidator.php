<?php

namespace App\Services\Workflow;

use Illuminate\Validation\ValidationException;

/**
 * Validates the JSON step configuration of a workflow definition.
 *
 * No user-supplied PHP or expressions are evaluated anywhere; the engine only
 * understands the declarative assignee types below.
 */
class WorkflowDefinitionValidator
{
    public const ASSIGNEE_TYPES = ['user', 'role', 'group', 'unit', 'employee'];

    /**
     * @param  array<string, mixed>|null  $configuration
     * @return array<int, array<string, mixed>>
     */
    public function normalize(?array $configuration, string $attribute = 'configuration'): array
    {
        $steps = $configuration['steps'] ?? null;

        if (! is_array($steps) || $steps === []) {
            throw ValidationException::withMessages([
                $attribute => 'A workflow needs at least one approval step.',
            ]);
        }

        $normalized = [];
        $seenKeys = [];

        foreach (array_values($steps) as $index => $step) {
            if (! is_array($step)) {
                throw ValidationException::withMessages([
                    $attribute => 'Each workflow step must be an object.',
                ]);
            }

            $key = (string) ($step['key'] ?? 'step_'.($index + 1));

            if (! preg_match('/^[a-z0-9_\-]+$/', $key)) {
                throw ValidationException::withMessages([
                    $attribute => "Step key '{$key}' may only contain letters, numbers, hyphens and underscores.",
                ]);
            }

            if (isset($seenKeys[$key])) {
                throw ValidationException::withMessages([
                    $attribute => "Step key '{$key}' is duplicated.",
                ]);
            }

            $seenKeys[$key] = true;

            $mode = (string) ($step['mode'] ?? 'all');

            if (! in_array($mode, ['all', 'any'], true)) {
                throw ValidationException::withMessages([
                    $attribute => "Step '{$key}' must use mode 'all' or 'any'.",
                ]);
            }

            $assignees = $step['assignees'] ?? null;

            if (! is_array($assignees) || $assignees === []) {
                throw ValidationException::withMessages([
                    $attribute => "Step '{$key}' needs at least one assignee.",
                ]);
            }

            $normalizedAssignees = [];

            foreach ($assignees as $assignee) {
                $type = is_array($assignee) ? (string) ($assignee['type'] ?? '') : '';

                if (! in_array($type, self::ASSIGNEE_TYPES, true)) {
                    throw ValidationException::withMessages([
                        $attribute => "Step '{$key}' has an unsupported assignee type.",
                    ]);
                }

                $value = $assignee['value'] ?? $assignee['id'] ?? null;

                if ($value === null || $value === '') {
                    throw ValidationException::withMessages([
                        $attribute => "Step '{$key}' has an assignee without a value.",
                    ]);
                }

                $normalizedAssignees[] = [
                    'type' => $type,
                    'value' => $type === 'user' || $type === 'unit' || $type === 'employee'
                        ? (int) $value
                        : (string) $value,
                    'label' => $assignee['label'] ?? null,
                ];
            }

            $dueDays = $step['due_days'] ?? null;

            if ($dueDays !== null && (! is_numeric($dueDays) || (int) $dueDays < 0)) {
                throw ValidationException::withMessages([
                    $attribute => "Step '{$key}' has an invalid due_days value.",
                ]);
            }

            $normalized[] = [
                'key' => $key,
                'name' => (string) ($step['name'] ?? ucfirst(str_replace(['_', '-'], ' ', $key))),
                'mode' => $mode,
                'order' => $index + 1,
                'due_days' => $dueDays === null ? null : (int) $dueDays,
                'assignees' => $normalizedAssignees,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    public function assertValid(?array $configuration): void
    {
        $this->normalize($configuration);
    }
}
