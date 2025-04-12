<?php
namespace PanelWPConnector\Routes;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use PanelWPConnector\Authentication\PanelWPAuthentication;
use PanelWPConnector\Core\PanelWPCore;
use PanelWPConnector\Backup\PanelWPBackup;
use WP_REST_Server;

class PanelWPRestRoutes {
    private $authentication;
    private $core;

    public function __construct() {
        $this->authentication = new \PanelWPConnector\Authentication\PanelWPAuthentication();
        $this->core = new \PanelWPConnector\Core\PanelWPCore();

        add_action('rest_api_init', [$this, 'registrar_rotas']);
    }

    /**
     * Registra todas as rotas da API REST do Plugin
     */
    public function registrar_rotas() {
        // Verificar se a API REST está ativa
        if (!$this->verificar_rest_api()) {
            return;
        }

        // Adicionar headers CORS para todos os endpoints
        add_filter('rest_pre_serve_request', function($response) {
            if (defined('REST_REQUEST') && REST_REQUEST) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
                header('Access-Control-Allow-Headers: X-Api-Key, Content-Type');
                header('Access-Control-Allow-Credentials: true');
            }
            return $response;
        });

        try {
            // Rota de Autenticação
            register_rest_route('panel-wp/v1', '/authenticate', [
                'methods' => 'GET,POST',
                'callback' => [$this, 'autenticar_aplicativo'],
                'permission_callback' => '__return_true'
            ]);

            // Rota de Status do Site
            register_rest_route('panel-wp/v1', '/status', [
                'methods' => 'GET',
                'callback' => [$this, 'obter_status_site'],
                'permission_callback' => '__return_true'
            ]);

            // Rota para Execução de Tarefas
            register_rest_route('panel-wp/v1', '/execute-task', [
                'methods' => 'POST',
                'callback' => [$this, 'executar_tarefa'],
                'permission_callback' => [$this, 'validar_acesso']
            ]);

            // Rota para Informações do Sistema
            register_rest_route('panel-wp/v1', '/system-info', [
                'methods' => 'GET',
                'callback' => [$this, 'obter_informacoes_sistema'],
                'permission_callback' => [$this, 'validar_acesso']
            ]);

            // Rotas de Debug
            register_rest_route('panel-wp/v1', '/debug/status', [
                'methods' => 'GET',
                'callback' => [$this, 'obter_status_debug'],
                'permission_callback' => [$this, 'validar_acesso']
            ]);

            register_rest_route('panel-wp/v1', '/debug/toggle', [
                'methods' => 'POST',
                'callback' => [$this, 'alternar_debug'],
                'permission_callback' => [$this, 'validar_acesso']
            ]);

            register_rest_route('panel-wp/v1', '/debug/log', [
                'methods' => 'GET',
                'callback' => [$this, 'obter_log_debug'],
                'permission_callback' => [$this, 'validar_acesso']
            ]);

            register_rest_route('panel-wp/v1', '/debug/log/clear', [
                'methods' => 'POST',
                'callback' => [$this, 'limpar_log_debug'],
                'permission_callback' => [$this, 'validar_acesso']
            ]);

            // Rotas de Backup
            register_rest_route('panel-wp/v1', '/backup', [
                'methods'             => 'POST',
                'callback'            => [$this, 'iniciar_backup'],
                'permission_callback' => [$this, 'validar_acesso'],
                'args'                => [
                    'tipo' => [
                        'required'          => true,
                        'validate_callback' => function($param) {
                            return in_array($param, ['completo', 'banco_dados', 'arquivos']);
                        }
                    ]
                ]
            ]);

            // Rota de Status de Backup
            register_rest_route('panel-wp/v1', '/backup-status/(?P<backup_id>[a-zA-Z0-9_-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'status_backup'],
                'permission_callback' => [$this, 'validar_acesso'],
                'args' => [
                    'backup_id' => [
                        'validate_callback' => function($param) {
                            return !empty($param) && preg_match('/^[a-zA-Z0-9_-]+$/', $param);
                        }
                    ]
                ]
            ]);

            // Rota para processar backup (uso interno)
            register_rest_route('panel-wp/v1', '/process-backup/(?P<backup_id>[a-zA-Z0-9_-]+)', [
                'methods' => 'POST',
                'callback' => [$this, 'processar_backup'],
                'permission_callback' => '__return_true',
                'args' => [
                    'backup_id' => [
                        'validate_callback' => function($param) {
                            return !empty($param) && preg_match('/^[a-zA-Z0-9_-]+$/', $param);
                        }
                    ]
                ]
            ]);

            // Rota de Download de Backup
            register_rest_route('panel-wp/v1', '/download-backup/(?P<backup_id>[a-zA-Z0-9_-]+)', [
                'methods'             => 'GET',
                'callback'            => [$this, 'download_backup'],
                'permission_callback' => [$this, 'validar_acesso'],
                'args'                => [
                    'backup_id' => [
                        'validate_callback' => function($param) {
                            return !empty($param) && preg_match('/^[a-zA-Z0-9_-]+$/', $param);
                        }
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            error_log('PANEL WP: Erro ao registrar rotas - ' . $e->getMessage());
        }
    }

    /**
     * Verifica se o usuário tem acesso à rota
     * Permite acesso via chave API ou se for administrador logado
     * @param WP_REST_Request $request Requisição da API
     * @return bool Se o usuário tem acesso
     */
    public function validar_acesso($request) {
        // Verificar se é um administrador logado
        if (current_user_can('manage_options')) {
            return true;
        }

        // Verificar chave API (no cabeçalho ou como parâmetro)
        $api_key = $request->get_header('X-API-KEY') ?? $request->get_param('api_key');
        if (!$api_key) {
            return false;
        }

        return $this->authentication->validar_chave_api($api_key) !== false;
    }

    /**
     * Verifica se a API REST está ativa
     * @return bool
     */
    private function verificar_rest_api() {
        $rest_available = apply_filters('rest_enabled', true);
        $rest_url = get_rest_url();
        return $rest_available && !empty($rest_url);
    }

    /**
     * Valida a chave de API para rotas protegidas
     *
     * @param WP_REST_Request $request Requisição da API
     * @return bool Indica se a chave de API é válida
     */
    public function validar_chave_api(WP_REST_Request $request) {
        $api_key = $request->get_header('X-API-KEY') ?? $request->get_param('api_key');

        if (!$api_key) {
            return new WP_Error(
                'sem_chave_api',
                __('Chave de API não fornecida.', 'panel-wp-connector'),
                ['status' => 401]
            );
        }

        $user_id = $this->authentication->validar_chave_api($api_key);

        return $user_id !== false;
    }

    /**
     * Autentica um novo aplicativo
     *
     * @param WP_REST_Request $request Requisição da API
     * @return WP_REST_Response|void Resposta da autenticação ou redirecionamento
     */
    public function autenticar_aplicativo(WP_REST_Request $request) {
        // Obter a chave de API do cabeçalho ou do corpo da requisição
        $api_key = $request->get_header('X-Api-Key') ?? $request->get_param('api_key');

        if (!$api_key) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => __('Chave de API não fornecida.', 'panel-wp-connector')
            ], 401);
        }

        // Validar a chave de API
        $user_id = $this->authentication->validar_chave_api($api_key);

        if ($user_id === false) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => __('Chave de API inválida.', 'panel-wp-connector')
            ], 401);
        }

        // Obter informações do usuário
        $user = get_userdata($user_id);

        // Verificar se é uma solicitação de auto-login
        $auto_login = $request->get_param('auto_login');

        if ($auto_login) {
            // Verificar se o usuário tem permissões adequadas
            if (!user_can($user_id, 'manage_options')) {
                return new WP_REST_Response([
                    'status' => 'error',
                    'message' => __('Usuário sem permissões adequadas para auto-login.', 'panel-wp-connector')
                ], 403);
            }

            // Criar cookie de autenticação
            wp_set_auth_cookie($user_id, true);

            // Registrar log de login automático
            error_log(sprintf('PANEL WP: Login automático realizado para o usuário ID %d via API', $user_id));

            // Obter URL de redirecionamento (padrão para o painel admin)
            $redirect_url = $request->get_param('redirect_url') ?? admin_url();

            // Redirecionar para o painel administrativo
            wp_redirect($redirect_url);
            exit;
        }

        // Resposta padrão para autenticação sem auto-login
        return new WP_REST_Response([
            'status' => 'success',
            'message' => __('Autenticação realizada com sucesso.', 'panel-wp-connector'),
            'user' => [
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles
            ],
            'site' => [
                'name' => get_bloginfo('name'),
                'url' => get_site_url(),
                'description' => get_bloginfo('description'),
                'admin_email' => get_bloginfo('admin_email'),
                'language' => get_bloginfo('language'),
                'wordpress_version' => get_bloginfo('version'),
                'timezone' => wp_timezone_string()
            ]
        ], 200);
    }

    /**
     * Obtém o status básico do site
     *
     * @return WP_REST_Response Status do site
     */
    public function obter_status_site() {
        return new WP_REST_Response([
            'status' => 'online',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
            'timezone' => wp_timezone_string()
        ], 200);
    }

    /**
     * Executa uma tarefa específica
     *
     * @param WP_REST_Request $request Requisição da API
     * @return WP_REST_Response Resultado da tarefa
     */
    public function executar_tarefa(WP_REST_Request $request) {
        $task = $request->get_param('task');
        $params = $request->get_param('params') ?? [];

        if (!$task) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => __('Tarefa não especificada.', 'panel-wp-connector')
            ], 400);
        }

        // Executa a tarefa usando o núcleo do plugin
        $resultado = $this->core->executar_tarefa($task, $params);

        return new WP_REST_Response($resultado, $resultado['status'] === 'success' ? 200 : 400);
    }

    /**
     * Obtém informações detalhadas do sistema
     *
     * @return WP_REST_Response Informações do sistema
     */
    public function obter_informacoes_sistema() {
        $system = new \PanelWPConnector\System\PanelWPSystem();
        $informacoes = $system->coletar_informacoes_sistema();

        // Adicionar informações em formato simplificado para compatibilidade
        $informacoes['wordpress_version'] = $informacoes['wordpress']['versao'];
        $informacoes['php_version'] = $informacoes['php']['versao'];
        $informacoes['mysql_version'] = $informacoes['banco_dados']['versao'];
        $informacoes['site_url'] = $informacoes['wordpress']['url'];
        $informacoes['site_name'] = get_bloginfo('name');

        return new WP_REST_Response($informacoes, 200);
    }

    /**
     * Obtém o status atual do debug
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function obter_status_debug($request) {
        $debug = new \PanelWPConnector\Debug\PanelWPDebug();
        $status = $debug->get_debug_status();
        return rest_ensure_response($status);
    }

    /**
     * Alterna o status do debug
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function alternar_debug($request) {
        $debug = new \PanelWPConnector\Debug\PanelWPDebug();
        $params = $request->get_json_params();
        $ativar = isset($params['ativar']) ? (bool) $params['ativar'] : false;

        // Obter o ID do usuário pela chave API (no cabeçalho ou como parâmetro)
        $api_key = $request->get_header('X-API-KEY') ?? $request->get_param('api_key');
        $user_id = $this->authentication->validar_chave_api($api_key);

        $resultado = $debug->toggle_debug($ativar, $user_id);
        return rest_ensure_response($resultado);
    }

    /**
     * Obtém o conteúdo do arquivo de log
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function obter_log_debug($request) {
        $debug = new \PanelWPConnector\Debug\PanelWPDebug();

        // Obter o ID do usuário pela chave API (no cabeçalho ou como parâmetro)
        $api_key = $request->get_header('X-API-KEY') ?? $request->get_param('api_key');
        $user_id = $this->authentication->validar_chave_api($api_key);

        $conteudo = $debug->get_debug_log_content($user_id);
        return rest_ensure_response($conteudo);
    }

    /**
     * Limpa o arquivo de log
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function limpar_log_debug($request) {
        $debug = new \PanelWPConnector\Debug\PanelWPDebug();

        // Obter o ID do usuário pela chave API (no cabeçalho ou como parâmetro)
        $api_key = $request->get_header('X-API-KEY') ?? $request->get_param('api_key');
        $user_id = $this->authentication->validar_chave_api($api_key);

        $resultado = $debug->clear_debug_log($user_id);
        return rest_ensure_response($resultado);
    }

    /**
     * Processa um backup
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function processar_backup($request) {
        $backup_id = $request->get_param('backup_id');

        // Configurar para execução longa
        ignore_user_abort(true);
        set_time_limit(0);

        // Iniciar o processamento do backup
        $backup = new \PanelWPConnector\Backup\PanelWPBackup();
        $backup->processar_backup_cron($backup_id);

        // Retornar resposta imediatamente
        return rest_ensure_response(['success' => true, 'message' => 'Processamento iniciado']);
    }

    /**
     * Inicia o processo de backup
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function iniciar_backup($request) {
        $params = $request->get_params();

        if (!isset($params['tipo'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Parâmetro "tipo" é obrigatório'
            ], 400);
        }

        $backup = new PanelWPBackup();
        $resultado = $backup->iniciar_backup($params['tipo']);

        return rest_ensure_response($resultado);
    }

    /**
     * Download de backup
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function download_backup($request) {
        $timestamp = $request->get_param('timestamp');

        if (!is_numeric($timestamp) || strlen($timestamp) !== 10 || $timestamp > time()) {
            error_log("Timestamp inválido recebido: $timestamp");
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Timestamp inválido ou futuro'
            ], 400);
        }

        try {
            $backup = new PanelWPBackup();

            if (!$backup->verificar_existencia_backup($timestamp)) {
                return new WP_REST_Response([
                    'status' => 'error',
                    'message' => 'Backup não encontrado'
                ], 404);
            }

            return $backup->download_backup($timestamp);

        } catch (Exception $e) {
            error_log("Erro no download do backup: " . $e->getMessage());
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Erro interno no servidor'
            ], 500);
        }
    }

    /**
     * Obtém o status do backup
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function status_backup($request) {
        $backup = new PanelWPBackup();
        $backup_id = $request->get_param('backup_id');
        $resultado = $backup->status_backup($backup_id);
        return rest_ensure_response($resultado);
    }

    /**
     * Lista arquivos em um diretório específico
     *
     * @param WP_REST_Request $request Objeto de requisição
     * @return WP_REST_Response Resposta com lista de arquivos
     */
    public function listar_arquivos(WP_REST_Request $request) {
        try {
            // Usar ABSPATH como caminho base se nenhum diretório for especificado
            $caminho_base = $request->get_param('diretorio')
                ? realpath($request->get_param('diretorio'))
                : ABSPATH;

            // Validações de segurança adicionais
            if (!$caminho_base || strpos($caminho_base, ABSPATH) !== 0) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Diretório inválido'
                ], 403);
            }

            // Listar arquivos e pastas
            $arquivos = array_diff(scandir($caminho_base), ['.', '..']);
            $resultado = [];

            foreach ($arquivos as $arquivo) {
                $caminho_item = $caminho_base . DIRECTORY_SEPARATOR . $arquivo;
                $resultado[] = [
                    'nome' => $arquivo,
                    'tipo' => is_dir($caminho_item) ? 'diretorio' : 'arquivo',
                    'tamanho' => is_file($caminho_item) ? filesize($caminho_item) : null,
                    'modificado_em' => date('Y-m-d H:i:s', filemtime($caminho_item))
                ];
            }

            return new WP_REST_Response([
                'success' => true,
                'arquivos' => $resultado
            ]);
        } catch (Exception $e) {
            error_log('PANEL WP: Erro ao listar arquivos - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao listar arquivos'
            ], 500);
        }
    }

    /**
     * Realiza upload de arquivo
     *
     * @param WP_REST_Request $request Objeto de requisição
     * @return WP_REST_Response Resposta do upload
     */
    public function upload_arquivo(WP_REST_Request $request) {
        try {
            $diretorio = $request->get_param('diretorio') ?? ABSPATH;
            $arquivo = $request->get_file_params()['arquivo'] ?? null;

            if (!$arquivo) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Nenhum arquivo enviado'
                ], 400);
            }

            // Validar e sanitizar caminho
            $caminho_real = realpath($diretorio);
            if (!$caminho_real || !is_dir($caminho_real)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Diretório inválido'
                ], 400);
            }

            $nome_arquivo = sanitize_file_name($arquivo['name']);
            $caminho_destino = $caminho_real . DIRECTORY_SEPARATOR . $nome_arquivo;

            // Mover arquivo
            if (move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Upload realizado com sucesso',
                    'arquivo' => $nome_arquivo
                ]);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao realizar upload'
            ], 500);
        } catch (Exception $e) {
            error_log('PANEL WP: Erro no upload de arquivo - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno no upload'
            ], 500);
        }
    }

    /**
     * Realiza download de arquivo
     *
     * @param WP_REST_Request $request Objeto de requisição
     * @return WP_REST_Response Resposta do download
     */
    public function download_arquivo(WP_REST_Request $request) {
        try {
            $caminho_arquivo = $request->get_param('arquivo');

            // Validar e sanitizar caminho
            $caminho_real = realpath($caminho_arquivo);
            if (!$caminho_real || !is_file($caminho_real)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Arquivo inválido'
                ], 400);
            }

            // Enviar arquivo para download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($caminho_real) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($caminho_real));
            readfile($caminho_real);
            exit;
        } catch (Exception $e) {
            error_log('PANEL WP: Erro no download de arquivo - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno no download'
            ], 500);
        }
    }

    /**
     * Exclui arquivo ou diretório
     *
     * @param WP_REST_Request $request Objeto de requisição
     * @return WP_REST_Response Resposta da exclusão
     */
    public function excluir_arquivo(WP_REST_Request $request) {
        try {
            $caminho = $request->get_param('caminho');

            // Validar e sanitizar caminho
            $caminho_real = realpath($caminho);
            if (!$caminho_real) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Caminho inválido'
                ], 400);
            }

            // Verificar se é arquivo ou diretório
            if (is_file($caminho_real)) {
                unlink($caminho_real);
            } elseif (is_dir($caminho_real)) {
                rmdir($caminho_real);
            } else {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Caminho não é um arquivo ou diretório válido'
                ], 400);
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Exclusão realizada com sucesso'
            ]);
        } catch (Exception $e) {
            error_log('PANEL WP: Erro na exclusão de arquivo - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno na exclusão'
            ], 500);
        }
    }

    /**
     * Renomeia arquivo ou diretório
     *
     * @param WP_REST_Request $request Objeto de requisição
     * @return WP_REST_Response Resposta da renomeação
     */
    public function renomear_arquivo(WP_REST_Request $request) {
        try {
            $caminho_atual = $request->get_param('caminho_atual');
            $novo_nome = $request->get_param('novo_nome');

            // Validar e sanitizar caminhos
            $caminho_real_atual = realpath($caminho_atual);
            $diretorio_pai = dirname($caminho_real_atual);
            $novo_caminho = $diretorio_pai . DIRECTORY_SEPARATOR . sanitize_file_name($novo_nome);

            if (!$caminho_real_atual) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Caminho atual inválido'
                ], 400);
            }

            // Renomear arquivo/diretório
            if (rename($caminho_real_atual, $novo_caminho)) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Renomeação realizada com sucesso',
                    'novo_caminho' => $novo_caminho
                ]);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao renomear'
            ], 500);
        } catch (Exception $e) {
            error_log('PANEL WP: Erro na renomeação de arquivo - ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno na renomeação'
            ], 500);
        }
    }
}
// Inicializar as rotas
new PanelWPRestRoutes();
