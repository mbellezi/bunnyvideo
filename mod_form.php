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

        // Removemos o campo de porcentagem daqui, pois ele será adicionado pela função add_completion_rules
        // que foi implementada no lib.php e será chamada pelo sistema de conclusão de atividades do Moodle

        //-------------------------------------------------------------------------------
        // Elementos padrão do Moodle para módulos (visibilidade, grupos, etc.)
        // Esta chamada é importante e deve permanecer.
        $this->standard_coursemodule_elements();
        
        // Add warning message about automatic completion only
        $mform->addElement('static', 'completioninfo', '', 
            '<div class="alert alert-info">'.
            'A conclusão manual pelo usuário não está disponível para atividades Bunny Video. '.
            'A conclusão será marcada automaticamente quando o vídeo for assistido até a porcentagem configurada.'.
            '</div>');

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

    /**
     * Adiciona ações de conclusão personalizada ao formulário de configuração.
     * Importante: esta função é chamada pelo Moodle para integrar as regras personalizadas
     * definidas em bunnyvideo_get_completion_rules()
     *
     * @return array
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        
        $group = [];
        $group[] = $mform->createElement('text', 'completionpercent', get_string('completionpercent', 'mod_bunnyvideo'), ['size' => 3]);
        $mform->setType('completionpercent', PARAM_INT);
        $mform->addGroup($group, 'completionpercentgroup', get_string('completionpercent', 'mod_bunnyvideo'), ' ', false);
        $mform->addHelpButton('completionpercentgroup', 'completionpercent', 'mod_bunnyvideo');
        $mform->setDefault('completionpercent', 80);
        
        return ['completionpercentgroup'];
    }

    /**
     * Ativa a regra de conclusão com base na presença de dados.
     * Importante: esta função é chamada pelo Moodle para determinar se a regra deve ser ativada
     *
     * @param array $data Form data
     * @return boolean
     */
    public function completion_rule_enabled($data) {
        return (!empty($data['completionpercent']) && $data['completionpercent'] > 0);
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
