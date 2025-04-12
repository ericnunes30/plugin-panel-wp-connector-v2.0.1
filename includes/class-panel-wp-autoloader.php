<?php
namespace PanelWPConnector;

/**
 * Autoloader para classes do Plugin Panel WP Connector
 */
class PanelWPAutoloader {
    /**
     * Registra o autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Carrega classes do namespace PanelWPConnector
     * 
     * @param string $class Nome da classe a ser carregada
     */
    public static function autoload($class) {
        // Verificar se a classe pertence ao namespace do plugin
        if (strpos($class, 'PanelWPConnector\\') !== 0) {
            return;
        }

        // Remover o namespace base
        $class = str_replace('PanelWPConnector\\', '', $class);

        // Converter namespace para caminho de arquivo
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        
        // Definir diretórios base de busca
        $base_dirs = [
            __DIR__ . DIRECTORY_SEPARATOR,
            __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR,
            __DIR__ . DIRECTORY_SEPARATOR . 'authentication' . DIRECTORY_SEPARATOR,
            __DIR__ . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR,
            __DIR__ . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR
        ];

        // Possíveis variações de nome de arquivo
        $file_variations = [
            'class-' . strtolower(str_replace(['\\', '_'], ['-', '-'], $path)) . '.php',
            strtolower(str_replace(['\\', '_'], ['-', '-'], $path)) . '.php',
            'class-' . strtolower(str_replace(['\\', '_'], ['-', '-'], basename($path))) . '.php',
            $path . '.php'
        ];

        // Tentar carregar o arquivo
        foreach ($base_dirs as $base_dir) {
            foreach ($file_variations as $file_name) {
                $file = $base_dir . $file_name;
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    }
}