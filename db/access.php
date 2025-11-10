<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'report/adeptus_insights:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];
