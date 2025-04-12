<?php
/**
 * Teste para o recurso de Auto-Login
 * 
 * Este script testa o endpoint de auto-login do Panel WP Connector
 * Para usar: acesse este arquivo diretamente no navegador
 */

// Carregar WordPress
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php');

// Verificar se o usuário tem permissões
if (!current_user_can('manage_options')) {
    wp_die('Você precisa ser um administrador para executar este teste.');
}

// Verificar se o plugin está ativo
if (!class_exists('PanelWPConnector\Authentication\PanelWPAuthentication')) {
    wp_die('O plugin Panel WP Connector não está ativo.');
}

// Obter uma chave API válida para teste
global $wpdb;
$api_key = $wpdb->get_var("SELECT api_key FROM {$wpdb->prefix}panel_wp_api_keys LIMIT 1");

if (!$api_key) {
    wp_die('Nenhuma chave API encontrada. Gere uma chave API nas configurações do plugin primeiro.');
}

// URL do endpoint de auto-login
$auto_login_url = rest_url('panel-wp/v1/authenticate') . '?api_key=' . urlencode($api_key) . '&auto_login=true';

// Exibir informações de teste
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Auto-Login - Panel WP Connector</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2271b1;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .button {
            display: inline-block;
            background: #2271b1;
            color: #fff;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
        .button:hover {
            background: #135e96;
        }
        code {
            background: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 14px;
        }
        .info {
            background: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 12px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>Teste de Auto-Login - Panel WP Connector</h1>
    
    <div class="card">
        <h2>Informações do Teste</h2>
        <p>Este teste verifica se o endpoint de auto-login está funcionando corretamente.</p>
        
        <div class="info">
            <p><strong>Chave API:</strong> <code><?php echo esc_html(substr($api_key, 0, 8) . '...' . substr($api_key, -8)); ?></code></p>
            <p><strong>URL do Endpoint:</strong> <code><?php echo esc_html($auto_login_url); ?></code></p>
        </div>
        
        <p>Clique no botão abaixo para testar o auto-login:</p>
        
        <p>
            <a href="<?php echo esc_url($auto_login_url); ?>" class="button" target="_blank">Testar Auto-Login</a>
        </p>
        
        <p><strong>Nota:</strong> Este teste abrirá uma nova aba e tentará fazer login automático usando a chave API.</p>
    </div>
    
    <div class="card">
        <h2>Exemplo de Implementação</h2>
        <p>Para implementar o auto-login em uma aplicação externa, use o seguinte código JavaScript:</p>
        
        <pre><code>function loginWordPress(apiKey, siteUrl) {
  const autoLoginUrl = `${siteUrl}/wp-json/panel-wp/v1/authenticate?api_key=${apiKey}&auto_login=true`;
  window.open(autoLoginUrl, '_blank');
}</code></pre>
    </div>
</body>
</html>
<?php
// Fim do script
