<?php
namespace PanelWPConnector\Backup;

use PanelWPConnector\Authentication\PanelWPAuthentication;
use ZipArchive;
use Exception;

class PanelWPBackup {
    private $authentication;
    private $backup_dir;
    private $wp_content_dir;

    public function __construct() {
        $this->authentication = new PanelWPAuthentication();
        $this->wp_content_dir = WP_CONTENT_DIR;
        $this->backup_dir = $this->wp_content_dir . '/panelWP-backups';
        
        // Criar diretório de backups se não existir
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            
            // Criar arquivo .htaccess para permitir download direto
            $htaccess_content = "
<IfModule mod_mime.c>
    AddType application/octet-stream .zip
</IfModule>
<IfModule mod_headers.c>
    Header set Content-Disposition \"attachment\"
</IfModule>
";
            file_put_contents($this->backup_dir . '/.htaccess', $htaccess_content);
        }
        
        // Log de inicialização
        error_log('[PanelWPBackup] Plugin inicializado - backup completo do WordPress');
    }
    
    /**
     * Processa o download direto
     */
    public function process_direct_download() {
        // Verificação muito simples - apenas para o teste
        if (isset($_GET['panelwp_download'])) {
            error_log("[PanelWPBackup] Tentativa de download detectada: " . $_GET['panelwp_download']);
            
            $backup_id = sanitize_text_field($_GET['panelwp_download']);
            $backup_info = get_option("panelwp_backup_{$backup_id}");
            
            if (!$backup_info || !isset($backup_info['arquivo_final'])) {
                error_log("[PanelWPBackup] Erro: Informações do backup não encontradas");
                echo "Erro: Backup não encontrado";
                exit;
            }
            
            $arquivo = $backup_info['arquivo_final'];
            
            if (!file_exists($arquivo)) {
                error_log("[PanelWPBackup] Erro: Arquivo não encontrado: {$arquivo}");
                echo "Erro: Arquivo não encontrado";
                exit;
            }
            
            $tamanho = filesize($arquivo);
            
            error_log("[PanelWPBackup] Iniciando download usando método direto");
            error_log("[PanelWPBackup] Arquivo: {$arquivo}, Tamanho: {$tamanho}");
            
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"" . basename($arquivo) . "\"");
            header("Content-Length: {$tamanho}");
            
            readfile($arquivo);
            exit;
        }
    }
    
    /**
     * Gera um link de download para um backup específico
     */
    public function gerar_link_download($backup_id) {
        $backup_info = get_option("panelwp_backup_{$backup_id}");
        
        if (!$backup_info || !isset($backup_info['arquivo_final'])) {
            return "Erro: Backup não encontrado";
        }
        
        if (!file_exists($backup_info['arquivo_final'])) {
            return "Erro: Arquivo de backup não encontrado";
        }
        
        // URL simples sem token para teste
        $url = home_url("/?panelwp_download={$backup_id}");
        error_log("[PanelWPBackup] URL de download gerada: {$url}");
        
        return $url;
    }

    /**
     * Lista todos os backups disponíveis
     * 
     * @return array Lista de backups com seus status
     */
    public function listar_backups() {
        $lista_backups = get_option('panelwp_lista_backups', []);
        $backups = [];

        foreach ($lista_backups as $backup_id) {
            $backup_info = get_option("panelwp_backup_{$backup_id}");
            if ($backup_info) {
                // Verifica se o arquivo existe (para backups concluídos)
                if ($backup_info['status'] === 'concluido' && 
                    (!isset($backup_info['arquivo_final']) || !file_exists($backup_info['arquivo_final']))) {
                    $backup_info['status'] = 'arquivo_ausente';
                    $backup_info['mensagem'] = 'Arquivo de backup não encontrado';
                }

                $backups[] = [
                    'id' => $backup_id,
                    'status' => $backup_info['status'],
                    'progresso' => $backup_info['progresso'] ?? 0,
                    'mensagem' => $backup_info['mensagem'] ?? '',
                    'data_inicio' => $backup_info['data_inicio'] ?? '',
                    'data_conclusao' => $backup_info['data_conclusao'] ?? '',
                    'tamanho' => $backup_info['tamanho'] ?? 0,
                    'download_url' => $backup_info['status'] === 'concluido' ? $this->gerar_link_download($backup_id) : null
                ];
            }
        }

        // Ordenar por data de início (mais recente primeiro)
        usort($backups, function($a, $b) {
            return strtotime($b['data_inicio']) - strtotime($a['data_inicio']);
        });

        return $backups;
    }

    /**
     * Inicia o processo de backup
     * 
     * @param WP_REST_Request $request Objeto de requisição REST
     * @return WP_REST_Response|WP_Error Resposta da API
     */
    public function iniciar_backup($request) {
        try {
            // Gerar um ID único para o backup
            $backup_id = uniqid('panelwp_backup_');
            
            // Registrar início do backup
            $backup_info = [
                'id' => $backup_id,
                'status' => 'iniciando',
                'progresso' => 0,
                'data_inicio' => current_time('mysql'),
                'mensagem' => 'Iniciando backup completo do WordPress'
            ];
            
            update_option("panelwp_backup_{$backup_id}", $backup_info);
            
            // Adicionar à lista de backups
            $lista_backups = get_option('panelwp_lista_backups', []);
            $lista_backups[] = $backup_id;
            update_option('panelwp_lista_backups', array_unique($lista_backups));
            
            // Definir o caminho do arquivo de backup
            $backup_file = $this->backup_dir . '/backup_' . $backup_id . '.zip';
            
            // Atualizar status
            $backup_info['status'] = 'em_andamento';
            $backup_info['mensagem'] = 'Criando arquivo de backup';
            update_option("panelwp_backup_{$backup_id}", $backup_info);
            
            // Criar um novo arquivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception('Não foi possível criar o arquivo de backup');
            }

            // Diretório raiz do WordPress
            $wp_root = untrailingslashit(ABSPATH);
            
            // Fazer backup de tudo
            $this->adicionar_diretorio_ao_zip($zip, $wp_root, $backup_id);
            
            $zip->close();
            
            // Atualizar informações finais do backup
            $backup_info['status'] = 'concluido';
            $backup_info['arquivo_final'] = $backup_file;
            $backup_info['data_conclusao'] = current_time('mysql');
            $backup_info['tamanho'] = filesize($backup_file);
            $backup_info['progresso'] = 100;
            $backup_info['mensagem'] = 'Backup concluído com sucesso';
            
            update_option("panelwp_backup_{$backup_id}", $backup_info);
            
            error_log("[PanelWPBackup] Backup criado com sucesso: {$backup_id}");
            
            // Gerar link de download
            $download_url = $this->gerar_link_download($backup_id);
            
            return rest_ensure_response([
                'success' => true,
                'backup_id' => $backup_id,
                'status' => $backup_info['status'],
                'mensagem' => $backup_info['mensagem'],
                'tamanho' => $backup_info['tamanho'],
                'download_url' => $download_url
            ]);
            
        } catch (Exception $e) {
            error_log("[PanelWPBackup] Erro ao criar backup: " . $e->getMessage());
            
            if (isset($backup_id)) {
                $backup_info['status'] = 'erro';
                $backup_info['mensagem'] = 'Erro: ' . $e->getMessage();
                update_option("panelwp_backup_{$backup_id}", $backup_info);
            }
            
            return new \WP_Error(
                'backup_error', 
                'Não foi possível criar o backup: ' . $e->getMessage(), 
                ['status' => 500]
            );
        }
    }

    /**
     * Verifica o status de um backup específico
     * 
     * @param string $backup_id ID do backup
     * @return array Informações do status do backup
     */
    public function status_backup($backup_id) {
        // Busca as informações do backup
        $backup_info = get_option("panelwp_backup_{$backup_id}");
        
        if (!$backup_info) {
            error_log("[PanelWPBackup] Backup não encontrado: {$backup_id}");
            return [
                'success' => false,
                'message' => 'Backup não encontrado',
                'status' => 'nao_encontrado'
            ];
        }
        
        $response = [
            'success' => true,
            'backup_id' => $backup_id,
            'status' => $backup_info['status'],
            'progresso' => $backup_info['progresso'] ?? 0,
            'mensagem' => $backup_info['mensagem'] ?? '',
            'data_inicio' => $backup_info['data_inicio'] ?? '',
            'data_conclusao' => $backup_info['data_conclusao'] ?? ''
        ];
        
        // Adicionar informações extras se o backup estiver concluído
        if ($backup_info['status'] === 'concluido') {
            if (!isset($backup_info['arquivo_final']) || !file_exists($backup_info['arquivo_final'])) {
                $response['status'] = 'arquivo_ausente';
                $response['mensagem'] = 'Arquivo de backup não encontrado';
            } else {
                $response['tamanho'] = $backup_info['tamanho'] ?? filesize($backup_info['arquivo_final']);
                $response['download_url'] = $this->gerar_link_download($backup_id);
            }
        }
        
        error_log("[PanelWPBackup] Status do backup {$backup_id} verificado: " . $backup_info['status']);
        
        return $response;
    }

    /**
     * Atualiza o progresso de um backup em andamento
     * 
     * @param string $backup_id ID do backup
     * @param int $progresso Porcentagem de progresso (0-100)
     * @param string $mensagem Mensagem de status atual
     */
    private function atualizar_progresso($backup_id, $progresso, $mensagem) {
        $backup_info = get_option("panelwp_backup_{$backup_id}");
        if ($backup_info) {
            $backup_info['progresso'] = $progresso;
            $backup_info['mensagem'] = $mensagem;
            update_option("panelwp_backup_{$backup_id}", $backup_info);
            error_log("[PanelWPBackup] Progresso atualizado: {$backup_id} - {$progresso}% - {$mensagem}");
        }
    }

    /**
     * Adiciona um diretório e todo seu conteúdo ao ZIP
     */
    private function adicionar_diretorio_ao_zip($zip, $source_dir, $backup_id) {
        $source_dir = str_replace('\\', '/', realpath($source_dir));
        $base_dir = basename($source_dir);

        // Iterator para listar todos os arquivos e diretórios
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source_dir),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        // Contar total de itens para o progresso
        $total_items = iterator_count($iterator);
        $iterator->rewind();
        $processed = 0;

        foreach ($iterator as $item) {
            $processed++;
            
            // Atualizar progresso
            if ($processed % 10 == 0) {
                $this->atualizar_progresso(
                    $backup_id,
                    round(($processed / $total_items) * 100),
                    "Processando item {$processed} de {$total_items}"
                );
            }

            // Converter para o formato correto de path
            $item_path = str_replace('\\', '/', $item->getRealPath());
            
            // Pular diretórios especiais
            if ($item->getBasename() === '.' || $item->getBasename() === '..') {
                continue;
            }

            // Pular o diretório de backups
            if (strpos($item_path, 'panelWP-backups') !== false) {
                continue;
            }

            // Calcular o caminho relativo dentro do ZIP
            $relative_path = substr($item_path, strlen($source_dir) + 1);
            if (empty($relative_path)) continue;

            if ($item->isDir()) {
                // Adicionar diretório
                $zip->addEmptyDir($relative_path);
            } else {
                // Adicionar arquivo
                $zip->addFile($item_path, $relative_path);
            }
        }
    }
}