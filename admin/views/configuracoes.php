<?php
if (!defined('ABSPATH')) {
    exit;
}

// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$configuracoes = get_option('panel_wp_connector_options', []);
$ips_autorizados = get_option('panel_wp_authorized_ips', ['ips' => [], 'urls' => []]);
?>

<div class="wrap panel-wp-wrapper">
    <div class="panel-wp-header">
        <h1>
            <img src="<?php echo esc_url(PANEL_WP_CONNECTOR_URL . 'assets/icon.png'); ?>" alt="" style="height: 32px; vertical-align: middle; margin-right: 10px;">
            <?php echo esc_html(get_admin_page_title()); ?>
        </h1>
    </div>

    <?php settings_errors(); ?>

    <div class="panel-wp-card">
        <h2><?php _e('Suas Chaves de API', 'panel-wp-connector'); ?></h2>
        
        <?php 
        use PanelWPConnector\Authentication\PanelWPAuthentication;

        // Buscar usuários do WordPress
        $usuarios = get_users([
            'role__in' => ['administrator', 'editor', 'author'], // Adicione os níveis de usuário desejados
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        // Depuração para verificar o estado da sessão
        error_log('DEBUG: Estado da sessão: ' . session_status());
        error_log('DEBUG: Conteúdo da sessão: ' . print_r($_SESSION, true));

        // Processar ações de chaves API
        if (isset($_POST['gerar_chave']) && wp_verify_nonce($_POST['_wpnonce'], 'gerar_chave_api')) {
            error_log('DEBUG: Botão Gerar Chave clicado');
            
            $auth = new PanelWPConnector\Authentication\PanelWPAuthentication();
            $resultado_geracao = $auth->gerar_chave_api_temporaria();
            
            if ($resultado_geracao['success']) {
                // Usar $_SESSION diretamente
                $_SESSION['chave_api_temporaria'] = $resultado_geracao['api_key'];
                error_log('DEBUG: Chave temporária salva na sessão: ' . $_SESSION['chave_api_temporaria']);
                
                add_settings_error('panel_wp_api_keys', 'chave_gerada', 
                    __('Nova chave de API gerada. Selecione um usuário para salvar.', 'panel-wp-connector'), 
                    'success'
                );
            }
        }

        // Processar ação de limpar chave temporária
        if (isset($_POST['limpar_chave']) && wp_verify_nonce($_POST['_wpnonce'], 'limpar_chave_api')) {
            error_log('DEBUG: Botão Limpar Chave clicado');
            
            $auth = new PanelWPConnector\Authentication\PanelWPAuthentication();
            if ($auth->limpar_chave_temporaria()) {
                add_settings_error(
                    'panel_wp_messages',
                    'chave_limpa',
                    __('Chave temporária removida com sucesso.', 'panel-wp-connector'),
                    'success'
                );
            } else {
                add_settings_error(
                    'panel_wp_messages',
                    'erro_limpar_chave',
                    __('Erro ao tentar remover a chave temporária.', 'panel-wp-connector'),
                    'error'
                );
            }
        }

        // Verificar se existe uma chave temporária
        error_log('DEBUG: Verificando última chave salva');
        $ultima_chave = isset($_SESSION['chave_api_temporaria']) ? $_SESSION['chave_api_temporaria'] : null;
        error_log('DEBUG: Última chave salva existe: ' . ($ultima_chave ? 'Sim' : 'Não'));

        if ($ultima_chave) {
            echo '<div class="notice notice-warning inline"><p>';
            _e('Você tem uma chave temporária ativa. Por segurança, lembre-se de removê-la após o uso.', 'panel-wp-connector');
            echo '</p></div>';

            echo '<div class="panel-wp-api-key">';
            echo '<p><strong>' . __('Sua chave temporária:', 'panel-wp-connector') . '</strong><br>';
            echo '<code>' . esc_html($ultima_chave) . '</code></p>';
            
            // Formulário para limpar a chave
            echo '<form method="post" style="display: inline;">';
            wp_nonce_field('limpar_chave_api');
            echo '<input type="submit" name="limpar_chave" class="button button-secondary" value="' . esc_attr__('Limpar Chave Temporária', 'panel-wp-connector') . '">';
            echo '</form>';
            echo '</div>';
        }

        // Salvar chave definitivamente
        if (isset($_POST['salvar_chave_api']) && wp_verify_nonce($_POST['_wpnonce'], 'panel_wp_connector_salvar_chave')) {
            error_log('DEBUG: Botão Salvar Chave clicado');
            
            // Verificar se há chave temporária
            $chave_temporaria = isset($_SESSION['chave_api_temporaria']) ? $_SESSION['chave_api_temporaria'] : null;
            
            error_log('DEBUG: Chave Temporária na Sessão: ' . ($chave_temporaria ? $chave_temporaria : 'Não encontrada'));
            error_log('DEBUG: Conteúdo completo da sessão: ' . print_r($_SESSION, true));
            
            // Verificar se usuário foi selecionado
            $usuario_selecionado = isset($_POST['usuario_api_key']) ? intval($_POST['usuario_api_key']) : null;

            if (!$chave_temporaria) {
                add_settings_error('panel_wp_api_keys', 'sem_chave', 
                    __('Nenhuma chave temporária encontrada. Gere uma nova chave primeiro.', 'panel-wp-connector'), 
                    'error'
                );
            } elseif (!$usuario_selecionado) {
                add_settings_error('panel_wp_api_keys', 'usuario_nao_selecionado', 
                    __('Selecione um usuário para salvar a chave.', 'panel-wp-connector'), 
                    'error'
                );
            } else {
                $auth = new PanelWPConnector\Authentication\PanelWPAuthentication();
                
                // Definir manualmente a chave temporária na instância
                $auth->set_chave_temporaria($chave_temporaria);
                
                $resultado_salvamento = $auth->salvar_chave_api_temporaria($usuario_selecionado);

                if ($resultado_salvamento['success']) {
                    // Armazenar informações da chave salva na sessão
                    $usuario = get_userdata($resultado_salvamento['user_id']);
                    
                    if ($usuario) {
                        $_SESSION['ultima_chave_salva'] = [
                            'api_key' => $resultado_salvamento['api_key'],
                            'user_id' => $resultado_salvamento['user_id'],
                            'user_display_name' => $usuario->display_name,
                            'user_login' => $usuario->user_login,
                            'user_roles' => $usuario->roles
                        ];

                        // Limpar chave temporária da sessão
                        unset($_SESSION['chave_api_temporaria']);
                    }

                    add_settings_error('panel_wp_api_keys', 'chave_salva', 
                        __('Chave de API salva com sucesso!', 'panel-wp-connector'), 
                        'success'
                    );
                } else {
                    add_settings_error('panel_wp_api_keys', 'erro_salvar_chave', 
                        __('Erro ao salvar chave de API: ', 'panel-wp-connector') . $resultado_salvamento['message'], 
                        'error'
                    );
                }
            }
        }

        // Exibir chave temporária, se existir
        $chave_temporaria = isset($_SESSION['chave_api_temporaria']) ? $_SESSION['chave_api_temporaria'] : null;
        if ($chave_temporaria) {
            ?>
            <div class="panel-wp-card">
                <h3><?php _e('Chave API Temporária', 'panel-wp-connector'); ?></h3>
                <p><?php 
                    printf(__('Chave gerada: <code>%s</code>', 'panel-wp-connector'), 
                        esc_html($chave_temporaria)
                    ); 
                ?></p>
                <form method="post" class="panel-wp-form">
                    <?php wp_nonce_field('panel_wp_connector_salvar_chave'); ?>
                    
                    <div class="form-group">
                        <label for="usuario_api_key"><?php _e('Selecione o Usuário para Salvar a Chave', 'panel-wp-connector'); ?></label>
                        <select 
                            id="usuario_api_key" 
                            name="usuario_api_key" 
                            class="widefat"
                            required
                        >
                            <option value=""><?php _e('Selecione um usuário', 'panel-wp-connector'); ?></option>
                            <?php 
                            foreach ($usuarios as $usuario) {
                                $nome_usuario = $usuario->display_name . ' (' . $usuario->user_login . ')';
                                echo '<option value="' . esc_attr($usuario->ID) . '">' . 
                                     esc_html($nome_usuario) . 
                                     '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <?php submit_button(
                            __('Salvar Chave', 'panel-wp-connector'), 
                            'panel-wp-button panel-wp-button-primary', 
                            'salvar_chave_api'
                        ); ?>
                    </div>
                </form>
            </div>
            <?php
        }

        // Exibir última chave salva, se existir
        $ultima_chave_salva = isset($_SESSION['ultima_chave_salva']) ? $_SESSION['ultima_chave_salva'] : null;
        
        error_log('DEBUG: Verificando última chave salva');
        error_log('DEBUG: Última chave salva existe: ' . ($ultima_chave_salva ? 'Sim' : 'Não'));
        
        if ($ultima_chave_salva) {
            error_log('DEBUG: Detalhes da última chave salva');
            error_log('DEBUG: API Key: ' . $ultima_chave_salva['api_key']);
            error_log('DEBUG: User ID: ' . $ultima_chave_salva['user_id']);
            error_log('DEBUG: User Display Name: ' . $ultima_chave_salva['user_display_name']);
            ?>
            <div class="panel-wp-card">
                <h3><?php _e('Última Chave Salva', 'panel-wp-connector'); ?></h3>
                <div class="panel-wp-chave-detalhes">
                    <div class="panel-wp-chave-info">
                        <strong><?php _e('Chave API:', 'panel-wp-connector'); ?></strong>
                        <code><?php echo esc_html($ultima_chave_salva['api_key']); ?></code>
                    </div>
                    <div class="panel-wp-usuario-info">
                        <strong><?php _e('Usuário:', 'panel-wp-connector'); ?></strong>
                        <?php 
                        printf(
                            __('%s (Login: %s, ID: %d)', 'panel-wp-connector'), 
                            esc_html($ultima_chave_salva['user_display_name']),
                            esc_html($ultima_chave_salva['user_login']),
                            esc_html($ultima_chave_salva['user_id'])
                        ); 
                        ?>
                    </div>
                    <div class="panel-wp-usuario-roles">
                        <strong><?php _e('Funções:', 'panel-wp-connector'); ?></strong>
                        <?php echo esc_html(implode(', ', $ultima_chave_salva['user_roles'])); ?>
                    </div>
                </div>
            </div>
            <?php
            // Limpar última chave salva após exibição
            unset($_SESSION['ultima_chave_salva']);
        } else {
            error_log('DEBUG: Nenhuma chave salva encontrada');
        }
        ?>

        <form method="post" class="panel-wp-form">
            <?php wp_nonce_field('gerar_chave_api'); ?>
            
            <div class="form-group">
                <?php submit_button(
                    __('Gerar Nova Chave API', 'panel-wp-connector'), 
                    'panel-wp-button panel-wp-button-secondary', 
                    'gerar_chave'
                ); ?>
            </div>
        </form>

        <?php 
        // Revogar chave
        if (isset($_GET['revogar']) && wp_verify_nonce($_GET['_wpnonce'], 'revogar_chave_' . $_GET['revogar'])) {
            $chave_revogada = sanitize_text_field($_GET['revogar']);
            $auth = new PanelWPConnector\Authentication\PanelWPAuthentication();
            $resultado = $auth->revogar_chave_api($chave_revogada);
            if ($resultado) {
                add_settings_error('panel_wp_api_keys', 'chave_revogada', __('Chave de API revogada com sucesso!', 'panel-wp-connector'), 'success');
            }
        }

        // Listar chaves do usuário
        $auth = new PanelWPConnector\Authentication\PanelWPAuthentication();
        $current_user_id = get_current_user_id();
        $chaves = $auth->listar_chaves_usuario($current_user_id);

        // Fallback para chaves vazias
        if ($chaves === false) {
            $chaves = [];
            add_settings_error('panel_wp_connector_messages', 'erro_chaves', __('Não foi possível recuperar as chaves de API.', 'panel-wp-connector'), 'error');
        }

        ?>

        <?php if (empty($chaves)): ?>
            <p><?php _e('Você ainda não gerou nenhuma chave de API.', 'panel-wp-connector'); ?></p>
        <?php else: ?>
            <table class="panel-wp-table">
                <thead>
                    <tr>
                        <th><?php _e('Chave', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Criada em', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Última Utilização', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Ações', 'panel-wp-connector'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chaves as $chave): ?>
                    <tr>
                        <td>
                            <?php 
                            if (isset($chave['api_key'])) {
                                $chave_exibicao = strlen($chave['api_key']) > 10 
                                    ? substr($chave['api_key'], 0, 10) . '...' 
                                    : $chave['api_key'];
                                echo '<code>' . esc_html($chave_exibicao) . '</code>';
                            }
                            ?>
                        </td>
                        <td><?php 
                            echo isset($chave['created_at']) 
                                ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($chave['created_at']))
                                : __('Agora', 'panel-wp-connector'); 
                        ?></td>
                        <td>
                            <?php 
                            echo isset($chave['last_used']) && $chave['last_used']
                                ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($chave['last_used']))
                                : __('Nunca utilizada', 'panel-wp-connector'); 
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url(add_query_arg('revogar', $chave['api_key']), 'revogar_chave_' . $chave['api_key']); ?>" 
                               class="panel-wp-button panel-wp-button-danger" 
                               onclick="return confirm('<?php _e('Tem certeza que deseja revogar esta chave de API?', 'panel-wp-connector'); ?>')">
                                <?php _e('Revogar', 'panel-wp-connector'); ?>
                            </a>
                            <button type="button" 
                                    class="panel-wp-button panel-wp-button-secondary panel-wp-copiar-chave" 
                                    data-chave="<?php echo esc_attr($chave['api_key']); ?>">
                                <?php _e('Copiar', 'panel-wp-connector'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

    <div class="panel-wp-card">
        <h2><?php _e('Instruções', 'panel-wp-connector'); ?></h2>
        <p><?php _e('Chaves de API permitem que aplicações externas acessem recursos do seu site de forma segura.', 'panel-wp-connector'); ?></p>
        <ul>
            <li><?php _e('Cada chave é única e vinculada ao seu usuário.', 'panel-wp-connector'); ?></li>
            <li><?php _e('Você pode gerar quantas chaves precisar.', 'panel-wp-connector'); ?></li>
            <li><?php _e('Revogue imediatamente chaves comprometidas.', 'panel-wp-connector'); ?></li>
        </ul>
    </div>

    <div class="panel-wp-card">
        <h2>IPs e URLs Autorizados</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="panel-wp-form">
            <?php 
            // Adicionar campos de segurança do WordPress
            wp_nonce_field('panel_wp_authorized_ips_action', '_wpnonce', true, true);
            ?>
            <input type="hidden" name="action" value="salvar_ips_autorizados">
            
            <div class="form-group">
                <label for="ips_autorizados">
                    <strong>IPs Autorizados</strong> 
                    <small>(um por linha)</small>
                </label>
                <textarea 
                    id="ips_autorizados" 
                    name="panel_wp_authorized_ips[ips]" 
                    rows="5" 
                    class="widefat"
                ><?php 
                    echo !empty($ips_autorizados['ips']) 
                        ? esc_textarea(implode("\n", $ips_autorizados['ips'])) 
                        : ''; 
                ?></textarea>
                <p class="description">
                    Exemplo: 192.168.1.1, 10.0.0.1, 127.0.0.1
                </p>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label for="urls_autorizadas">
                    <strong>URLs Autorizadas</strong> 
                    <small>(um por linha)</small>
                </label>
                <textarea 
                    id="urls_autorizadas" 
                    name="panel_wp_authorized_ips[urls]" 
                    rows="5" 
                    class="widefat"
                ><?php 
                    echo !empty($ips_autorizados['urls']) 
                        ? esc_textarea(implode("\n", $ips_autorizados['urls'])) 
                        : ''; 
                ?></textarea>
                <p class="description">
                    Exemplo: https://painel.exemplo.com, http://localhost
                </p>
            </div>

            <?php submit_button('Salvar IPs e URLs', 'panel-wp-button panel-wp-button-secondary'); ?>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const botoesCopiar = document.querySelectorAll('.panel-wp-copiar-chave');
    
    botoesCopiar.forEach(botao => {
        botao.addEventListener('click', function() {
            const chave = this.getAttribute('data-chave');
            
            // Criar um elemento temporário para copiar
            const tempInput = document.createElement('input');
            tempInput.value = chave;
            document.body.appendChild(tempInput);
            
            // Selecionar e copiar
            tempInput.select();
            document.execCommand('copy');
            
            // Remover elemento temporário
            document.body.removeChild(tempInput);
            
            // Feedback visual
            this.textContent = '<?php _e('Copiado!', 'panel-wp-connector'); ?>';
            this.disabled = true;
            
            // Restaurar depois de 2 segundos
            setTimeout(() => {
                this.textContent = '<?php _e('Copiar', 'panel-wp-connector'); ?>';
                this.disabled = false;
            }, 2000);
        });
    });
});
</script>
