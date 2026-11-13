<?php

namespace App\Http\Controllers;

use App\Gateways\GenerateTimesheetGateway;
use App\Models\Employee;
use App\Models\Leave;
use App\Services\Attendance\AttendanceCompute;
use App\Services\ConcatenatePdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use setasign\Fpdi\Fpdi;

class TimesheetController extends Controller
{
    public $array_pdf = [];

    public $employee_array = [];

    public $employee_id = '';

    public $month = '';

    public $year = '';

    public $period_from = '';

    public $period_to = '';

    public $dont_compute_dates = '';

    public $sat_or_sun = '';

    public $is_holiday = false;

    public $holiday = null;

    public $late_final = 0;    // Late counter (minutes or hours late)

    public $late_count = 0;    // Late count (number of lates

    public $undertime_final = 0;    // Undertime

    public $undertime_count = 0;    // Undertime count (number of undertime)

    public $total_tardiness = 0;

    public $line_number = 1;    // Where the writing in pdf should.

    public $number_of_days = 0;    // Number of days work

    public $final_over_time = 0;    // Number of overtime

    public $overtime = 0;    // Overtime

    public $number_of_hours_work = 0;    // Number of hours worked

    public $shift_type = 1;

    public $am_login = '';

    public $am_logout = '';

    public $pm_login = '';

    public $pm_logout = '';

    public $ot_login = '';

    public $ot_logout = '';

    public $leave_type_id = '';

    public $log_date = '';

    public $manual_log_id = '';

    public $time_a = '08:00';

    public $time_b = '12:00';

    public $time_c = '13:00';

    public $time_d = '17:00';

    public $allow_40hrs = false;    // Allow 40 hrs per week

    public $week1_hours = 0;        // Day 1 - 8

    public $week2_hours = 0;        // Day 9 - 15

    public $week3_hours = 0;        // Day 16 - 22

    public $week4_hours = 0;        // Day 23 - 31

    public $week1_days = ['01', '02', '03', '04', '05', '06', '07'];

    public $week2_days = ['08', '09', '10', '11', '12', '13', '14', '15'];

    public $week3_days = ['16', '17', '18', '19', '20', '21', '22'];

    public $week4_days = ['23', '24', '25', '26', '27', '28', '28', '30', '31'];

    public $week1_total_tardiness = 0;        // Total of hours tardy

    public $week2_total_tardiness = 0;

    public $week3_total_tardiness = 0;

    public $week4_total_tardiness = 0;

    public $week1_late_count = 0;        // Total of late count

    public $week2_late_count = 0;

    public $week3_late_count = 0;

    public $week4_late_count = 0;

    public $week1_late_final = 0;        // Total of weekly late

    public $week2_late_final = 0;

    public $week3_late_final = 0;

    public $week4_late_final = 0;

    public $week1_undertime_count = 0;        // Total of undertime count

    public $week2_undertime_count = 0;

    public $week3_undertime_count = 0;

    public $week4_undertime_count = 0;

    public $week1_undertime_final = 0;        // Total of weekly undertime

    public $week2_undertime_final = 0;

    public $week3_undertime_final = 0;

    public $week4_undertime_final = 0;

    public $start_date = 1;

    public $pdf = '';

    public $friday_exempted = 'no';

    public $attendanceCompute;

    public function __construct()
    {
        $this->pdf = new Fpdi();

        $this->attendanceCompute = new AttendanceCompute;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        // dd(session('tables.ListTimesheets_filters'));
        // Get the data that need to process

        $show_employee_number_dtr = get_setting('show_employee_number_dtr');

        $this->employee_array[] = request('employee_id');

        $this->employee_array[] = request('hidden_employee_id');

        // Dont compute dates
        $this->dont_compute_dates = get_setting('dont_compute');

        $this->month = request('month');
        $this->year = request('year');
        $this->period_from = request('period_from');
        $this->period_to = request('period_to');

        // If the $this->period_from is not 1
        if ($this->period_from != '1') {
            $this->start_date = request('period_from'); // Start of viewing DTR

            $this->period_from = '1'; // We will still use the first day
        }

        // If the Multiple employee checkbox is checked
        if (request('multi_employee')) {
            // Array of employee from checkboxes
            $employees = request('employee');

            if (is_array($employees)) {
                $this->employee_array = $employees;
            }
        }

        // If the request is from view attendance page===>>>>
        // $this->from_view_attendance();

        // Remove empty array
        $this->employee_array = array_filter($this->employee_array);

        $fromDate = session('tables.ListTimesheets_filters.log_date.from_date');
        $toDate = session('tables.ListTimesheets_filters.log_date.to_date');

        $employeeId = session('tables.ListTimesheets_filters.employee_id.value');

        $officeId = session('tables.ListTimesheets_filters.office_id.value');

        // dd(session('tables.ListTimesheets_filters.log_date'));
        // dd(session('tables.ListTimesheets_filters.log_date.from_date'));
        // dd(session('tables.ListTimesheets_filters.log_date.to_date'));

        // dd(session('tables.ListTimesheets_filters.employee_id.value'));

        // dd(session('tables.ListTimesheets_filters.office_id.value'));

        $employees = Employee::with(['timesheets' => function (Builder $query) use ($fromDate, $toDate) {
            $query->whereBetween('log_date', [$fromDate, $toDate]);
            $query->orderBy('log_date');
        }])
            ->where('status', 1)
            ->byOfficeId($officeId)
            // ->byId($employeeId)
            ->get();

        // dd($employees);
        // 1926
        foreach ($employees as $employee) {

            $this->employee_id = $employee->id;

            // This is to add any blank log in timesheets
            // could be very expensive to process
            // TODO
            $days = CarbonPeriod::create($fromDate, '1 day', $toDate);
            $gateway = new GenerateTimesheetGateway;
            $gateway->addDays($employee, $days);

            $this->friday_exempted = $employee->friday_exempted;

            // Check what type of user is logged
            // If leave manager

            if (Auth::user()->user_type == 5) {
                // If the office is not equal to office id of user logged
                // if (Session::get('office_id') != $employee->office_id']) {
                //     echo '<font color="red">You are not allowed to view this records!</font>';
                //     exit;
                // }
            }

            $wholeName = $employee->first_name . ' ' . $employee->mname . ' ' . $employee->last_name;

            if ($employee->extension != '') {
                $wholeName .= ', ' . $employee->extension;
            }

            // If the LGU wants to see the last name in DTR first
            if (get_setting('last_name_first_dtr') == 'yes') {
                $wholeName = $employee->last_name . ', ' . $employee->first_name . ' ' . $employee->mname;

                if ($employee->extension != '') {
                    $wholeName = $employee->last_name . ', ' . $employee->first_name . ' ' . $employee->extension . ' ' . $employee->mname;
                }
            }

            $this->shift_type = $employee->shift_type;

            // initiate FPDI
            $this->pdf = new FPDI();
            // add a page
            $this->pdf->AddPage();
            // set the sourcefile
            $this->pdf->setSourceFile('templates/dtr.pdf');

            // select the first page
            $tplIdx = $this->pdf->importPage(1);

            // use the page we imported
            $this->pdf->useTemplate($tplIdx);

            // set font, font style, font size.
            $this->pdf->SetFont('Times', 'B', 11);

            //###################################
            //THIS IS FOR THE NAME OF EMPLOYEE  #
            //###################################

            if ($show_employee_number_dtr == 'yes') {
                $this->pdf->SetXY(80, 10);

                $this->pdf->Write(0, $this->employee_id);

                $this->pdf->SetX(175);

                $this->pdf->Write(0, $this->employee_id);
            }

            // set initial placement
            $this->pdf->SetXY(30, 22.5);

            // line break
            $this->pdf->Ln(5);

            // go to 25 X (indent)
            $this->pdf->SetX(40);

            // write
            $this->pdf->Write(0, ucwords(strtolower(utf8_decode($wholeName))));

            // go to 25 X (indent)
            $this->pdf->SetX(130);

            // write
            $this->pdf->Write(0, ucwords(strtolower(utf8_decode($wholeName))));

            //#######################################
            //THIS IS FOR THE NAME OF EMPLOYEE (END)#
            //#######################################

            //###################################
            //THIS IS FOR THE MONTH             #
            //###################################

            $monthName = Carbon::createFromFormat('Y-m-d', $fromDate)->format('F');
            $startDate = Carbon::createFromFormat('Y-m-d', $fromDate);
            $endDate = Carbon::createFromFormat('Y-m-d', $toDate);

            // line break
            $this->pdf->Ln(11);

            // go to 25 X (indent)
            $this->pdf->SetX(43);

            // write
            $this->pdf->Write(0, ucwords(strtolower($monthName . ' ' . $startDate->format('d') . '-' . $endDate->format('d') . ', ' . $startDate->format('Y'))));

            // go to 25 X (indent)
            $this->pdf->SetX(137);

            // write
            $this->pdf->Write(0, ucwords(strtolower($monthName . ' ' . $startDate->format('d') . '-' . $endDate->format('d') . ', ' . $startDate->format('Y'))));

            //###################################
            //THIS IS FOR THE MONTH (END)       #
            //###################################

            $this->pdf->Ln(28.5);
            $this->pdf->SetFont('Times', '', 9);

            // This will start the writing process to pdf

            // ========= START ====================================
            foreach ($employee->timesheets as $row) {
                $this->set_dtr($row);

                // Split the logged_date
                // As of PHP 5.3.0 the regex extension is deprecated,
                // calling this function will issue an E_DEPRECATED notice.
                [$log_year, $log_month, $log_day] = explode('-', $row->log_date);

                // Check if the day is Sat or Sun
                $this->sat_or_sun = is_sat_sun($row->log_date);

                // Check if the day is holiday
                $this->is_holiday = is_holiday($row->log_date->format('Y-m-d'));

                // if holiday then
                if ($this->is_holiday) {
                    $this->holiday = find_holiday($row->log_date->format('Y-m-d'));
                }

                $allow_forty_hours = get_setting('allow_forty_hours');

                // If regular hours (8-5)
                // We can also use shift_type 3 here because it has
                // 4 logs
                if ($this->shift_type == 1 || $this->shift_type == 3) {
                    // $this->regular_hours(); // TODO

                    $this->allow_40hrs == false;
                }

                // If shifting
                if ($this->shift_type == 2) {
                    if ($allow_forty_hours == 1) {
                        // Allow 40 hrs per week
                        $this->allow_40hrs = true;
                    }

                    // $this->shifting_same_day(); // TODO
                }

                // If 24 hrs
                //if ($this->shift_type == 3)
                if ($this->shift_type == 4) {
                    if ($allow_forty_hours == 1) {
                        // Allow 40 hrs per week
                        $this->allow_40hrs = true;
                    }

                    // $this->shifting_same_day(); // added 7.27.2011 5:47pm // TODO
                }
                //echo $this->log_date.' -- '.$this->attendanceCompute->hours_late.'<br>';

                // Reset Late and undertime
                $this->attendanceCompute->hours_late = 0;
                $this->attendanceCompute->count_late = 0;

                $this->attendanceCompute->hours_undertime = 0;
                $this->attendanceCompute->count_undertime = 0;

                $this->attendanceCompute->am_login = 0;
                $this->attendanceCompute->pm_login = 0;

                $this->attendanceCompute->am_logout = 0;
                $this->attendanceCompute->pm_logout = 0;

                $this->attendanceCompute->pm_late_hours = 0;
                $this->attendanceCompute->pm_undertime_hours = 0;

                // Set the line number from where in the pdf position will be place
                $line_numbers = [4, 6, 8, 11, 14, 17, 19, 21, 24, 28];

                $line_number_view = (in_array($this->line_number, $line_numbers)) ? 4 : 3.5;

                $this->pdf->Ln($line_number_view);

                // Check for saturdays and sundays

                // If the date has log on it
                if (
                    $this->am_login != '' or $this->am_logout != '' or $this->pm_login != '' or
                    $this->pm_logout != '' or $this->ot_login != '' or $this->ot_logout != ''
                ) {
                    //Do nothing

                    // If the date is holiday
                    if ($this->is_holiday == true) {
                        // Lets check if the holiday is half day
                        if ($this->holiday->half_day == true) {
                            if ($this->holiday->am_pm == 'am') {
                                $this->am_login = strtoupper($this->holiday->name);
                            } else {
                                $this->pm_login = strtoupper($this->holiday->name);
                            }
                        }
                    }
                } else {
                    // Do if not Leave
                    if ($this->sat_or_sun == 'Saturday') {
                        $this->am_login = 'SATURDAY';
                    }

                    if ($this->sat_or_sun == 'Sunday') {
                        $this->am_login = 'SUNDAY';
                    }

                    // If the date is holiday
                    if ($this->is_holiday == true) {
                        $this->am_login = strtoupper($this->holiday->name);
                    }
                }

                // Lets check if the logs has atleast one OB in entries
                $has_ob = false;

                $has_ob = $this->attendanceCompute->has_ob(
                    $this->am_login,
                    $this->am_logout,
                    $this->pm_login,
                    $this->pm_logout
                );

                $ob_location = '';

                if ($has_ob == true) {
                    // $ob_location = $this->Manual_log->get_notes($this->manual_log_id); // TODO
                    // Check if Official Business (Change Official Business to OB)
                    $this->am_login = $this->attendanceCompute->is_ob($this->am_login);
                    $this->am_logout = $this->attendanceCompute->is_ob($this->am_logout);
                    $this->pm_login = $this->attendanceCompute->is_ob($this->pm_login);
                    $this->pm_logout = $this->attendanceCompute->is_ob($this->pm_logout);
                }

                $notes = '';

                // If whole day ob
                if (
                    $this->am_login == 'OB' && $this->am_logout == 'OB' &&
                    $this->pm_login == 'OB' && $this->pm_logout == 'OB'
                ) {
                    $ob_location = '';

                    // $notes = $this->Manual_log->get_notes($this->manual_log_id);

                    if (trim($notes) == '') {
                        $this->am_login = 'OB';
                        $this->am_logout = 'OB';
                        $this->pm_login = 'OB';
                        $this->pm_logout = 'OB';
                    } else {
                        $this->am_login = 'Official Business  --';
                        $this->am_logout = '';
                        $this->pm_login = $notes;
                        $this->pm_logout = '';
                    }
                }

                // Check if Travel Order (Change Travel Order to To)
                $this->am_login = $this->attendanceCompute->is_to($this->am_login);
                $this->am_logout = $this->attendanceCompute->is_to($this->am_logout);
                $this->pm_login = $this->attendanceCompute->is_to($this->pm_login);
                $this->pm_logout = $this->attendanceCompute->is_to($this->pm_logout);

                // If whole day to
                if (
                    $this->am_login == 'TO' && $this->am_logout == 'TO' &&
                    $this->pm_login == 'TO' && $this->pm_logout == 'TO'
                ) {
                    // $notes = $this->Manual_log->get_notes($this->manual_log_id); //  TODO

                    if (trim($notes) == '') {
                        $this->am_login = 'TO';
                        $this->am_logout = 'TO';
                        $this->pm_login = 'TO';
                        $this->pm_logout = 'TO';
                    } else {
                        $this->am_login = 'Travel Order --';
                        $this->am_logout = '';
                        $this->pm_login = trim($notes);
                        $this->pm_logout = '';
                    }
                }

                // Check if the log is leave
                if (
                    $this->leave_type_id != 0 && $this->am_login == 'Leave' && $this->am_logout == 'Leave' &&
                    $this->pm_login == 'Leave' && $this->pm_logout == 'Leave'
                ) {
                    $this->am_login = Leave::find($this->leave_type_id)?->name;
                    $this->am_logout = '';
                    $this->pm_login = '';
                    $this->pm_logout = '';
                }

                $am_is_leave = false;

                // If half day leave (morning)
                if ($this->leave_type_id != 0 && $this->am_login == 'Leave' && $this->am_logout == 'Leave') {
                    $this->am_login = Leave::find($this->leave_type_id)?->code;
                    $this->am_logout = '';

                    $am_is_leave = true;
                }

                $pm_is_leave = false;
                // If half day leave (afternoon)
                if ($this->leave_type_id != 0 && $this->pm_login == 'Leave' && $this->pm_logout == 'Leave') {
                    $this->pm_login = Leave::find($this->leave_type_id)?->code;
                    $this->pm_logout = '';

                    $pm_is_leave = true;
                }

                // Execute only if regular hours (8-5)
                // and special sched (added 08-08-2011)
                if ($this->shift_type == 1 || $this->shift_type == 3) {

                    // We need to check if the settings enabled for this halfday tardy
                    // Check if there is no log on am or pm
                    // For the compliant to CSC MC 17 series of 2010
                    // put VL if absent on AM and VL if absent on PM

                    // AM Absent
                    if ($this->am_login == '' && $this->am_logout == '' && $this->pm_login != '' && $this->pm_logout != '') {
                        //$this->am_login = 'Vacation Leave';
                        //$this->am_login = 'Late';

                        // Update 2.8.2013
                        // Put Tardy only if not saturday or sunday
                        if ($this->sat_or_sun != 'Saturday' and $this->sat_or_sun != 'Sunday' and $this->is_holiday == false) {
                            $this->am_login = 'Tardy';
                            $this->am_logout = '';

                            // Change font color
                            $this->attendanceCompute->font_color_am_login = 200;
                        }
                    }

                    // PM Absent
                    if ($this->am_login != '' && $this->am_logout != '' && $this->pm_login == '' && $this->pm_logout == '') {
                        //$this->pm_login = 'Vacation Leave';
                        $this->pm_login = 'Undertime';
                        $this->pm_logout = '';

                        // Check if saturday or sunday and shift_id == 1
                        // if true we dont need to put undertime to DTR
                        if ($this->shift_type == 1 && ($this->sat_or_sun == 'Saturday' || $this->sat_or_sun == 'Sunday')) {
                            $this->pm_login = '';
                        }

                        // Change font color
                        $this->attendanceCompute->font_color_pm_login = 200;
                    }
                    // PM Absent(no log out)
                    elseif ($this->am_login != '' && $this->am_logout != '' && $this->pm_login != '' && $this->pm_logout == '') {
                        $this->pm_logout = 'Undertime';

                        // add this lines 12.14.2012 8.37am
                        if (
                            $this->is_holiday and
                            $this->holiday->half_day == true and
                            $this->holiday->am_pm == 'pm'
                        ) {

                            $this->pm_logout = '';
                        }
                        // end add 12.14.2012 8.37am

                        // Check if saturday or sunday and shift_id == 1
                        // if true we dont need to put undertime to DTR
                        if ($this->shift_type == 1 && ($this->sat_or_sun == 'Saturday' || $this->sat_or_sun == 'Sunday')) {
                            $this->pm_logout = '';
                        }

                        // If pm is leave
                        if ($pm_is_leave == true) {
                            $this->pm_logout = '';
                        }

                        // Change font color
                        $this->attendanceCompute->font_color_pm_logout = 200;
                    }

                    // PM Absent(no log in)
                    elseif ($this->am_login != '' && $this->am_logout != '' && $this->pm_login == '' && $this->pm_logout != '') {
                        //$this->pm_logout= 'Undertime';
                        $this->pm_login = 'UT';

                        // If pm is leave
                        if ($pm_is_leave == true) {
                            //$this->pm_logout = '';
                        }

                        // Change font color
                        $this->attendanceCompute->font_color_pm_login = 200;
                    }

                    // AM Absent(no log out)
                    //else if($this->am_login != '' && $this->am_logout == '' && $this->pm_login != '' && $this->pm_logout != '')
                    elseif ($this->am_login != '' && $this->am_logout == '' && $this->pm_login != '' && $this->pm_logout != '' && $this->leave_type_id == 0) {
                        $this->am_logout = 'Tardy';

                        // If pm is leave
                        if ($pm_is_leave == true) {
                            //$this->pm_logout = '';
                        }

                        // Change font color
                        $this->attendanceCompute->font_color_am_logout = 200;
                    }

                    // no am log then OB for 3 entries 07.3.2012
                    elseif ($this->am_login == '' && $this->am_logout == 'OB' && $this->pm_login == 'OB' && $this->pm_logout == 'OB' && $this->leave_type_id == 0) {
                        $this->am_login = 'Tardy';

                        // If pm is leave
                        if ($pm_is_leave == true) {
                            //$this->pm_logout = '';
                        }

                        // Change font color
                        $this->attendanceCompute->font_color_am_login = 200;
                    }
                }

                //convert the time to 12 hour

                //am login A
                $this->pdf->SetX(24);
                $this->pdf->SetTextColor($this->attendanceCompute->font_color_am_login, 0, 0);
                $this->pdf->SetFont('Times', $this->attendanceCompute->am_font_bold, 9);
                $this->pdf->Write(0, $this->am_login);

                //am login B
                $this->pdf->SetX(118);
                $this->pdf->Write(0, $this->am_login);
                $this->attendanceCompute->font_color_am_login = 0;
                $this->attendanceCompute->am_font_bold = '';

                //am logout A
                $this->pdf->SetX(36);
                $this->pdf->SetTextColor($this->attendanceCompute->font_color_am_logout, 0, 0);
                $this->pdf->SetFont('Times', $this->attendanceCompute->am_out_bold, 9);
                $this->pdf->Write(0, $this->am_logout);

                //am logout B
                $this->pdf->SetX(130);
                $this->pdf->Write(0, $this->am_logout);
                $this->attendanceCompute->font_color_am_logout = 0;
                $this->attendanceCompute->am_out_bold = '';

                //pm login A
                $this->pdf->SetX(50);
                $this->pdf->SetTextColor($this->attendanceCompute->font_color_pm_login, 0, 0);
                $this->pdf->SetFont('Times', $this->attendanceCompute->pm_font_bold, 9);

                // Change format(only if notes is blank)
                if (isset($notes) && $notes != '') {
                    $this->pm_login = $notes;
                } else {
                    // If pm login is not leave and pm login is not half day absent(vacation leave)
                    if (($this->leave_type_id == 0 && $this->pm_login != 'Undertime' && $this->pm_login != 'UT')) {

                        $this->pm_login = change_format($this->pm_login, 1, $format = '');

                        // TODO
                        if (
                            $this->is_holiday and
                            $this->holiday->half_day == true and
                            $this->holiday->am_pm == 'pm'
                        ) {

                            $this->pm_login = $this->holiday->name;
                        }
                    }

                    // If leave in the pm and pm_login is not equal to leave code(VL, SPL)
                    // TODO
                    if ($this->leave_type_id != 0 && $this->pm_login != Leave::find($this->leave_type_id)?->code) {
                        $this->pm_login = $this->attendanceCompute->change_format($this->pm_login, 1, $format = '');
                    }
                }

                // Reset the notes to blank
                $notes = '';

                $this->pdf->Write(0, $this->pm_login);

                //pm login B
                $this->pdf->SetX(144);
                $this->pdf->Write(0, $this->pm_login);
                $this->attendanceCompute->font_color_pm_login = 0;
                $this->attendanceCompute->pm_font_bold = '';

                //pm logout A
                $this->pdf->SetX(62);
                $this->pdf->SetTextColor($this->attendanceCompute->font_color_pm_logout, 0, 0);
                $this->pdf->SetFont('Times', $this->attendanceCompute->pm_out_bold, 9);

                // Change format if the entry is not leave and not Undertime or (leave in the morning)
                if (($this->leave_type_id == 0 && $this->pm_logout != 'Undertime') or $am_is_leave == true) {
                    $this->pm_logout = $this->attendanceCompute->change_format($this->pm_logout, 1, $format = '');
                }
                $this->pdf->Write(0, $this->pm_logout);

                //pm logout B
                $this->pdf->SetX(157);
                $this->pdf->Write(0, $this->pm_logout);
                $this->attendanceCompute->font_color_pm_logout = 0;
                $this->attendanceCompute->pm_out_bold = '';

                //ot login A
                $this->pdf->SetX(74);
                $this->pdf->SetTextColor(0, 0, 0);

                if ($has_ob == true and trim($ob_location) != '') {
                    $this->ot_login = $ob_location;
                } else {
                    $this->ot_login = change_format($this->ot_login, 1, $format = '');
                }

                $this->pdf->Write(0, $this->ot_login);

                //ot login B
                $this->pdf->SetX(169);
                $this->pdf->SetTextColor(0, 0, 0);
                $this->pdf->Write(0, $this->ot_login);

                //ot logout A
                $this->pdf->SetX(88);
                $this->pdf->SetTextColor(0, 0, 0);
                $this->ot_logout = change_format($this->ot_logout, 1, $format = '');
                $this->pdf->Write(0, $this->ot_logout);

                //ot logout B
                $this->pdf->SetX(182);
                $this->pdf->SetTextColor(0, 0, 0);
                $this->pdf->Write(0, $this->ot_logout);

                $this->line_number++;

                $this->holiday = null;
            } //foreach ($dtr_result as $row) ========= END ====================================

            // If allow the 40 hrs per week (offsettings)=====================================
            if ($this->allow_40hrs == true) {
                // If the work for 1st week is 40 hours or more or 5 days or more
                if ($this->attendanceCompute->compute_time($this->week1_hours) >= '5 days') {

                    // Subtract the week 1 total late and undertime count
                    $this->late_count -= $this->week1_late_count;
                    $this->undertime_count -= $this->week1_undertime_count;

                    // Subtract the week 1 total late and undertime hours
                    $this->late_final -= $this->week1_late_final;
                    $this->undertime_final -= $this->week1_undertime_final;
                }

                // If the work for 2nd week is 40 hours or more or 5 days or more
                if ($this->attendanceCompute->compute_time($this->week2_hours) >= '5 days') {
                    // Subtract the week 2 total late and undertime count
                    $this->late_count -= $this->week2_late_count;
                    $this->undertime_count -= $this->week2_undertime_count;

                    // Subtract the week 2 total late and undertime hours
                    $this->late_final -= $this->week2_late_final;
                    $this->undertime_final -= $this->week2_undertime_final;
                }

                // If the work for 3rd week is 40 hours or more or 5 days or more
                if ($this->attendanceCompute->compute_time($this->week3_hours) >= '5 days') {
                    // Subtract the week 3 total late and undertime count
                    $this->late_count -= $this->week3_late_count;
                    $this->undertime_count -= $this->week3_undertime_count;

                    // Subtract the week 3 total late and undertime hours
                    $this->late_final -= $this->week3_late_final;
                    $this->undertime_final -= $this->week3_undertime_final;
                }

                // If the work for 4th week is 40 hours or more or 5 days or more
                if ($this->attendanceCompute->compute_time($this->week4_hours) >= '5 days') {
                    // Subtract the week 4 total late and undertime count
                    $this->late_count -= $this->week4_late_count;
                    $this->undertime_count -= $this->week4_undertime_count;

                    // Subtract the week 4 total late and undertime hours
                    $this->late_final -= $this->week4_late_final;
                    $this->undertime_final -= $this->week4_undertime_final;
                }
            }

            // =================================================================
            // set initial placement
            $this->pdf->SetXY(30, 34);
            //$this->pdf->Ln(160.5);
            $this->pdf->Ln(153);
            //total A
            $this->pdf->SetX(50);
            $this->pdf->Write(0, ucwords(($this->attendanceCompute->compute_time($this->number_of_hours_work))));
            //total B
            $this->pdf->SetX(145);
            $this->pdf->Write(0, ucwords(($this->attendanceCompute->compute_time($this->number_of_hours_work))));

            // LGU CODE
            $lgu_code = get_setting('lgu_code');

            if ($lgu_code == 'marinduque_province') {
                // Total tardiness
                $this->total_tardiness = $this->late_final + $this->undertime_final;

                $this->undertime_final = $this->attendanceCompute->compute_time($this->undertime_final + $this->late_final); // TODO

                $this->late_final = '';
            } else {
                // Total tardiness
                $this->total_tardiness = $this->late_final + $this->undertime_final;

                // Compute the late in words
                $this->late_final = $this->attendanceCompute->compute_time($this->late_final); // TODO

                // Compute undertime
                $this->undertime_final = $this->attendanceCompute->compute_time($this->undertime_final); // TODO
            }

            // Total
            $this->pdf->Ln(48);
            $this->pdf->SetX(17);
            $this->pdf->Write(0, 'Tardiness:');

            $this->pdf->SetX(40);
            $this->pdf->Write(0, '(' . $this->late_count . 'x)' . $this->late_final);

            $this->pdf->SetX(110);
            $this->pdf->Write(0, 'Tardiness:');

            $this->pdf->SetX(133);
            $this->pdf->Write(0, '(' . $this->late_count . 'x)' . $this->late_final);

            $this->pdf->Ln(6);

            $this->pdf->SetX(17);
            $this->pdf->Write(0, 'Under time:');
            $this->pdf->SetX(40);

            if ($lgu_code == 'marinduque_province') {
                $this->pdf->Write(0, $this->undertime_final);
            } else {
                $this->pdf->Write(0, '(' . $this->undertime_count . 'x) ' . $this->undertime_final);
            }

            $this->pdf->SetX(110);
            $this->pdf->Write(0, 'Under time:');

            $this->pdf->SetX(133);
            if ($lgu_code == 'marinduque_province') {
                $this->pdf->Write(0, $this->undertime_final);
            } else {
                $this->pdf->Write(0, '(' . $this->undertime_count . 'x) ' . $this->undertime_final);
            }

            //overtime
            $this->pdf->Ln(6);
            $this->number_of_hours_work = $this->attendanceCompute->compute_time($this->number_of_hours_work);
            $this->pdf->SetX(17);
            //$this->pdf->Write(0, ucwords(('Number of Tardiness:')));

            $this->pdf->SetX(50);
            //$this->pdf->Write(0, ucwords(($late_count + $undertime_count)));

            $this->pdf->SetX(110);
            //$this->pdf->Write(0, ucwords(('Number of Tardiness:')));

            $this->pdf->SetX(143);
            //$this->pdf->Write(0, ucwords(($late_count + $undertime_count)));

            //overtime
            $this->overtime = $this->attendanceCompute->compute_time($this->overtime);

            $print_overtime_in_dtr = get_setting('print_overtime_in_dtr');

            if ($print_overtime_in_dtr == 1) {
                $this->pdf->SetX(17);
                $this->pdf->Write(0, ucwords(('Over time:')));
                $this->pdf->Write(0, ucwords(($this->overtime)));

                $this->pdf->SetX(110);
                $this->pdf->Write(0, ucwords(('Over time:')));
                $this->pdf->Write(0, ucwords(($this->overtime)));
            }

            // If allow the 40 hrs per week
            if ($this->allow_40hrs == true) {
                $this->pdf->Ln(6);
                $this->pdf->SetX(17);
                $this->pdf->Write(0, ('The system allow the 40 hrs a week'));

                $this->pdf->SetX(110);
                $this->pdf->Write(0, ('The system allow the 40 hrs a week'));

                // We require hours only here.
                // $this->attendanceCompute->hours_only = true; // TODO

                $this->pdf->Ln(4);
                $this->pdf->SetX(17);
                $this->pdf->Write(0, ('1st week: ' . $this->attendanceCompute->compute_time($this->week1_hours)));

                $this->pdf->SetX(110);
                $this->pdf->Write(0, ('1st week: ' . $this->attendanceCompute->compute_time($this->week1_hours)));

                $this->pdf->Ln(4);
                $this->pdf->SetX(17);
                $this->pdf->Write(0, ('2nd week: ' . $this->attendanceCompute->compute_time($this->week2_hours)));

                $this->pdf->SetX(110);
                $this->pdf->Write(0, ('2nd week: ' . $this->attendanceCompute->compute_time($this->week2_hours)));

                $this->pdf->Ln(4);
                $this->pdf->SetX(17);
                $this->pdf->Write(0, ('3rd week: ' . $this->attendanceCompute->compute_time($this->week3_hours)));

                $this->pdf->SetX(110);
                $this->pdf->Write(0, ('3rd week: ' . $this->attendanceCompute->compute_time($this->week3_hours)));

                $this->pdf->Ln(4);
                $this->pdf->SetX(17);
                $this->pdf->Write(0, ('4th week: ' . $this->attendanceCompute->compute_time($this->week4_hours)));

                $this->pdf->SetX(110);
                $this->pdf->Write(0, ('4th week: ' . $this->attendanceCompute->compute_time($this->week4_hours)));
            }

            // Add office head and designation
            $print_office_head_in_dtr = get_setting('print_office_head_in_dtr');

            if ($print_office_head_in_dtr == 1) {

                $office = $employee->office;

                // If detailed
                if ($employee->detailed_office_id != 0) {
                    // TODO
                    // $office['office_head'] = $detailed_office['office_head'];
                    // $office['position'] = $detailed_office['position'];
                }

                // If Employee is Department Head
                // $o = new Office_m();

                // $o->get_by_employee_id($this->employee_id);

                // if ($o->exists()) {
                //     if ($lgu_code == 'marinduque_province') {
                //         $office['office_head'] = 'CARMENCITA O. REYES';
                //         $office['position'] = 'Governor';
                //     } else { // for puerto princesa
                //         $office['office_head'] = 'ATTORNEY AGUSTIN M. ROCAMORA';
                //         $office['position'] = 'City Administrator';
                //     }

                // }

                // We need to work out for this as exception
                if ($this->employee_id === '61') {
                    $office['office_head'] = 'ANTONIO L. UY, JR. M.D.';
                    $office['position'] = 'Vice Governor';
                }

                // For Quirino Province
                if ($this->employee_id == '122265') {
                    $office['office_head'] = 'PELIGIO H. OBLIGACION, JR.';
                    $office['position'] = 'Supervising Administrative Officer';
                }

                $this->pdf->SetXY(17, 217);
                $this->pdf->Cell(73, 4, strtoupper($office['office_head']), '0', 0, 'C', false);

                $this->pdf->SetX(110);
                $this->pdf->Cell(73, 4, strtoupper($office['office_head']), '0', 0, 'C', false);

                $this->pdf->Ln(4);

                $this->pdf->SetX(17);
                $this->pdf->Cell(73, 4, $office['position'], '0', 0, 'C', false);

                $this->pdf->SetX(110);
                $this->pdf->Cell(73, 4, $office['position'], '0', 0, 'C', false);
            }

            // Process the adding of undertime to leave card
            // $this->leave_card_undertime(); TODO

            // Reset
            $this->late_count = 0;
            $this->late_final = 0;
            $this->undertime_count = 0;
            $this->undertime_final = 0;

            $this->line_number = 1;
            $this->overtime = 0;

            $this->number_of_hours_work = 0;

            header('Cache-Control: maxage=3600'); //Adjust maxage appropriately

            header('Pragma: public');

            // If the parameter is D = download F = save as file

            $this->pdf->Output('dtr/archives/' . $employee->office_id . "_$employee->id.pdf", 'F');

            // All name of dtr file. put in an array
            $multiple_dtr[] = 'dtr/archives/' . $employee->office_id . "_$employee->id.pdf";
        } //foreach($employee_array as $employee_id) ========= START ====================================

        if (file_exists('dtr/archives/' . $employee->office_id . '.pdf')) {
            unlink('dtr/archives/' . $employee->office_id . '.pdf');
        }

        $concat = new ConcatenatePdf();

        $concat->setFiles($multiple_dtr);
        $concat->concat();
        // Store to a file
        // $concat->Output('dtr/archives/'.$employee->office_id.'.pdf', 'F'); // Store to a file

        // Output the PDF
        $concat->Output('I', 'generated.pdf');

        // Delete all files
        foreach ($multiple_dtr as $filename) {
            if (file_exists($filename)) {
                unlink($filename);
            }
        }

        // Delete all tardiness with zero seconds
        // $this->Tardiness->delete_zero_seconds(); // TODO

    }

    public function set_dtr($row)
    {
        $this->am_login = $row->am_login;
        $this->am_logout = $row->am_logout;
        $this->pm_login = $row->pm_login;
        $this->pm_logout = $row->pm_logout;
        $this->ot_login = $row->ot_login;
        $this->ot_logout = $row->ot_logout;
        $this->leave_type_id = $row->leave_type_id;
        $this->log_date = $row->log_date;
        $this->manual_log_id = $row->manual_log_id;
    }

    public function setDtrValue($row)
    {
        $this->am_login = $row->am_login;
        $this->am_logout = $row->am_logout;
        $this->pm_login = $row->pm_login;
        $this->pm_logout = $row->pm_logout;
        $this->ot_login = $row->ot_login;
        $this->ot_logout = $row->ot_logout;
        $this->leave_type_id = $row->leave_type_id;
        $this->log_date = $row->log_date;
        $this->manual_log_id = $row->manual_log_id;
    }
}
