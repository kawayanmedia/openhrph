<?php

namespace App\Livewire\Portal\Leaves;

use App\Actions\CreateLeaveAction;
use App\Models\Applicant;
use App\Models\JobVacancy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;

class ApplicantCreate extends Component
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

    public $training_file;

    public $experience_file;

    public $employee;

    public $name;

    public $gender = 'male';

    public $address;
    public $birth_date;

    public $educational_attainment;

    public $telephone;

    public $email;
    public $eligibility;

    public $training;
    public $experience;

    public $jobVacancy;

    public function mount(Request $request, $id)
    {
        $this->jobVacancy = JobVacancy::find($id);
        // $this->leaveOptions = Cache::rememberForever($request->user()?->company_id . '_leave_types', function () {
        //     return Leave::active()->pluck('name', 'id')->toArray();
        // });

        // Select only vacation and sick leave for now
        // $selectedLeaveOptions = [];

        // foreach ($this->leaveOptions as $key => $leaveOption) {
        //     if ($leaveOption == 'Vacation Leave' || $leaveOption == 'Sick Leave') {
        //         $selectedLeaveOptions[$key] = $leaveOption;
        //     }
        // }

        // $this->leaveOptions = $selectedLeaveOptions;

        // $this->employee = Auth::user()->employee;
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
        $trainingFile = null;
        if ($this->training_file != null) {
            $trainingFile = $this->training_file->storePublicly('job-applications', 's3');
        }

        $experienceFile = null;

        if ($this->experience_file != null) {
            $experienceFile = $this->experience_file->storePublicly('job-applications', 's3');
        }

        // training_file
        // experience_file

        $this->validate([
            'name' => 'required',
            'address' => 'required',
            'birth_date' => 'required',
            'educational_attainment' => 'required',
            'telephone' => 'required',
            'email' => 'required|email',
            'eligibility' => 'required',
        ]);

        $applicant = new Applicant;
        $applicant->name = $this->name;
        $applicant->gender = $this->gender;
        $applicant->address = $this->address;
        $applicant->birth_date = $this->birth_date;
        $applicant->educational_attainment = $this->educational_attainment;
        $applicant->job_vacancy_id = $this->jobVacancy->id;
        // $applicant->position_applying = $this->educational_attainment;
        $applicant->name = $this->telephone;
        $applicant->telephone = $this->name;
        $applicant->email = $this->email;
        $applicant->eligibility = $this->eligibility;
        $applicant->address = $this->address;
        $applicant->training = $this->training;
        $applicant->experience = $this->experience;
        $applicant->training_file = $trainingFile;
        $applicant->experience_file = $experienceFile;
        $applicant->save();

        // Redirect to success page
        return redirect()->to(route('portal.jobs.success'));
    }

    public function render()
    {
        return view('livewire.portal.applicants.create')
            ->title($this->title)->layout('layouts.job');
    }
}
