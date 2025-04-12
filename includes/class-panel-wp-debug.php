<?php
namespace PanelWPConnector\Debug;

/**
 * Classe responsável por gerenciar as funcionalidades de debug
 * @package PanelWPConnector\Debug
 * @since 1.3.0
 */
class PanelWPDebug {
    /**
     * Caminho do arquivo wp-config.php
     * @var string
     */
    private $wp_config_path;

    /**
     * Caminho do arquivo debug.log
     * @var string
     */
    private $debug_log_path;

    /**
     * Construtor
     */
    public function __construct() {
        $this->wp_config_path = ABSPATH . 'wp-config.php';
        $this->debug_log_path = WP_CONTENT_DIR . '/debug.log';
    }

    /**
     * Ativa ou desativa o debug no wp-config.php
     * @param bool $ativar Se true, ativa o debug. Se false, desativa.
     * @param int|null $user_id ID do usuário para verificar permissões
     * @return array Resultado da operação
     */
    public function toggle_debug($ativar = true, $user_id = null) {
        try {
            // Se não foi fornecido user_id, usa o usuário atual
            if ($user_id === null) {
                // Verificar permissões do usuário atual
                if (!current_user_can('manage_options')) {
                    throw new \Exception('Você não tem permissão para modificar as configurações de debug.');
                }
            } else {
                // Verificar permissões do usuário específico
                $user = get_user_by('id', $user_id);
                if (!$user || !user_can($user, 'manage_options')) {
                    throw new \Exception('Você não tem permissão para modificar as configurações de debug.');
                }
            }

            // Verificar se o arquivo wp-config.php é gravável
            if (!is_writable($this->wp_config_path)) {
                throw new \Exception('O arquivo wp-config.php não tem permissão de escrita.');
            }

            // Ler o conteúdo atual do wp-config.php
            $config_content = file_get_contents($this->wp_config_path);
            if ($config_content === false) {
                throw new \Exception('Não foi possível ler o arquivo wp-config.php');
            }

            // Preparar as constantes de debug
            $debug_constants = $this->get_debug_constants($ativar);

            // Procurar por definições existentes de WP_DEBUG
            $pattern = '/define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,.*?\);/';
            $pattern_log = '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,.*?\);/';
            $pattern_display = '/define\s*\(\s*[\'"]WP_DEBUG_DISPLAY[\'"]\s*,.*?\);/';

            // Verificar se as definições existem
            if (preg_match($pattern, $config_content)) {
                // Substituir definições existentes
                $config_content = preg_replace($pattern, "define('WP_DEBUG', " . ($ativar ? 'true' : 'false') . ");", $config_content);
                
                // Se WP_DEBUG_LOG existe, substituir
                if (preg_match($pattern_log, $config_content)) {
                    $config_content = preg_replace($pattern_log, "define('WP_DEBUG_LOG', " . ($ativar ? 'true' : 'false') . ");", $config_content);
                } else {
                    // Se não existe, adicionar após WP_DEBUG
                    $config_content = preg_replace($pattern, "define('WP_DEBUG', " . ($ativar ? 'true' : 'false') . ");\ndefine('WP_DEBUG_LOG', " . ($ativar ? 'true' : 'false') . ");", $config_content);
                }

                // Se WP_DEBUG_DISPLAY existe, substituir
                if (preg_match($pattern_display, $config_content)) {
                    $config_content = preg_replace($pattern_display, "define('WP_DEBUG_DISPLAY', false);", $config_content);
                } else {
                    // Se não existe, adicionar após WP_DEBUG_LOG
                    $config_content = preg_replace($pattern_log, "define('WP_DEBUG_LOG', " . ($ativar ? 'true' : 'false') . ");\ndefine('WP_DEBUG_DISPLAY', false);", $config_content);
                }
            } else {
                // Se WP_DEBUG não existe, adicionar após o primeiro define
                $pos = strpos($config_content, 'define');
                if ($pos === false) {
                    throw new \Exception('Não foi possível encontrar um ponto seguro para inserir as definições de debug');
                }

                $config_content = substr_replace(
                    $config_content,
                    "\n" . $debug_constants . "\n",
                    $pos,
                    0
                );
            }

            // Salvar as alterações
            if (file_put_contents($this->wp_config_path, $config_content) === false) {
                throw new \Exception('Não foi possível salvar as alterações no wp-config.php');
            }

            return [
                'success' => true,
                'message' => $ativar ? 'Debug ativado com sucesso' : 'Debug desativado com sucesso'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém o conteúdo do arquivo debug.log
     * @param int|null $user_id ID do usuário para verificar permissões
     * @return array Conteúdo do arquivo ou mensagem de erro
     */
    public function get_debug_log_content($user_id = null) {
        try {
            // Se não foi fornecido user_id, usa o usuário atual
            if ($user_id === null) {
                // Verificar permissões do usuário atual
                if (!current_user_can('manage_options')) {
                    throw new \Exception('Você não tem permissão para visualizar o arquivo de log.');
                }
            } else {
                // Verificar permissões do usuário específico
                $user = get_user_by('id', $user_id);
                if (!$user || !user_can($user, 'manage_options')) {
                    throw new \Exception('Você não tem permissão para visualizar o arquivo de log.');
                }
            }

            if (!file_exists($this->debug_log_path)) {
                return [
                    'success' => true,
                    'content' => 'O arquivo debug.log ainda não existe.'
                ];
            }

            $content = file_get_contents($this->debug_log_path);
            if ($content === false) {
                throw new \Exception('Não foi possível ler o arquivo debug.log');
            }

            return [
                'success' => true,
                'content' => $content
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Limpa o arquivo debug.log
     * @param int|null $user_id ID do usuário para verificar permissões
     * @return array Resultado da operação
     */
    public function clear_debug_log($user_id = null) {
        try {
            // Se não foi fornecido user_id, usa o usuário atual
            if ($user_id === null) {
                // Verificar permissões do usuário atual
                if (!current_user_can('manage_options')) {
                    throw new \Exception('Você não tem permissão para limpar o arquivo de log.');
                }
            } else {
                // Verificar permissões do usuário específico
                $user = get_user_by('id', $user_id);
                if (!$user || !user_can($user, 'manage_options')) {
                    throw new \Exception('Você não tem permissão para limpar o arquivo de log.');
                }
            }

            if (!file_exists($this->debug_log_path)) {
                return [
                    'success' => true,
                    'message' => 'O arquivo debug.log não existe.'
                ];
            }

            if (file_put_contents($this->debug_log_path, '') === false) {
                throw new \Exception('Não foi possível limpar o arquivo debug.log');
            }

            return [
                'success' => true,
                'message' => 'Arquivo debug.log limpo com sucesso'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Retorna as constantes de debug formatadas
     * @param bool $ativar Se true, retorna constantes ativadas
     * @return string Constantes formatadas
     */
    private function get_debug_constants($ativar) {
        $value = $ativar ? 'true' : 'false';
        return "define('WP_DEBUG', {$value});\n" .
               "define('WP_DEBUG_LOG', {$value});\n" .
               "define('WP_DEBUG_DISPLAY', false);";
    }

    /**
     * Obtém o status atual das configurações de debug
     * @return array Status das configurações de debug
     */
    public function get_debug_status() {
        return [
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'success' => true
        ];
    }
} 