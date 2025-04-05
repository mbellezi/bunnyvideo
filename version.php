<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_bunnyvideo';  // Full name of the plugin (used for diagnostics)
$plugin->version   = 2025040504;        // Versão incrementada novamente
$plugin->requires  = 2021051700;        // Moodle 3.11+ (Adjust as needed for JS module/API usage)
$plugin->maturity  = MATURITY_ALPHA;    // MATURITY_ALPHA, BETA, RC, or STABLE
$plugin->release   = '0.1.0';
$plugin->pluginname = get_string('pluginname', 'mod_bunnyvideo'); // Use lang string
$plugin->visible = true; // Explicitamente marcar o plugin como visível
