<?php

require_once(__DIR__ . '/../../config.php'); // Inclui config do Moodle

// Requer login como admin para segurança
require_login();
require_admin();

// Constrói o caminho completo para o arquivo JS
$filepath = $CFG->dirroot . '/mod/bunnyvideo/amd/src/player_handler.js';

echo "<h1>Teste de Acesso ao Arquivo JS</h1>";
echo "<p>Verificando o arquivo: <code>" . htmlspecialchars($filepath) . "</code></p>";

if (file_exists($filepath)) {
    echo "<p style='color:green;'>Arquivo EXISTE.</p>";
    if (is_readable($filepath)) {
        echo "<p style='color:green;'>Arquivo tem permissão de LEITURA.</p>";
        // Opcional: Tenta ler uma parte do conteúdo
        $content_start = file_get_contents($filepath, false, null, 0, 100);
         echo "<p>Início do conteúdo:</p><pre>" . htmlspecialchars($content_start) . "</pre>";
    } else {
        echo "<p style='color:red;'>Arquivo NÃO tem permissão de LEITURA.</p>";
    }
} else {
    echo "<p style='color:red;'>Arquivo NÃO EXISTE neste caminho.</p>";
}

echo "<hr>";
echo "<p>Valor de \$CFG->dirroot: <code>" . htmlspecialchars($CFG->dirroot) . "</code></p>";
echo "<p>Usuário PHP atual: <code>" . htmlspecialchars(get_current_user()) . "</code> (pode não ser o usuário do servidor web)</p>";
// Tenta obter o usuário do processo web (nem sempre funciona)
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    echo "<p>Usuário do processo web (provável): <code>" . htmlspecialchars($processUser['name']) . "</code></p>";
}

?>
