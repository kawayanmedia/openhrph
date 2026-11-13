<?php

namespace App\Livewire\Portal\OfficialBusiness;

use App\Models\OfficialBusinessRequest;
use Livewire\Component;
use Livewire\WithFileUploads;

class OfficialBusinessCreate extends Component
{
    use WithFileUploads;

    public $title = 'Schedule Adjustment';

    public $dateFrom;

    public $dateTo;

    public $timeFrom;

    public $timeTo;

    public $location;

    public $purpose;

    public $attachments;

    public function store()
    {
        if ($this->attachments != null) {
            $attachments = $this->attachments->store(path: 'attachments');
        }

        if (! isset($attachments)) {
            $attachments = null;
        }

        $this->validate([
            'dateFrom' => 'required',
            'dateTo' => 'required',
            'timeFrom' => 'required',
            'timeTo' => 'required',
            'location' => 'required',
            'purpose' => 'required',
        ]);

        $overtime = new OfficialBusinessRequest;
        $overtime->employee_id = request()->user()?->employee?->id;
        $overtime->date_filed = now()->format('Y-m-d');
        $overtime->date_from = $this->dateFrom;
        $overtime->date_to = $this->dateTo;
        $overtime->time_from = $this->timeFrom;
        $overtime->time_to = $this->timeTo;
        $overtime->location = $this->location;
        $overtime->purpose = $this->purpose;
        $overtime->attachments = $attachments;
        $overtime->save();

        return redirect()->route('portal.officialbusiness');
    }

    public function render()
    {
        return view('livewire.portal.officialbusiness.create')
            ->title($this->title)->layout('layouts.portal');
    }
}
