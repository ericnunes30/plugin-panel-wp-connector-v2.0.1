<?php
namespace PanelWPConnector\Backup;

class PanelWPBackupLogger {
    private $backup_id;
    private $log_file;
    private $start_time;

    public function __construct($backup_id) {
        $this->backup_id = $backup_id;
        $this->start_time = microtime(true);
        $this->log_file = WP_CONTENT_DIR . '/panelWP-backups/logs/' . $backup_id . '.log';
        
        // Criar diretório de logs se não existir
        wp_mkdir_p(dirname($this->log_file));
        
        // Iniciar log
        $this->write_header();
    }

    private function write_header() {
        $info = [
            'Data/Hora Início' => current_time('mysql'),
            'WordPress Versão' => get_bloginfo('version'),
            'PHP Versão' => PHP_VERSION,
            'Sistema Operacional' => PHP_OS,
            'Servidor Web' => $_SERVER['SERVER_SOFTWARE'],
            'Memória Limite' => ini_get('memory_limit'),
            'Tempo Máximo Execução' => ini_get('max_execution_time') . 's',
            'Site URL' => get_site_url(),
            'Diretório WP' => ABSPATH,
            'ID do Backup' => $this->backup_id
        ];

        $header = "==========================================================\n";
        $header .= "           PANEL WP CONNECTOR - LOG DE BACKUP             \n";
        $header .= "==========================================================\n\n";
        
        foreach ($info as $key => $value) {
            $header .= sprintf("%-25s: %s\n", $key, $value);
        }
        
        $header .= "\n==========================================================\n\n";
        
        file_put_contents($this->log_file, $header);
    }

    public function log($message, $type = 'info', $context = []) {
        $time_elapsed = number_format(microtime(true) - $this->start_time, 2);
        $memory_usage = size_format(memory_get_usage(true));
        
        $log_entry = sprintf(
            "[%s] [%6s] [%8ss] [%10s] %s",
            current_time('mysql'),
            strtoupper($type),
            $time_elapsed,
            $memory_usage,
            $message
        );

        if (!empty($context)) {
            $log_entry .= "\n    Contexto: " . json_encode($context, JSON_PRETTY_PRINT);
        }

        $log_entry .= "\n";
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
        
        // Atualizar status no banco de dados
        $this->update_backup_status($message, $type);
    }

    private function update_backup_status($message, $type) {
        $backup_info = get_option("panelwp_backup_{$this->backup_id}");
        if ($backup_info) {
            $backup_info['status_atual'] = $message;
            $backup_info['ultimo_log'] = [
                'mensagem' => $message,
                'tipo' => $type,
                'timestamp' => current_time('mysql')
            ];
            update_option("panelwp_backup_{$this->backup_id}", $backup_info);
        }
    }

    public function get_log_content() {
        return file_exists($this->log_file) ? file_get_contents($this->log_file) : '';
    }

    public function log_error($message, $context = []) {
        $this->log($message, 'error', $context);
    }

    public function log_warning($message, $context = []) {
        $this->log($message, 'warn', $context);
    }

    public function log_success($message, $context = []) {
        $this->log($message, 'success', $context);
    }

    public function log_step_start($step) {
        $this->log("Iniciando: {$step}", 'step');
    }

    public function log_step_complete($step) {
        $this->log("Concluído: {$step}", 'success');
    }

    public function log_progress($step, $progress, $total = 100) {
        $percentage = round(($progress / $total) * 100);
        $this->log("{$step}: {$percentage}% completo", 'progress');
    }

    public function finalize($status = 'completed') {
        $time_total = number_format(microtime(true) - $this->start_time, 2);
        $final_memory = size_format(memory_get_usage(true));
        
        $summary = "\n==========================================================\n";
        $summary .= "                    RESUMO DO BACKUP                      \n";
        $summary .= "==========================================================\n\n";
        $summary .= sprintf("Status Final      : %s\n", strtoupper($status));
        $summary .= sprintf("Tempo Total       : %s segundos\n", $time_total);
        $summary .= sprintf("Memória Utilizada : %s\n", $final_memory);
        $summary .= sprintf("Data/Hora Fim     : %s\n", current_time('mysql'));
        $summary .= "\n==========================================================\n";
        
        file_put_contents($this->log_file, $summary, FILE_APPEND);
    }
}