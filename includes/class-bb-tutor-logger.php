<?php
/**
 * Classe de Logger do Plugin BuddyBoss Tutores IA
 *
 * Responsável por registrar erros e eventos importantes em arquivos de log
 * para facilitar o debug e monitoramento do plugin.
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe de logging.
 */
class BB_Tutor_Logger {
    
    /**
     * Diretório de logs.
     *
     * @var string
     */
    private static $log_dir;
    
    /**
     * Níveis de log.
     */
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    
    /**
     * Inicializa o logger.
     */
    public static function init() {
        self::$log_dir = BB_TUTOR_IA_PLUGIN_DIR . 'logs/';
        
        // Criar diretório de logs se não existir
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            
            // Criar arquivo .htaccess para proteger os logs
            self::create_htaccess();
            
            // Criar arquivo index.php para prevenir listagem de diretório
            self::create_index_file();
        }
    }
    
    /**
     * Cria arquivo .htaccess para proteger os logs.
     */
    private static function create_htaccess() {
        $htaccess_file = self::$log_dir . '.htaccess';
        
        if (!file_exists($htaccess_file)) {
            $content = "# Proteger logs do BuddyBoss Tutores IA\n";
            $content .= "Order deny,allow\n";
            $content .= "Deny from all\n";
            
            file_put_contents($htaccess_file, $content);
        }
    }
    
    /**
     * Cria arquivo index.php para prevenir listagem de diretório.
     */
    private static function create_index_file() {
        $index_file = self::$log_dir . 'index.php';
        
        if (!file_exists($index_file)) {
            $content = "<?php\n// Silence is golden.\n";
            file_put_contents($index_file, $content);
        }
    }
    
    /**
     * Registra uma mensagem de erro.
     *
     * @param string $message Mensagem de erro.
     * @param array $context Contexto adicional (opcional).
     */
    public static function error($message, $context = []) {
        self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Registra uma mensagem de aviso.
     *
     * @param string $message Mensagem de aviso.
     * @param array $context Contexto adicional (opcional).
     */
    public static function warning($message, $context = []) {
        self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Registra uma mensagem informativa.
     *
     * @param string $message Mensagem informativa.
     * @param array $context Contexto adicional (opcional).
     */
    public static function info($message, $context = []) {
        self::log(self::INFO, $message, $context);
    }
    
    /**
     * Registra uma mensagem de debug.
     *
     * @param string $message Mensagem de debug.
     * @param array $context Contexto adicional (opcional).
     */
    public static function debug($message, $context = []) {
        // Só registra debug se WP_DEBUG estiver ativo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log(self::DEBUG, $message, $context);
        }
    }
    
    /**
     * Registra uma mensagem no arquivo de log.
     *
     * @param string $level Nível do log.
     * @param string $message Mensagem.
     * @param array $context Contexto adicional.
     */
    private static function log($level, $message, $context = []) {
        // Verificar se o diretório existe
        if (!self::$log_dir) {
            self::init();
        }
        
        // Nome do arquivo de log (um por dia)
        $log_file = self::$log_dir . 'bb-tutor-' . date('Y-m-d') . '.log';
        
        // Formatar timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Formatar mensagem
        $log_message = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            $level,
            $message
        );
        
        // Adicionar contexto se houver
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Adicionar informações do usuário se disponível
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $log_message .= sprintf(
                ' | User: %s (ID: %d)',
                $user->user_login,
                $user->ID
            );
        }
        
        // Adicionar URL da requisição
        if (isset($_SERVER['REQUEST_URI'])) {
            $log_message .= ' | URL: ' . sanitize_text_field($_SERVER['REQUEST_URI']);
        }
        
        $log_message .= "\n";
        
        // Escrever no arquivo
        error_log($log_message, 3, $log_file);
        
        // Limitar tamanho do arquivo (5MB)
        self::rotate_log_if_needed($log_file);
    }
    
    /**
     * Rotaciona o arquivo de log se ele ficar muito grande.
     *
     * @param string $log_file Caminho do arquivo de log.
     */
    private static function rotate_log_if_needed($log_file) {
        if (file_exists($log_file) && filesize($log_file) > 5242880) { // 5MB
            $backup_file = $log_file . '.old';
            
            // Se já existe um backup, deletar
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            
            // Renomear arquivo atual para backup
            rename($log_file, $backup_file);
        }
    }
    
    /**
     * Limpa logs antigos (mais de 30 dias).
     */
    public static function clean_old_logs() {
        if (!self::$log_dir) {
            self::init();
        }
        
        $files = glob(self::$log_dir . '*.log*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Deletar arquivos com mais de 30 dias
                if ($now - filemtime($file) >= 30 * 24 * 60 * 60) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Retorna o caminho do diretório de logs.
     *
     * @return string
     */
    public static function get_log_dir() {
        if (!self::$log_dir) {
            self::init();
        }
        return self::$log_dir;
    }
    
    /**
     * Lista todos os arquivos de log disponíveis.
     *
     * @return array
     */
    public static function get_log_files() {
        if (!self::$log_dir) {
            self::init();
        }
        
        $files = glob(self::$log_dir . '*.log*');
        $log_files = [];
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $log_files[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }
        
        // Ordenar por data de modificação (mais recente primeiro)
        usort($log_files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $log_files;
    }
    
    /**
     * Lê o conteúdo de um arquivo de log.
     *
     * @param string $filename Nome do arquivo.
     * @param int $lines Número de linhas a ler (padrão: 100 últimas linhas).
     * @return string|false
     */
    public static function read_log($filename, $lines = 100) {
        if (!self::$log_dir) {
            self::init();
        }
        
        $file_path = self::$log_dir . basename($filename);
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Ler últimas N linhas do arquivo
        $file = new SplFileObject($file_path, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        
        $content = [];
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = $file->current();
            if (!empty(trim($line))) {
                $content[] = $line;
            }
            $file->next();
        }
        
        return implode('', $content);
    }
}

// Inicializar logger
BB_Tutor_Logger::init();
