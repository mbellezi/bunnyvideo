<?php
namespace mod_bunnyvideo\event;

defined('MOODLE_INTERNAL') || die();

/**
 * O evento de visualização do módulo do curso.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    protected function init() {
        $this->data['objecttable'] = 'bunnyvideo'; // Nome da tabela principal do módulo
        parent::init();
    }

    /**
     * Retorna a URL relevante.
     * @return \moodle_url
     */
    public function get_url() {
        // URL para a página de visualização da instância específica do módulo
        return new \moodle_url('/mod/bunnyvideo/view.php', array('id' => $this->contextinstanceid));
    }

    // Você pode adicionar outros métodos aqui se precisar personalizar mais o evento
    // por exemplo, get_legacy_log_data() se necessário para logs legados.
}
