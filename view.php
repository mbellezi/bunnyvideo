<?php
require_once('../../config.php'); // Configuração do Moodle
require_once('lib.php');       // Inclui o arquivo da biblioteca

$id = required_param('id', PARAM_INT); // ID do Módulo do Curso

// Obtém o módulo do curso e o registro do curso
if (!$cm = get_coursemodule_from_id('bunnyvideo', $id)) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}
if (!$bunnyvideo = $DB->get_record('bunnyvideo', array('id' => $cm->instance))) {
    print_error('invalidcoursemodule'); // Ou um erro mais específico
}

// Requer que o usuário esteja logado e inscrito (ou acesso de visitante, se permitido)
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/bunnyvideo:view', $context);

// IMPORTANTE: Desabilitar explicitamente o rastreamento de visualização para conclusão
// Isso evita que o Moodle marque a atividade como concluída quando o aluno apenas visualiza
if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC && $cm->completionview) {
    // Se as configurações atuais incluem completionview, vamos remover esse comportamento
    // temporariamente para esta execução, sem alterar o banco de dados
    $cm->completionview = 0;
}

// --- Configura a página ---
$PAGE->set_url('/mod/bunnyvideo/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($bunnyvideo->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// --- Exibe a página ---
echo $OUTPUT->header();

// Chama a função view de lib.php para renderizar o conteúdo
echo bunnyvideo_view($bunnyvideo, $cm, $context);

echo $OUTPUT->footer();
