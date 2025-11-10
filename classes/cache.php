<?php
/**
 * Cache definitions for Adeptus Insights plugin
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'auth_cache' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
        'ttl' => 300, // 5 minutes
        'mappingsonly' => false,
        'invalidationevents' => [],
        'canuselocalstore' => true,
    ],
];


