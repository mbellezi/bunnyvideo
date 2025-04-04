<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->libdir.'/completionlib.php'); // Needed for COMPLETION_TRACKING_NONE constant

class mod_bunnyvideo_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform = $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_bunnyvideo'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'bunnyvideo_settings', get_string('pluginname', 'mod_bunnyvideo'));

        $mform->addElement('textarea', 'embedcode', get_string('embedcode', 'mod_bunnyvideo'), 'wrap="virtual" rows="6" cols="80"');
        $mform->addHelpButton('embedcode', 'embedcode', 'mod_bunnyvideo');
        $mform->setType('embedcode', PARAM_RAW);
        $mform->addRule('embedcode', get_string('required'), 'required', null, 'client');
        $mform->addRule('embedcode', get_string('invalidembedcode', 'mod_bunnyvideo'), 'regex', '/<iframe.*src=.*iframe\.mediadelivery\.net.*<\/iframe>/is', 'client');

        // Adiciona o campo de porcentagem para conclusão
        $mform->addElement('text', 'completionpercent', get_string('completionpercent', 'mod_bunnyvideo'), array('size' => '3'));
        $mform->addHelpButton('completionpercent', 'completionpercent', 'mod_bunnyvideo');
        $mform->setType('completionpercent', PARAM_INT);
        $mform->addRule('completionpercent', get_string('numeric'), 'numeric', null, 'client');
        $mform->addRule('completionpercent', get_string('invalidarg_numeric', 'completion', 100), 'rangelength', array(0, 100), 'client'); // Range 0-100
        $mform->setDefault('completionpercent', 80); // Default to 80%

        // Desabilita o campo de porcentagem se a conclusão de atividade estiver desabilitada no Moodle.
        // 'completion' é o nome do elemento padrão que o Moodle adiciona para o controle principal da conclusão.
        $mform->disabledIf('completionpercent', 'completion', 'eq', COMPLETION_TRACKING_NONE);


        //-------------------------------------------------------------------------------
        // Elementos padrão do Moodle para módulos (visibilidade, grupos, etc.)
        // Esta chamada é importante e deve permanecer.
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------------------------------
        // REMOVIDO: A chamada abaixo estava incorreta e não existe.
        // O Moodle adiciona a seção de conclusão automaticamente com base no suporte em lib.php
        // $this->standard_completion_elements(); // <<< LINHA REMOVIDA!

        //-------------------------------------------------------------------------------
        // Botões de ação (Salvar e voltar, Salvar e mostrar, Cancelar)
        $this->add_action_buttons();
    }

    /**
      * Ajusta os dados após o envio do formulário, antes de salvar.
      * Garante que completionpercent seja 0 se a conclusão estiver desabilitada.
      * @param object $data data from the form
      * @return object processed data object
      */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        // Se a conclusão da atividade estiver explicitamente desabilitada, forçar percentual para 0.
        // 'completion' é o nome do elemento padrão do Moodle para a configuração principal de conclusão.
        if (isset($data->completion) && $data->completion == COMPLETION_TRACKING_NONE) {
            $data->completionpercent = 0;
        } else if (isset($data->completionpercent) && ($data->completionpercent < 0 || $data->completionpercent > 100)) {
             // Garante que o valor esteja no range 0-100 se a conclusão estiver habilitada. Fora do range, zera.
             $data->completionpercent = 0;
        } else if (!isset($data->completionpercent)) {
             // Garante que seja 0 se não for definido por algum motivo (e a conclusão não está desabilitada)
             $data->completionpercent = 0;
        }

        // Remove o campo antigo que era usado pela checkbox, caso ainda exista nos dados crus do POST
        unset($data->completionwhenpercentreached);

        return $data;
    }

    // A validação pode permanecer como está.
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validação do embedcode
        $embedcode = trim($data['embedcode']);
        if (!empty($embedcode)) {
            if (!preg_match('/<iframe.*src=.*iframe\.mediadelivery\.net.*<\/iframe>/is', $embedcode)) {
                 $errors['embedcode'] = get_string('invalidembedcode', 'mod_bunnyvideo');
            }
        }

        // Validação de range (um pouco redundante, mas segura)
        if (isset($data['completionpercent']) && ($data['completionpercent'] < 0 || $data['completionpercent'] > 100)) {
             $errors['completionpercent'] = get_string('invalidarg_numeric', 'completion', 100);
        }

        return $errors;
    }
}
