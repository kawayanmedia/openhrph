<?php

/**
 * Public-sector admin menu entries.
 *
 * Merged into the core navigation only when the active sector is Government
 * and the active jurisdiction is PH.
 */
return [
    [
        'label' => 'Government',
        'icon' => 'building-library',
        'sector' => 'Government',
        'jurisdiction' => 'PH',
        'items' => [
            ['type' => 'resource', 'key' => 'plantilla-version', 'label' => 'Plantilla Versions'],
            ['type' => 'resource', 'key' => 'plantilla-item', 'label' => 'Plantilla Items'],
            ['type' => 'resource', 'key' => 'qualification-standard', 'label' => 'Qualification Standards'],
            ['type' => 'resource', 'key' => 'qualification-screening', 'label' => 'Qualification Screenings'],
            ['type' => 'resource', 'key' => 'appointment-record', 'label' => 'Appointments'],
            ['type' => 'resource', 'key' => 'appointment-document', 'label' => 'Appointment Documents'],
            ['type' => 'resource', 'key' => 'appointment-action', 'label' => 'CSC Actions'],
            ['type' => 'resource', 'key' => 'assumption-record', 'label' => 'Assumptions'],
            ['type' => 'resource', 'key' => 'personnel-action', 'label' => 'Personnel Actions'],
            ['type' => 'resource', 'key' => 'hrmpsb-committee', 'label' => 'HRMPSB Committees'],
            ['type' => 'resource', 'key' => 'hrmpsb-member', 'label' => 'HRMPSB Members'],
            ['type' => 'resource', 'key' => 'recruitment-deliberation', 'label' => 'Deliberations'],
            ['type' => 'resource', 'key' => 'salary-grade', 'label' => 'Salary Grades'],
            ['type' => 'resource', 'key' => 'nosa', 'label' => 'NOSA'],
            ['type' => 'resource', 'key' => 'service-record', 'label' => 'Service Records'],
            ['type' => 'resource', 'key' => 'leave-card', 'label' => 'Leave Cards'],
        ],
    ],
];
