<?php
namespace PanelWPConnector\Migrations;

class PanelWPMigrations {
    private $wpdb;
    private $charset_collate;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $this->wpdb->get_charset_collate();
    }

    /**
     * Cria tabelas necessárias para o plugin
     */
    public function criar_tabelas() {
        $tabelas = [
            "CREATE TABLE IF NOT EXISTS `{$this->wpdb->prefix}panel_wp_api_keys` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `api_key` VARCHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `last_used` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `api_key` (`api_key`),
                KEY `user_id` (`user_id`)
            ) {$this->charset_collate};"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tabelas as $tabela) {
            dbDelta($tabela);
        }
    }

    /**
     * Remove tabelas do plugin
     */
    public function remover_tabelas() {
        $tabelas = [
            "{$this->wpdb->prefix}panel_wp_api_keys"
        ];

        foreach ($tabelas as $tabela) {
            $this->wpdb->query("DROP TABLE IF EXISTS $tabela");
        }
    }

    /**
     * Executa migrações iniciais do plugin
     * 
     * @since 1.3.0
     * @return void
     */
    public function executar_migracoes_iniciais() {
        // Criar tabelas necessárias
        $this->criar_tabelas();

        // Definir opções padrão
        $this->definir_opcoes_iniciais();

        // Registrar log de migração
        error_log('PANEL WP: Migrações iniciais executadas com sucesso');
    }

    /**
     * Define opções iniciais do plugin
     * 
     * @since 1.3.0
     * @return void
     */
    private function definir_opcoes_iniciais() {
        // Definir opções padrão se não existirem
        $opcoes_padrao = [
            'versao' => '1.3.0',
            'primeira_instalacao' => current_time('mysql'),
            'debug_mode' => false,
            'log_level' => 'info'
        ];

        // Adicionar opções se não existirem
        foreach ($opcoes_padrao as $chave => $valor) {
            if (false === get_option("panel_wp_connector_{$chave}")) {
                add_option("panel_wp_connector_{$chave}", $valor);
            }
        }
    }
}
