<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_bunnyvideo'; // Full Frankencode name
$plugin->version   = 2025040401; // YYYYMMDDXX (increment XX for same-day versions)
$plugin->requires  = 2021051700; // Moodle 3.11+ (Adjust as needed for JS module/API usage)
$plugin->maturity  = MATURITY_ALPHA; // Or BETA, RC, STABLE
$plugin->release   = '0.1.0';
$plugin->pluginname = get_string('pluginname', 'mod_bunnyvideo'); // Use lang string
