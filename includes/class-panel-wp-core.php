<?php
namespace PanelWPConnector\Core;

use PanelWPConnector\Admin\PanelWPAdmin;
use PanelWPConnector\System\PanelWPSystem;

/**
 * Classe principal do Plugin Panel WP Connector
 * Responsável por gerenciar dependências, hooks e endpoints da API
 * @package PanelWPConnector\Core
 * @since 0.3.0
 */
class PanelWPCore {
    /**
     * Instância da classe de administração
     * @var PanelWPAdmin
     */
    private $admin;

    /**
     * Instância da classe de informações do sistema
     * @var PanelWPSystem
     */
    private $system;

    /**
     * Construtor da classe
     * Inicializa dependências e define hooks do plugin
     */
    public function __construct() {
        try {
            $this->load_dependencies();
            $this->define_hooks();
        } catch (\Exception $e) {
            error_log('PANEL WP: Erro ao inicializar núcleo - ' . $e->getMessage());

            // Notificar admin sobre erro crítico
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>' .
                     sprintf(__('Erro crítico no Plugin Panel WP Connector: %s', 'panel-wp-connector'),
                             $e->getMessage()) .
                     '</p></div>';
            });
        }
    }

    /**
     * Carrega dependências necessárias para o plugin
     * Inclui e instancia classes essenciais
     * @throws \Exception Se falhar ao carregar dependências
     */
    private function load_dependencies() {
        $arquivos_dependencias = [
            'admin' => 'includes/class-panel-wp-admin.php',
            'system' => 'includes/class-panel-wp-system.php'
        ];

        foreach ($arquivos_dependencias as $tipo => $arquivo) {
            $caminho_completo = PANEL_WP_CONNECTOR_PATH . $arquivo;

            if (!file_exists($caminho_completo)) {
                throw new \Exception(sprintf('Arquivo de dependência não encontrado: %s', $arquivo));
            }

            require_once $caminho_completo;
        }

        $this->admin = PanelWPAdmin::get_instance();
        $this->system = new PanelWPSystem();
    }

    /**
     * Define hooks e ações do plugin
     * Registra pontos de interação com o WordPress
     */
    private function define_hooks() {
        // Hooks administrativos
        add_action('admin_init', [$this->admin, 'registrar_configuracoes']);

        // Registrar endpoints da API
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    /**
     * Registra endpoints personalizados da API REST
     * Adiciona rotas para status e informações do sistema
     */
    public function register_endpoints() {
        $endpoints = [
            '/status' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_site_status'],
                'permission_callback' => '__return_true'
            ],
            '/system-info' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_system_info'],
                'permission_callback' => '__return_true'
            ]
        ];

        foreach ($endpoints as $rota => $args) {
            register_rest_route('panel-wp/v1', $rota, $args);
        }
    }

    /**
     * Obtém status básico do site
     * @return \WP_REST_Response Resposta com informações do site
     */
    public function get_site_status() {
        // Carregar arquivo necessário para get_plugins()
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $status = [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'site_url' => get_site_url(),
            'admin_email' => get_bloginfo('admin_email'),
            'active_plugins' => array_keys(\get_plugins()),
            'status' => 'online'
        ];

        return rest_ensure_response($status);
    }

    /**
     * Obtém informações detalhadas do sistema
     * @return \WP_REST_Response Resposta com informações do sistema
     */
    public function get_system_info() {
        try {
            $informacoes = $this->system->coletar_informacoes_sistema();
            return rest_ensure_response($informacoes);
        } catch (\Exception $e) {
            error_log('PANEL WP: Erro ao coletar informações do sistema - ' . $e->getMessage());

            return rest_ensure_response([
                'error' => true,
                'message' => __('Falha ao coletar informações do sistema', 'panel-wp-connector')
            ]);
        }
    }

    /**
     * Método principal para execução do plugin
     * Pode ser usado para inicializações adicionais
     */
    public function run() {
        // Método para execuções adicionais, se necessário
        do_action('panel_wp_core_run');
    }
}
