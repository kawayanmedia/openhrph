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
            ['type' => 'resource', 'key' => 'plantilla-item', 'label' => 'Plantilla Items'],
            ['type' => 'resource', 'key' => 'salary-grade', 'label' => 'Salary Grades'],
            ['type' => 'resource', 'key' => 'nosa', 'label' => 'NOSA'],
            ['type' => 'resource', 'key' => 'service-record', 'label' => 'Service Records'],
            ['type' => 'resource', 'key' => 'leave-card', 'label' => 'Leave Cards'],
        ],
    ],
];
