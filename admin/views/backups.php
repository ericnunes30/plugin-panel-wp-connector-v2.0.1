<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap panel-wp-wrapper">
    <div class="panel-wp-header">
        <h1>
            <?php echo esc_html(get_admin_page_title()); ?>
        </h1>
    </div>

    <?php settings_errors(); ?>

    <div class="panel-wp-card">
        <h2><?php _e('Status dos Backups', 'panel-wp-connector'); ?></h2>
        
        <div id="panel-wp-backup-status">
            <div class="backup-status-container">
                <h3><?php _e('Backup em Andamento', 'panel-wp-connector'); ?></h3>
                <div id="backup-progress-bar" class="progress-bar">
                    <div class="progress-bar-fill" style="width: 0%;">
                        <span class="progress-text">0%</span>
                    </div>
                </div>
                <p id="backup-current-status"><?php _e('Nenhum backup em andamento', 'panel-wp-connector'); ?></p>
            </div>

            <div class="backup-logs-container">
                <h3><?php _e('Logs do Backup', 'panel-wp-connector'); ?></h3>
                <div id="backup-logs" class="backup-logs">
                    <p class="no-logs"><?php _e('Nenhum log disponível', 'panel-wp-connector'); ?></p>
                </div>
            </div>
        </div>

        <div class="backup-actions">
            <h3><?php _e('Iniciar Novo Backup', 'panel-wp-connector'); ?></h3>
            <form id="iniciar-backup-form" method="post">
                <select name="tipo_backup" id="tipo_backup">
                    <option value="completo"><?php _e('Backup Completo', 'panel-wp-connector'); ?></option>
                    <option value="banco_dados"><?php _e('Apenas Banco de Dados', 'panel-wp-connector'); ?></option>
                    <option value="arquivos"><?php _e('Apenas Arquivos', 'panel-wp-connector'); ?></option>
                </select>
                <button type="submit" class="button button-primary" id="iniciar-backup">
                    <?php _e('Iniciar Backup', 'panel-wp-connector'); ?>
                </button>
            </form>
        </div>

        <div class="backup-history">
            <h3><?php _e('Histórico de Backups', 'panel-wp-connector'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Data', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Tipo', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Status', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Tamanho', 'panel-wp-connector'); ?></th>
                        <th><?php _e('Ações', 'panel-wp-connector'); ?></th>
                    </tr>
                </thead>
                <tbody id="backup-history-list">
                    <!-- Preenchido via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.status-completed {
    background-color: #46b450;
    color: white;
}

.status-processing {
    background-color: #00a0d2;
    color: white;
}

.status-failed {
    background-color: #dc3232;
    color: white;
}

.status-unknown {
    background-color: #666;
    color: white;
}

.progress-mini {
    width: 100px;
    height: 4px;
    background-color: #f0f0f1;
    border-radius: 2px;
    margin-top: 5px;
    overflow: hidden;
}

.progress-mini .progress-bar-fill {
    height: 100%;
    background-color: #00a0d2;
    transition: width 0.3s ease;
}

.button .dashicons {
    vertical-align: middle;
    margin-top: -2px;
}

.backup-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

#backup-logs {
    max-height: 200px;
    overflow-y: auto;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    padding: 10px;
    margin: 10px 0;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
}

#backup-logs p {
    margin: 0;
    padding: 2px 0;
}

.no-logs {
    color: #666;
    font-style: italic;
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentBackupId = null;
    let checkStatusInterval = null;

    // Função para atualizar o progresso do backup
    function updateBackupProgress(backupId) {
        $.ajax({
            url: `<?php echo rest_url('panel-wp/v1/backup-status/'); ?>${backupId}`,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (response.success) {
                    // Atualizar barra de progresso
                    $('.progress-bar-fill').css('width', `${response.progresso}%`);
                    $('.progress-text').text(`${response.progresso}%`);
                    
                    // Atualizar status atual
                    $('#backup-current-status').text(response.status_atual);
                    
                    // Adicionar log
                    $('#backup-logs').append(`<p>${response.status_atual}</p>`);
                    $('#backup-logs').scrollTop($('#backup-logs')[0].scrollHeight);
                    
                    // Se o backup estiver completo ou falhou
                    if (response.status === 'completed' || response.status === 'failed') {
                        clearInterval(checkStatusInterval);
                        loadBackupHistory();
                        
                        if (response.status === 'completed') {
                            alert('Backup concluído com sucesso!');
                        } else {
                            alert('Erro no backup: ' + response.message);
                        }
                    }
                }
            },
            error: function() {
                $('#backup-current-status').text('Erro ao verificar status do backup');
            }
        });
    }

    // Função para carregar histórico de backups
    function loadBackupHistory() {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'get_backup_history'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const historyHtml = response.data.map(backup => `
                        <tr>
                            <td>${backup.id || '-'}</td>
                            <td>${backup.data || '-'}</td>
                            <td>${backup.tipo || 'completo'}</td>
                            <td>
                                <span class="status-badge status-${backup.status || 'unknown'}">
                                    ${backup.status || 'Desconhecido'}
                                </span>
                                ${backup.status === 'processing' ? 
                                    `<div class="progress-mini">
                                        <div class="progress-bar-fill" style="width: ${backup.progresso || 0}%"></div>
                                    </div>` : 
                                    ''}
                            </td>
                            <td>${backup.tamanho || '-'}</td>
                            <td>
                                ${backup.status === 'completed' && backup.download_url ? 
                                    `<a href="${backup.download_url}" class="button button-primary">Download</a>` : 
                                    ''}
                                <button class="button button-secondary delete-backup" data-id="${backup.id}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    
                    $('#backup-history-list').html(historyHtml || '<tr><td colspan="6">Nenhum backup encontrado</td></tr>');
                } else {
                    $('#backup-history-list').html('<tr><td colspan="6">Erro ao carregar histórico de backups</td></tr>');
                }
            }
        });
    }

    // Iniciar backup
    $('#iniciar-backup-form').on('submit', function(e) {
        e.preventDefault();
        
        const tipo = $('#tipo_backup').val();
        
        $.ajax({
            url: '<?php echo rest_url('panel-wp/v1/backup'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            contentType: 'application/json',
            data: JSON.stringify({
                tipo: tipo
            }),
            success: function(response) {
                if (response.success) {
                    currentBackupId = response.backup_id;
                    
                    // Limpar logs anteriores
                    $('#backup-logs').html('');
                    
                    // Iniciar verificação de status
                    checkStatusInterval = setInterval(() => {
                        updateBackupProgress(currentBackupId);
                    }, 5000);
                    
                    // Primeira verificação imediata
                    updateBackupProgress(currentBackupId);
                } else {
                    alert('Erro ao iniciar backup: ' + response.message);
                }
            },
            error: function() {
                alert('Erro ao iniciar backup');
            }
        });
    });

    // Excluir backup
    $(document).on('click', '.delete-backup', function() {
        const backupId = $(this).data('id');
        if (confirm('Tem certeza que deseja excluir este backup?')) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'delete_backup',
                    backup_id: backupId
                },
                success: function(response) {
                    if (response.success) {
                        loadBackupHistory();
                    } else {
                        alert('Erro ao excluir backup: ' + response.message);
                    }
                }
            });
        }
    });

    // Carregar histórico inicial
    loadBackupHistory();
});
</script>