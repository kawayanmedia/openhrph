<?php

namespace App\Livewire\Admin\Imports;

use App\Imports\OpenHrWorkbook;
use App\Imports\Workbook\Exceptions\ImportFormatException;
use App\Imports\Workbook\Exceptions\ImportValidationException;
use App\Imports\Workbook\ImportProblem;
use App\Imports\Workbook\WorkbookImporter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImport extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $problems = [];

    /**
     * @var array<string, array{created: int, updated: int}>
     */
    public array $summary = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->can(config('admin.gate')), 403);
    }

    public function updatedFile(): void
    {
        $this->resetResult();
        $this->validateOnly('file', $this->rules());
    }

    public function import(WorkbookImporter $importer): void
    {
        $this->resetResult();
        $this->validate($this->rules());

        $path = $this->file?->getRealPath();

        if (! is_string($path) || $path === '') {
            $this->addError('file', 'The upload could not be read. Please choose the file again.');

            return;
        }

        try {
            $report = $importer->import($path, (string) $this->file->getClientOriginalExtension());

            $this->summary = $report->toArray();
            $this->file = null;

            session()->flash('admin.action', sprintf(
                'Import completed: %d record%s created and %d updated.',
                $report->created(),
                $report->created() === 1 ? '' : 's',
                $report->updated(),
            ));
        } catch (ImportValidationException $exception) {
            $this->problems = $exception->toArray();
        } catch (ImportFormatException $exception) {
            $this->problems = [ImportProblem::workbook($exception->getMessage())->toArray()];
        }
    }

    public function downloadProblems(): StreamedResponse
    {
        $problems = $this->problems;

        return response()->streamDownload(function () use ($problems): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Sheet', 'Row', 'Column', 'Heading', 'Problem']);

            foreach ($problems as $problem) {
                fputcsv($handle, [
                    $problem['sheet'] ?? '',
                    $problem['row'] ?? '',
                    $problem['column'] ?? '',
                    $problem['heading'] ?? '',
                    $problem['message'] ?? '',
                ]);
            }

            fclose($handle);
        }, 'import-problems-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render()
    {
        $workbook = app(OpenHrWorkbook::class);
        $sheets = $workbook->sheets();

        $groups = [];

        foreach ($this->problems as $problem) {
            $groups[$problem['sheet'] ?? 'Workbook'][] = $problem;
        }

        $ordered = [];

        foreach ($sheets as $sheet) {
            if (isset($groups[$sheet->title()])) {
                $ordered[$sheet->title()] = $groups[$sheet->title()];
                unset($groups[$sheet->title()]);
            }
        }

        foreach ($groups as $name => $problems) {
            $ordered[$name] = $problems;
        }

        $limit = (int) config('imports.display_problems', 200);
        $displayed = 0;
        $visible = [];

        foreach ($ordered as $name => $problems) {
            if ($displayed >= $limit) {
                break;
            }

            $slice = array_slice($problems, 0, $limit - $displayed);

            $visible[$name] = $slice;
            $displayed += count($slice);
        }

        return view('livewire.admin.imports', [
            'sheets' => $sheets,
            'groupedProblems' => $visible,
            'problemCount' => count($this->problems),
            'displayedProblems' => $displayed,
        ])->layout('layouts.admin')->title('Data Import');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:'.(int) config('imports.max_upload_kb', 10240),
            ],
        ];
    }

    private function resetResult(): void
    {
        $this->problems = [];
        $this->summary = [];
        $this->resetErrorBag('file');
    }
}
