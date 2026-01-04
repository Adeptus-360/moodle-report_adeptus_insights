<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'report_adeptus_insights';
$plugin->version   = 2026010320; // YYYYMMDDXX - Add debugging for stripe_product_id issue
$plugin->requires  = 2022112800; // Moodle 4.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.50';
