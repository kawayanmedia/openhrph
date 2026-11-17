<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngestAttendanceLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device' => ['nullable', 'string', 'max:128'],
            'logs' => ['required', 'array', 'min:1', 'max:500'],
            'logs.*' => ['array'],
            'logs.*.biometric_id' => ['nullable', 'string', 'max:64'],
            'logs.*.employee_number' => ['nullable', 'string', 'max:64'],
            'logs.*.at' => ['nullable', 'date'],
            'logs.*.timestamp' => ['nullable', 'date'],
            'logs.*.date' => ['nullable', 'date_format:Y-m-d'],
            'logs.*.log_date' => ['nullable', 'date_format:Y-m-d'],
            'logs.*.time' => ['nullable', 'date_format:H:i:s,H:i'],
            'logs.*.type' => ['nullable'],
            'logs.*.log_type' => ['nullable'],
        ];
    }

    /**
     * Every log needs a way to identify the employee and a timestamp.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                foreach ((array) $this->input('logs', []) as $index => $log) {
                    $log = (array) $log;

                    $hasEmployee = filled($log['biometric_id'] ?? null) || filled($log['employee_number'] ?? null);

                    if (! $hasEmployee) {
                        $validator->errors()->add(
                            "logs.{$index}",
                            'Each log requires a biometric_id or an employee_number.'
                        );
                    }

                    $hasMoment = filled($log['at'] ?? null)
                        || filled($log['timestamp'] ?? null)
                        || (filled($log['date'] ?? $log['log_date'] ?? null) && filled($log['time'] ?? null));

                    if (! $hasMoment) {
                        $validator->errors()->add(
                            "logs.{$index}",
                            'Each log requires an at timestamp (or date and time).'
                        );
                    }
                }
            },
        ];
    }
}
