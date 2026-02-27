<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_bunnyvideo';  // Nome completo do plugin (usado para diagnósticos)
$plugin->version = 2026022705;        // Fix: restored bunnyvideo_get_completion_state callback
$plugin->requires = 2021051700;        // Moodle 3.11+ (Ajuste conforme necessário para uso de módulo/API JS)
$plugin->maturity = MATURITY_ALPHA;    // MATURITY_ALPHA, BETA, RC ou STABLE
$plugin->release = '0.3.0';
$plugin->pluginname = get_string('pluginname', 'mod_bunnyvideo'); // Usa string de idioma
$plugin->visible = true; // Explicitamente marcar o plugin como visível
