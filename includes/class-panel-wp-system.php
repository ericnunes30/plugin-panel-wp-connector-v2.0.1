<?php
namespace PanelWPConnector\System;

/**
 * Classe responsável por coletar informações detalhadas do sistema
 * Fornece métodos para obter dados sobre WordPress, PHP, banco de dados e ambiente
 * @package PanelWPConnector\System
 * @since 0.3.0
 */
class PanelWPSystem {
    /**
     * Coleta informações abrangentes sobre o sistema
     * Reúne dados de WordPress, PHP, banco de dados e configurações do servidor
     * @return array Informações detalhadas do sistema
     */
    public function coletar_informacoes_sistema() {
        try {
            global $wpdb;

            $informacoes = [
                'wordpress' => $this->obter_informacoes_wordpress(),
                'php' => $this->obter_informacoes_php(),
                'banco_dados' => $this->obter_informacoes_banco_dados($wpdb),
                'plugins_ativos' => $this->obter_plugins_ativos(),
                'tema_atual' => wp_get_theme()->get('Name'),
                'informacoes_servidor' => $this->obter_informacoes_servidor()
            ];

            return $informacoes;
        } catch (\Exception $e) {
            error_log('PANEL WP: Erro ao coletar informações do sistema - ' . $e->getMessage());
            
            return [
                'erro' => true,
                'mensagem' => __('Falha ao coletar informações do sistema', 'panel-wp-connector')
            ];
        }
    }

    /**
     * Obtém informações básicas do WordPress
     * @return array Dados do WordPress
     */
    private function obter_informacoes_wordpress() {
        return [
            'versao' => get_bloginfo('version'),
            'url' => get_site_url(),
            'admin_email' => get_bloginfo('admin_email'),
            'linguagem' => get_bloginfo('language')
        ];
    }

    /**
     * Obtém informações sobre a configuração do PHP
     * @return array Configurações do PHP
     */
    private function obter_informacoes_php() {
        return [
            'versao' => PHP_VERSION,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Obtém informações sobre o banco de dados
     * @param \wpdb $wpdb Instância do banco de dados WordPress
     * @return array Dados do banco de dados
     */
    private function obter_informacoes_banco_dados($wpdb) {
        return [
            'tipo' => 'MySQL',
            'versao' => $wpdb->db_version(),
            'prefixo_tabelas' => $wpdb->prefix,
            'tamanho_total' => $this->calcular_tamanho_banco_dados()
        ];
    }

    /**
     * Obtém informações do servidor
     * @return array Dados do servidor
     */
    private function obter_informacoes_servidor() {
        return [
            'so' => php_uname('s') . ' ' . php_uname('r'),
            'arquitetura' => php_uname('m')
        ];
    }

    /**
     * Calcula o tamanho total do banco de dados
     * @return string Tamanho formatado do banco de dados
     */
    private function calcular_tamanho_banco_dados() {
        global $wpdb;
        $total_size = 0;
        
        try {
            $tables = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
            foreach ($tables as $table) {
                $total_size += $table['Data_length'] + $table['Index_length'];
            }
        } catch (\Exception $e) {
            error_log('PANEL WP: Erro ao calcular tamanho do banco de dados - ' . $e->getMessage());
            return __('Não foi possível calcular', 'panel-wp-connector');
        }

        return $this->formatar_tamanho_bytes($total_size);
    }

    /**
     * Formata o tamanho em bytes para uma unidade legível
     * @param int $bytes Tamanho em bytes
     * @return string Tamanho formatado
     */
    private function formatar_tamanho_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    /**
     * Obtém lista de plugins ativos com suas versões
     * @return array Lista de plugins ativos
     */
    private function obter_plugins_ativos() {
        // Carregar arquivo necessário para get_plugins()
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins_ativos = [];
        $plugins = \get_plugins();
        $plugins_ativos_raw = \get_option('active_plugins', []);

        foreach ($plugins_ativos_raw as $plugin_path) {
            if (isset($plugins[$plugin_path])) {
                $plugins_ativos[] = $plugins[$plugin_path]['Name'] . ' (v' . $plugins[$plugin_path]['Version'] . ')';
            }
        }

        return $plugins_ativos;
    }
}
