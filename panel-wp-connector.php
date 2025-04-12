<?php
/**
 * Plugin Name: Panel WP Connector
 * Plugin URI: https://github.com/ericnunes30/plugin-panel-wp-connector-v2.0.1
 * Description: Conecta o site WordPress ao Panel WP - Gerenciador Multi-site WordPress
 * Version: 2.0.1
 * Author: Eric Nunes
 * Author URL: https://github.com/ericnunes30
 * Text Domain: panel-wp-connector
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package PanelWPConnector
 */

// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Iniciar sessão o mais cedo possível
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes do plugin
define('PANEL_WP_CONNECTOR_VERSION', '1.3.0');
define('PANEL_WP_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('PANEL_WP_CONNECTOR_URL', plugin_dir_url(__FILE__));
define('PANEL_WP_CONNECTOR_BASENAME', plugin_basename(__FILE__));

// Carregar classes necessárias manualmente
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-core.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-admin.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-system.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-migrations.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-authentication.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-rest-routes.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-debug.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-backup.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-backup-logger.php';
require_once PANEL_WP_CONNECTOR_PATH . 'includes/class-panel-wp-autoloader.php';
PanelWPConnector\PanelWPAutoloader::register();

// Carregar classes automaticamente
spl_autoload_register(function($class) {
    $prefix = 'PanelWPConnector\\';
    $base_dir = PANEL_WP_CONNECTOR_PATH . 'includes/';

    // Verificar se a classe pertence ao namespace do plugin
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Converter namespace para caminho de arquivo
    $relative_class = substr($class, $len);

    // Tentar múltiplos formatos de nome de arquivo
    $possible_files = [
        $base_dir . 'class-' . strtolower(str_replace(['_', '\\'], ['-', '/'], $relative_class)) . '.php',
        $base_dir . strtolower(str_replace(['_', '\\'], ['-', '/'], $relative_class)) . '.php',
        $base_dir . str_replace('\\', '/', $relative_class) . '.php'
    ];

    // Incluir arquivo se existir
    foreach ($possible_files as $file) {
        if (file_exists($file)) {
            require_once $file;
            // Classe carregada com sucesso
            return;
        }
    }

    // Classe não encontrada - sem necessidade de log
});

/**
 * Ativa o plugin e realiza verificações iniciais
 * @since 1.3.0
 * @return void
 * @throws Exception Se requisitos mínimos não forem atendidos
 */
function panel_wp_connector_activate() {
    try {
        // Verificar compatibilidade
        if (!current_user_can('activate_plugins')) {
            throw new Exception(__('Você não tem permissão para ativar este plugin.', 'panel-wp-connector'));
        }

        // Verificar versões mínimas com tratamento de erro
        $php_version = '7.4';
        $wp_version = '5.8';

        if (version_compare(PHP_VERSION, $php_version, '<')) {
            throw new Exception(
                sprintf(
                    __('Panel WP Connector requer PHP %s ou superior. Sua versão atual é %s.', 'panel-wp-connector'),
                    $php_version,
                    PHP_VERSION
                )
            );
        }

        if (version_compare(get_bloginfo('version'), $wp_version, '<')) {
            throw new Exception(
                sprintf(
                    __('Panel WP Connector requer WordPress %s ou superior. Sua versão atual é %s.', 'panel-wp-connector'),
                    $wp_version,
                    get_bloginfo('version')
                )
            );
        }

        // Realizar migrações e configurações iniciais
        $migrations = new PanelWPConnector\Migrations\PanelWPMigrations();
        $migrations->executar_migracoes_iniciais();

    } catch (Exception $e) {
        // Desativar plugin em caso de erro
        deactivate_plugins(plugin_basename(__FILE__));

        // Notificar admin sobre erro
        wp_die(
            $e->getMessage(),
            __('Erro de Ativação', 'panel-wp-connector'),
            ['response' => 403]
        );
    }
}
register_activation_hook(__FILE__, 'panel_wp_connector_activate');

/**
 * Desativa o plugin e realiza limpezas necessárias
 * @since 1.3.0
 * @return void
 */
function panel_wp_connector_deactivate() {
    try {
        // Limpar configurações e logs, se necessário
        delete_option('panel_wp_connector_options');

        // Log de desativação
        error_log('PANEL WP: Plugin desativado');
    } catch (Exception $e) {
        error_log('PANEL WP: Erro na desativação - ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, 'panel_wp_connector_deactivate');

/**
 * Inicializa o plugin, carregando dependências e configurações
 * @since 1.3.0
 * @return void
 */
function panel_wp_connector_init() {
    try {
        // Carregar traduções
        load_plugin_textdomain('panel-wp-connector', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        // Inicializar núcleo do plugin
        $core = new PanelWPConnector\Core\PanelWPCore();
        $core->run();
    } catch (Exception $e) {
        error_log('PANEL WP: Erro na inicialização - ' . $e->getMessage());
    }
}
add_action('plugins_loaded', 'panel_wp_connector_init');

/**
 * Verifica atualizações do plugin
 * @since 1.3.0
 * @return void
 */
function panel_wp_connector_check_updates() {
    try {
        // Lógica de verificação de atualizações
        // Pode ser expandida para usar API do GitHub ou WordPress.org
        do_action('panel_wp_check_updates');
    } catch (Exception $e) {
        error_log('PANEL WP: Erro na verificação de atualizações - ' . $e->getMessage());
    }
}
add_action('wp_update_plugins', 'panel_wp_connector_check_updates');

/**
 * Configura atualizações automáticas para o plugin
 * @since 1.3.0
 * @param bool $update Flag de atualização
 * @param object $item Informações do plugin
 * @return bool
 */
function panel_wp_connector_auto_update($update, $item) {
    try {
        // Verificar se o plugin é o Panel WP Connector
        if (isset($item->plugin) && $item->plugin === PANEL_WP_CONNECTOR_BASENAME) {
            // Permitir atualização automática baseada em configurações
            $opcoes = get_option('panel_wp_connector_options', ['auto_update' => false]);
            return $opcoes['auto_update'] ?? false;
        }
        return $update;
    } catch (Exception $e) {
        error_log('PANEL WP: Erro na configuração de atualização automática - ' . $e->getMessage());
        return false;
    }
}
add_filter('auto_update_plugin', 'panel_wp_connector_auto_update', 10, 2);

/**
 * Registra log de atualizações automáticas
 * @since 1.3.0
 * @param array $update_results Resultados da atualização
 * @return void
 */
function panel_wp_connector_log_auto_updates($update_results) {
    try {
        if (!empty($update_results['plugin'])) {
            foreach ($update_results['plugin'] as $resultado) {
                error_log('PANEL WP: Atualização ' . ($resultado->result ? 'bem-sucedida' : 'falhou') .
                          ' para plugin ' . $resultado->plugin);
            }
        }
    } catch (Exception $e) {
        error_log('PANEL WP: Erro no registro de log de atualizações - ' . $e->getMessage());
    }
}
add_action('automatic_updates_complete', 'panel_wp_connector_log_auto_updates');

/**
 * Adiciona link de configurações na página de plugins
 * @since 1.3.0
 * @param array $links Links de ação do plugin
 * @return array
 */
function panel_wp_connector_settings_link($links) {
    try {
        $url_configuracoes = admin_url('admin.php?page=panel-wp-connector');
        $links_configuracao = [
            'configuracoes' => '<a href="' . $url_configuracoes . '">' .
                               __('Configurações', 'panel-wp-connector') . '</a>'
        ];
        return array_merge($links_configuracao, $links);
    } catch (Exception $e) {
        error_log('PANEL WP: Erro ao adicionar link de configurações - ' . $e->getMessage());
        return $links;
    }
}
add_filter('plugin_action_links_' . PANEL_WP_CONNECTOR_BASENAME, 'panel_wp_connector_settings_link');