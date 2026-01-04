<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'report_adeptus_insights';
$plugin->version   = 2026010308; // YYYYMMDDXX - Fix AI Assistant header styling and remove core/chartjs to prevent reactive errors
$plugin->requires  = 2022112800; // Moodle 4.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.38';
