<?php

use App\Models\Employee;
use App\Models\JobVacancy;

it('resolves every government relation on the employee model', function () {
    $employee = new Employee;

    $relations = [
        'nosa',
        'personalInformation',
        'familyBackground',
        'children',
        'educations',
        'eligibilities',
        'workExperiences',
        'voluntaryWorks',
        'pdsTrainings',
        'otherInfos',
        'questions',
        'references',
        'serviceRecords',
    ];

    foreach ($relations as $relation) {
        $related = $employee->{$relation}()->getRelated();

        expect(class_exists($related::class))->toBeTrue(
            'Employee::'.$relation.'() points at missing model '.$related::class,
        );
    }
});

it('resolves the plantilla item relation on a job vacancy', function () {
    $related = (new JobVacancy)->plantillaItem()->getRelated();

    expect(class_exists($related::class))->toBeTrue(
        'JobVacancy::plantillaItem() points at missing model '.$related::class,
    );
});

it('does not reference government models from the old models namespace', function () {
    $models = [
        'Children',
        'Education',
        'Eligibility',
        'EmployeePlantilla',
        'FamilyBackground',
        'LeaveApplication',
        'LeaveCard',
        'Nosa',
        'OtherInfo',
        'PersonalInformation',
        'PlantillaItem',
        'Question',
        'Reference',
        'SalaryGrade',
        'ServiceRecord',
        'Training',
        'TrainingContact',
        'TrainingContactType',
        'TrainingCourse',
        'TrainingEvent',
        'TrainingType',
        'VoluntaryWork',
        'WorkExperience',
    ];

    foreach ($models as $model) {
        expect(class_exists('App\\Models\\'.$model))->toBeFalse(
            'App\\Models\\'.$model.' still exists; it belongs in the Philippines Government module',
        );
    }
});
