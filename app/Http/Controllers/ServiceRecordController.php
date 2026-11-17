<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Nosa;
use setasign\Fpdi\Fpdi;

class ServiceRecordController extends Controller
{
    public $array_pdf = [];


    public $pdf = '';


    public function __construct()
    {
        $this->pdf = new Fpdi();
    }

    /**
     * Display the specified resource.
     */
    public function serviceRecord($id)
    {
        $employee = Employee::with('serviceRecords')->find($id);

        // dd($employee);

        dd($employee->serviceRecords);


        $nosa = Nosa::with('employee')->find($id);
        // initiate FPDI
        $this->pdf = new FPDI();
        // add a page
        $this->pdf->AddPage();
        // set the sourcefile
        $this->pdf->setSourceFile('templates/nosa.pdf');

        // select the first page
        $tplIdx = $this->pdf->importPage(1);

        // use the page we imported
        $this->pdf->useTemplate($tplIdx);

        // set font, font style, font size.
        $this->pdf->SetFont('Times', '', 11);

        // set initial placement
        $this->pdf->SetXY(150, 64);
        $this->pdf->Write(0, now()->format('F d, Y'));


        $this->pdf->SetXY(24, 73);
        $this->pdf->Write(0, $nosa->employee->salut . ' ' . strtoupper($nosa->employee->first_name . ' ' . $nosa->employee->middle_name . ' ' . $nosa->employee->last_name));

        $this->pdf->SetXY(24, 78);
        $this->pdf->Write(0, $nosa->employee->office?->name);

        $this->pdf->SetXY(24, 84);
        $this->pdf->Write(0, 'BOAC, Marinduque');



        $this->pdf->SetXY(35, 99);
        $this->pdf->Write(0, $nosa->employee->salut . ' ' . ucfirst($nosa->employee->last_name) . ':');


        $this->pdf->SetXY(59, 118);
        $this->pdf->Write(0, $nosa->effective_date->format('F d, Y'));


        $this->pdf->SetXY(111, 128);
        $this->pdf->Write(0, $nosa->effective_date->format('F d, Y'));


        $this->pdf->SetXY(94, 133);
        $this->pdf->Write(0, $nosa->to_salary_grade);


        $this->pdf->SetXY(117, 133);
        $this->pdf->Write(0, $nosa->to_step);


        $this->pdf->SetXY(101, 142);
        $this->pdf->Write(0, $nosa->effective_date->subDay()->format('F d, Y'));

        $this->pdf->SetXY(45, 147);
        $this->pdf->Write(0, $nosa->from_salary_grade);


        $this->pdf->SetXY(65, 147);
        $this->pdf->Write(0, $nosa->from_step);

        $this->pdf->SetXY(105, 157);
        $this->pdf->Write(0, $nosa->effective_date->format('F d, Y'));

        $adjustedSalary = basic_salary($nosa->to_salary_grade_year, $nosa->to_salary_grade, $nosa->to_step);
        $actualSalary = basic_salary($nosa->from_salary_grade_year, $nosa->from_salary_grade, $nosa->from_step);

        // Salaries
        $this->pdf->SetXY(155, 133);
        $this->pdf->Write(0, number_format($adjustedSalary, 2));


        $this->pdf->SetXY(155, 147);
        $this->pdf->Write(0, number_format($actualSalary, 2));


        $this->pdf->SetXY(155, 157);
        $this->pdf->Write(0, number_format($adjustedSalary - $actualSalary, 2));


        // Below position title
        $this->pdf->SetXY(53, 230);
        $this->pdf->Write(0, $nosa->plantillaItem->position_title);

        $this->pdf->SetXY(53, 235);
        $this->pdf->Write(0, $nosa->plantillaItem->salary_grade);

        $this->pdf->SetXY(51, 240);
        $this->pdf->Write(0, $nosa->plantillaItem->year);

        $this->pdf->SetXY(102, 240);
        $this->pdf->Write(0, $nosa->plantillaItem->item_number);



        // line break
        // $this->pdf->Ln(5);

        // go to 25 X (indent)
        // $this->pdf->SetX(40);

        // If the parameter is D = download F = save as file, I to show
        $this->pdf->Output('dtr/archives/' . "$nosa->id.pdf", 'I');
    }
}
