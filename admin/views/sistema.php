<?php
if (!defined('ABSPATH')) {
    exit;
}

use PanelWPConnector\System\PanelWPSystem;

$sistema = new PanelWPSystem();
$informacoes_sistema = $sistema->coletar_informacoes_sistema();

// Função para determinar status
function determinar_status($valor, $tipo = 'ok') {
    $status = 'ok';
    
    switch ($tipo) {
        case 'memoria':
            $memoria_atual = intval(str_replace(['M', 'G'], ['', ''], $valor));
            $status = $memoria_atual < 128 ? 'warning' : 'ok';
            break;
        case 'php_version':
            $versao_atual = floatval($valor);
            $status = $versao_atual < 7.4 ? 'warning' : 'ok';
            break;
        case 'wp_version':
            $versao_atual = floatval($valor);
            $status = $versao_atual < 5.8 ? 'warning' : 'ok';
            break;
    }
    
    return $status;
}
?>

<div class="wrap panel-wp-wrapper">
    <div class="panel-wp-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    </div>

    <div class="panel-wp-system-info">
        <div class="panel-wp-info-card">
            <h3>WordPress</h3>
            <ul class="panel-wp-info-list">
                <li>
                    <span class="info-label">Versão</span>
                    <span class="info-value status <?php echo determinar_status($informacoes_sistema['wordpress']['versao'], 'wp_version'); ?>">
                        <?php echo esc_html($informacoes_sistema['wordpress']['versao']); ?>
                    </span>
                </li>
                <li>
                    <span class="info-label">URL do Site</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['wordpress']['url']); ?></span>
                </li>
                <li>
                    <span class="info-label">Email Admin</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['wordpress']['admin_email']); ?></span>
                </li>
                <li>
                    <span class="info-label">Linguagem</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['wordpress']['linguagem']); ?></span>
                </li>
                <li>
                    <span class="info-label">Tema Atual</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['tema_atual']); ?></span>
                </li>
            </ul>
        </div>

        <div class="panel-wp-info-card">
            <h3>PHP</h3>
            <ul class="panel-wp-info-list">
                <li>
                    <span class="info-label">Versão</span>
                    <span class="info-value status <?php echo determinar_status($informacoes_sistema['php']['versao'], 'php_version'); ?>">
                        <?php echo esc_html($informacoes_sistema['php']['versao']); ?>
                    </span>
                </li>
                <li>
                    <span class="info-label">Tempo Máximo de Execução</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['php']['max_execution_time']); ?> segundos</span>
                </li>
                <li>
                    <span class="info-label">Limite de Memória</span>
                    <span class="info-value status <?php echo determinar_status($informacoes_sistema['php']['memory_limit'], 'memoria'); ?>">
                        <?php echo esc_html($informacoes_sistema['php']['memory_limit']); ?>
                    </span>
                </li>
            </ul>
        </div>

        <div class="panel-wp-info-card">
            <h3>Banco de Dados</h3>
            <ul class="panel-wp-info-list">
                <li>
                    <span class="info-label">Tipo</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['banco_dados']['tipo']); ?></span>
                </li>
                <li>
                    <span class="info-label">Versão</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['banco_dados']['versao']); ?></span>
                </li>
                <li>
                    <span class="info-label">Prefixo de Tabelas</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['banco_dados']['prefixo_tabelas']); ?></span>
                </li>
                <li>
                    <span class="info-label">Tamanho Total</span>
                    <span class="info-value"><?php echo esc_html($informacoes_sistema['banco_dados']['tamanho_total']); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <div class="panel-wp-card">
        <h2>Informações do Servidor</h2>
        <ul class="panel-wp-info-list">
            <li>
                <span class="info-label">Sistema Operacional</span>
                <span class="info-value"><?php echo esc_html($informacoes_sistema['informacoes_servidor']['so']); ?></span>
            </li>
            <li>
                <span class="info-label">Arquitetura</span>
                <span class="info-value"><?php echo esc_html($informacoes_sistema['informacoes_servidor']['arquitetura']); ?></span>
            </li>
        </ul>
    </div>

    <div class="panel-wp-card">
        <h2>Plugins Ativos</h2>
        <ul class="panel-wp-info-list">
            <?php 
            foreach ($informacoes_sistema['plugins_ativos'] as $plugin) {
                echo '<li><span class="info-label">' . esc_html($plugin) . '</span></li>';
            }
            ?>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusElements = document.querySelectorAll('.status');
    
    statusElements.forEach(elemento => {
        const status = elemento.classList.item(1);
        
        switch(status) {
            case 'warning':
                elemento.setAttribute('title', 'Recomenda-se atualizar para uma versão mais recente');
                break;
            case 'error':
                elemento.setAttribute('title', 'Versão incompatível ou crítica');
                break;
        }
    });
});</script>
