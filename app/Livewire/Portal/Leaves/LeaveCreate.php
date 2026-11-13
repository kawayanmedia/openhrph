<?php

namespace App\Livewire\Portal\Leaves;

use App\Actions\CreateLeaveAction;
use App\Models\Leave;
use App\Support\Tenancy\TenantCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

class LeaveCreate extends Component
{
    use WithFileUploads;

    public $title = 'Create Leave';

    public $leaveTypeId = 1;

    public $dateFrom;

    public $dateTo;

    public $daysWithPay;

    public $daysWithoutPay;

    public $reason;

    public $isWholeDay = 'true';

    public $leaveOptions = [];

    public $attachments;

    public $employee;

    // return Grid::make()
    // ->schema([
    //     Forms\Components\TextInput::make('First Name')->required(),
    //     Forms\Components\TextInput::make('Last Name')->required(),
    // ]);

    protected function getFormSchema(): array
    {
        // return Grid::make()
        // ->schema([
        //     Forms\Components\TextInput::make('First Name')->required(),
        //     Forms\Components\TextInput::make('Last Name')->required(),
        // ]);

        return [
           

            // Forms\Components\MarkdownEditor::make('content'),
            // ...
        ];
    }

    public function mount(Request $request)
    {
        $this->leaveOptions = TenantCache::rememberForever('leave_types', function () {
            return Leave::active()->pluck('name', 'id')->toArray();
        });

        // Select only vacation and sick leave for now
        $selectedLeaveOptions = [];

        foreach ($this->leaveOptions as $key => $leaveOption) {
            if ($leaveOption == 'Vacation Leave' || $leaveOption == 'Sick Leave') {
                $selectedLeaveOptions[$key] = $leaveOption;
            }
        }

        $this->leaveOptions = $selectedLeaveOptions;

        $this->employee = Auth::user()->employee;
    }

    public function updatedDateFrom($value)
    {
        $this->computeDays();
    }

    public function updatedDateTo($value)
    {
        $this->computeDays();
    }

    public function computeDays()
    {
        if ($this->dateFrom != null && $this->dateTo != null) {

            $totalLeave = Carbon::createFromFormat('Y-m-d', $this->dateFrom)->diffInDays(Carbon::createFromFormat('Y-m-d', $this->dateTo)) + 1;
            // Check if leave is already 0
            if ($this->leaveTypeId == 1 && $this->employee->total_vacation_leave <= 0) {
                $this->daysWithoutPay = $totalLeave;
            } else {
                $this->daysWithPay = $totalLeave;
            }
        }
    }

    public function applyLeave(Request $request)
    {
        if ($this->attachments != null) {
            $this->attachments = $this->attachments->store(path: 'attachments');
        }

        $this->validate([
            'dateFrom' => 'required',
            'dateTo' => 'required',
            // 'reason' => 'required',
        ]);

        $result = CreateLeaveAction::run($request, $this);

        if ($result === true) {
            return redirect()->route('portal.leaves');
        }

        // $createLeaveAction = new CreateLeaveAction();
        // $createLeaveAction->execute();
    }

    public function render()
    {
        return view('livewire.portal.leaves.create')
            ->title($this->title)->layout('layouts.portal');
    }
}
