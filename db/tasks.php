<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'report_adeptus_insights\task\build_materialized_table',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '1',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    [
        'classname' => 'report_adeptus_insights\task\build_analytics_base',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '1',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];
