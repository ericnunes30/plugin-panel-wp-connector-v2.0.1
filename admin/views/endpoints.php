<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permissões
if (!current_user_can('manage_options')) {
    wp_die(__('Você não tem permissão para acessar esta página.', 'panel-wp-connector'));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Endpoints da API', 'panel-wp-connector'); ?></h1>
    
    <?php settings_errors('panel_wp_endpoints'); ?>

    <div class="postbox">
        <div class="inside">
            <h2><?php _e('Informações Gerais', 'panel-wp-connector'); ?></h2>
            <p><?php _e('Base URL da API:', 'panel-wp-connector'); ?> <code><?php echo esc_html(get_rest_url(null, 'panel-wp/v1')); ?></code></p>
            <p><?php _e('Todas as requisições devem incluir o cabeçalho:', 'panel-wp-connector'); ?> <code>X-Api-Key: sua_chave_api</code></p>
        </div>
    </div>

    <div class="postbox">
        <div class="inside">
            <h2><?php _e('Endpoints Disponíveis', 'panel-wp-connector'); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="10%"><?php _e('Método', 'panel-wp-connector'); ?></th>
                        <th width="25%"><?php _e('Endpoint', 'panel-wp-connector'); ?></th>
                        <th width="50%"><?php _e('Descrição', 'panel-wp-connector'); ?></th>
                        <th width="15%"><?php _e('Requer Auth', 'panel-wp-connector'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Autenticação -->
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/authenticate</code></td>
                        <td><?php _e('Autentica o aplicativo e retorna informações do usuário e do site', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>

                    <!-- Status do Site -->
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/status</code></td>
                        <td><?php _e('Retorna o status atual do site', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>

                    <!-- Informações do Sistema -->
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/system-info</code></td>
                        <td><?php _e('Retorna informações detalhadas do sistema', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>

                    <!-- Debug Status -->
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/debug/status</code></td>
                        <td><?php _e('Retorna o status atual das configurações de debug', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>

                    <!-- Debug Toggle -->
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/debug/toggle</code></td>
                        <td><?php _e('Ativa ou desativa o debug. Requer parâmetro "ativar": true/false', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>

                    <!-- Debug Log -->
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/debug/log</code></td>
                        <td><?php _e('Retorna o conteúdo do arquivo debug.log', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>

                    <!-- Debug Log Clear -->
                    <tr>
                        <td><code>POST</code></td>
                        <td><code>/debug/log/clear</code></td>
                        <td><?php _e('Limpa o conteúdo do arquivo debug.log', 'panel-wp-connector'); ?></td>
                        <td><?php _e('Sim', 'panel-wp-connector'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="postbox">
        <div class="inside">
            <h2><?php _e('Exemplos de Uso', 'panel-wp-connector'); ?></h2>
            
            <h3><?php _e('Autenticação', 'panel-wp-connector'); ?></h3>
            <div class="code-example">
                <pre><code>curl -X POST "<?php echo esc_html(get_rest_url(null, 'panel-wp/v1/authenticate')); ?>" \
    -H "X-Api-Key: sua_chave_api"</code></pre>
            </div>

            <h3><?php _e('Status do Debug', 'panel-wp-connector'); ?></h3>
            <div class="code-example">
                <pre><code>curl -X GET "<?php echo esc_html(get_rest_url(null, 'panel-wp/v1/debug/status')); ?>" \
    -H "X-Api-Key: sua_chave_api"</code></pre>
            </div>

            <h3><?php _e('Ativar Debug', 'panel-wp-connector'); ?></h3>
            <div class="code-example">
                <pre><code>curl -X POST "<?php echo esc_html(get_rest_url(null, 'panel-wp/v1/debug/toggle')); ?>" \
    -H "Content-Type: application/json" \
    -H "X-Api-Key: sua_chave_api" \
    -d "{\"ativar\": true}"</code></pre>
            </div>

            <h3><?php _e('Obter Log', 'panel-wp-connector'); ?></h3>
            <div class="code-example">
                <pre><code>curl -X GET "<?php echo esc_html(get_rest_url(null, 'panel-wp/v1/debug/log')); ?>" \
    -H "X-Api-Key: sua_chave_api"</code></pre>
            </div>

            <h3><?php _e('Limpar Log', 'panel-wp-connector'); ?></h3>
            <div class="code-example">
                <pre><code>curl -X POST "<?php echo esc_html(get_rest_url(null, 'panel-wp/v1/debug/log/clear')); ?>" \
    -H "X-Api-Key: sua_chave_api"</code></pre>
            </div>
        </div>
    </div>
</div>

<style>
.postbox {
    margin-top: 20px;
}
.inside {
    padding: 0 12px 12px;
    margin: 11px 0;
    position: relative;
}
.inside h2 {
    padding: 8px 12px;
    margin: -12px -12px 8px;
    border-bottom: 1px solid #ccd0d4;
}
.inside h3 {
    margin: 1.5em 0 0.5em;
}
.code-example {
    background: #f5f5f5;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 0.5em 0 1.5em;
}
pre {
    margin: 0;
    padding: 15px;
    overflow-x: auto;
}
code {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
}
.wp-list-table code {
    background: transparent;
    padding: 0;
}
</style>
