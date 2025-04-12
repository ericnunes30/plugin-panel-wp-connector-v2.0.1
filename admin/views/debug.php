<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permissões
if (!current_user_can('manage_options')) {
    wp_die(__('Você não tem permissão para acessar esta página.', 'panel-wp-connector'));
}

// Instanciar classe de debug
$debug = new \PanelWPConnector\Debug\PanelWPDebug();

// Processar ações
if (isset($_POST['action'])) {
    check_admin_referer('panel_wp_debug_action');
    
    switch ($_POST['action']) {
        case 'toggle_debug':
            $ativar = isset($_POST['debug_status']) && $_POST['debug_status'] === 'on';
            $resultado = $debug->toggle_debug($ativar);
            if ($resultado['success']) {
                add_settings_error(
                    'panel_wp_debug',
                    'debug_updated',
                    $resultado['message'],
                    'success'
                );
            } else {
                add_settings_error(
                    'panel_wp_debug',
                    'debug_error',
                    $resultado['message'],
                    'error'
                );
            }
            break;

        case 'clear_log':
            $resultado = $debug->clear_debug_log();
            if ($resultado['success']) {
                add_settings_error(
                    'panel_wp_debug',
                    'log_cleared',
                    $resultado['message'],
                    'success'
                );
            } else {
                add_settings_error(
                    'panel_wp_debug',
                    'log_error',
                    $resultado['message'],
                    'error'
                );
            }
            break;
    }
}

// Obter conteúdo do log
$log_content = $debug->get_debug_log_content();
?>

<div class="wrap">
    <h1><?php _e('Gerenciamento de Debug', 'panel-wp-connector'); ?></h1>
    
    <?php settings_errors('panel_wp_debug'); ?>

    <div class="card">
        <h2><?php _e('Status do Debug', 'panel-wp-connector'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('panel_wp_debug_action'); ?>
            <input type="hidden" name="action" value="toggle_debug">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Debug Status', 'panel-wp-connector'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="debug_status" <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'checked' : ''; ?>>
                            <?php _e('Ativar Debug', 'panel-wp-connector'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Ativa/desativa o debug no WordPress. Isso irá modificar as configurações no wp-config.php', 'panel-wp-connector'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Salvar Alterações', 'panel-wp-connector')); ?>
        </form>
    </div>

    <div class="card">
        <h2><?php _e('Arquivo de Log', 'panel-wp-connector'); ?></h2>
        
        <form method="post" action="" style="margin-bottom: 20px;">
            <?php wp_nonce_field('panel_wp_debug_action'); ?>
            <input type="hidden" name="action" value="clear_log">
            <?php submit_button(__('Limpar Log', 'panel-wp-connector'), 'delete', 'submit', false); ?>
        </form>

        <div class="debug-log-content" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; max-height: 500px; overflow-y: auto;">
            <?php if ($log_content['success']): ?>
                <pre style="margin: 0; white-space: pre-wrap;"><?php echo esc_html($log_content['content']); ?></pre>
            <?php else: ?>
                <p class="error"><?php echo esc_html($log_content['message']); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
</style> 