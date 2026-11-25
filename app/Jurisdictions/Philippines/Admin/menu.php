<?php

/**
 * Philippine-only admin menu entries.
 *
 * Merged into the core navigation when the active jurisdiction is PH.
 */
return [
    [
        'label' => 'Statutory (PH)',
        'icon' => 'office-building',
        'jurisdiction' => 'PH',
        'groups' => [
            [
                'label' => 'Ref',
                'items' => [
                    // ['type' => 'resource', 'key' => 'phil-health', 'label' => 'PhilHealth'],
                    ['type' => 'resource', 'key' => 'sss', 'label' => 'SSS'],
                    ['type' => 'resource', 'key' => 'pagibig', 'label' => 'Pagibig'],
                    ['type' => 'resource', 'key' => 'minimum-wage-rate', 'label' => 'Minimum Wage Rate'],
                ],
            ],
        ],
    ]
];
