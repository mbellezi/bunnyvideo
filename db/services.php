<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_bunnyvideo_mark_complete' => array(
        'classname'     => 'mod_bunnyvideo\external\completion_ajax',
        'methodname'    => 'mark_complete',
        'description'   => 'Marks the Bunny Video activity as complete for the current user.',
        'type'          => 'write', // Indicates it modifies data
        'ajax'          => true,    // Available via AJAX
        'capabilities'  => 'mod/bunnyvideo:view', // Usuário precisa pelo menos da capacidade de visualização
    ),
    
    'mod_bunnyvideo_toggle_completion' => array(
        'classname'     => 'mod_bunnyvideo\external\completion_ajax',
        'methodname'    => 'toggle_completion',
        'description'   => 'Alterna o status de conclusão de uma atividade Bunny Video para qualquer usuário (somente professor/admin).',
        'type'          => 'write', // Indica que modifica os dados
        'ajax'          => true,    // Disponível via AJAX
        'capabilities'  => 'mod/bunnyvideo:managecompletion', // Requer capacidade especial
    )
);
