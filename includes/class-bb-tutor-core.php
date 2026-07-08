<?php
/**
 * Classe Core do Plugin BuddyBoss Tutores IA
 *
 * Responsável por inicializar o plugin, gerenciar autoload de classes,
 * registrar hooks e controlar o versionamento.
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe principal do plugin.
 */
class BB_Tutor_Core {
    
    /**
     * Instância única da classe (Singleton).
     *
     * @var BB_Tutor_Core
     */
    private static $instance = null;
    
    /**
     * Versão do plugin.
     *
     * @var string
     */
    private $version;
    
    /**
     * Classes carregadas.
     *
     * @var array
     */
    private $loaded_classes = [];
    
    /**
     * Construtor privado (Singleton).
     */
    private function __construct() {
        $this->version = BB_TUTOR_IA_VERSION;
        $this->setup_autoloader();
        $this->init_hooks();
    }
    
    /**
     * Retorna a instância única da classe.
     *
     * @return BB_Tutor_Core
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Configura o autoloader de classes.
     */
    private function setup_autoloader() {
        spl_autoload_register([$this, 'autoload']);
    }
    
    /**
     * Autoload de classes do plugin.
     *
     * @param string $class_name Nome da classe a ser carregada.
     */
    public function autoload($class_name) {
        // Verificar se a classe pertence ao nosso namespace
        if (strpos($class_name, 'BB_Tutor_') !== 0) {
            return;
        }
        
        // Converter nome da classe para nome do arquivo
        // BB_Tutor_Group -> class-bb-tutor-group.php
        $class_file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        $file_path = BB_TUTOR_IA_PLUGIN_DIR . 'includes/' . $class_file;
        
        // Carregar arquivo se existir
        if (file_exists($file_path)) {
            require_once $file_path;
            $this->loaded_classes[] = $class_name;
        }
    }
    
    /**
     * Registra hooks de inicialização do WordPress.
     */
    private function init_hooks() {
        // Hook de inicialização principal
        add_action('init', [$this, 'init'], 0);
        
        // Hook para inicializar admin
        if (is_admin()) {
            add_action('admin_menu', [$this, 'init_admin'], 5);
        }
        
        // Hook para carregar componentes após BuddyPress/BuddyBoss
        add_action('bp_include', [$this, 'load_components']);
        
        // Fallback caso bp_include não seja acionado
        add_action('bp_init', [$this, 'load_components']);
        
        // Hook para enqueue de scripts e estilos
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Hook para verificar versão e executar atualizações
        add_action('plugins_loaded', [$this, 'check_version'], 10);
        
        // Hook para limpeza de logs antigos (diário)
        add_action('bb_tutor_ia_daily_cleanup', [$this, 'cleanup_old_logs']);
        
        // Agendar limpeza de logs se não estiver agendado
        if (!wp_next_scheduled('bb_tutor_ia_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'bb_tutor_ia_daily_cleanup');
        }
    }
    
    /**
     * Inicialização do plugin.
     */
    public function init() {
        // Carregar tradução
        load_plugin_textdomain(
            'bb-tutor-ia',
            false,
            dirname(plugin_basename(BB_TUTOR_IA_PLUGIN_DIR)) . '/languages'
        );
    }
    
    /**
     * Carrega componentes do plugin após BuddyPress/BuddyBoss.
     */
    public function load_components() {
        // Evitar carregamento duplicado
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;
        
        // Verificar se BuddyPress/BuddyBoss está ativo
        if (!function_exists('bp_is_active')) {
            add_action('admin_notices', [$this, 'buddypress_required_notice']);
            return;
        }
        
        // Inicializar componentes principais
        $this->init_group_integration();
        $this->init_ajax_handlers();
    }
    
    /**
     * Inicializa componente de administração.
     */
    public function init_admin() {
        // Carregar classe de administração
        if (!class_exists('BB_Tutor_Admin')) {
            require_once BB_TUTOR_IA_PLUGIN_DIR . 'includes/class-bb-tutor-admin.php';
        }
        BB_Tutor_Admin::get_instance();
    }
    
    /**
     * Inicializa integração com grupos BuddyBoss.
     */
    private function init_group_integration() {
        // Carregar e inicializar classe de integração com grupos
        if (!class_exists('BB_Tutor_Group')) {
            require_once BB_TUTOR_IA_PLUGIN_DIR . 'includes/class-bb-tutor-group.php';
        }
        BB_Tutor_Group::get_instance();
    }
    
    /**
     * Inicializa handlers AJAX.
     */
    private function init_ajax_handlers() {
        // Carregar e inicializar handlers AJAX
        if (!class_exists('BB_Tutor_AJAX')) {
            require_once BB_TUTOR_IA_PLUGIN_DIR . 'includes/class-bb-tutor-ajax.php';
        }
        BB_Tutor_AJAX::get_instance();
    }
    
    /**
     * Enqueue de scripts e estilos do frontend.
     */
    public function enqueue_scripts() {
        // CSS do chat
        wp_register_style(
            'bb-tutor-chat',
            BB_TUTOR_IA_PLUGIN_URL . 'assets/css/chat.css',
            [],
            $this->version
        );
        
        // JavaScript do chat
        wp_register_script(
            'bb-tutor-chat',
            BB_TUTOR_IA_PLUGIN_URL . 'assets/js/chat.js',
            ['jquery'],
            $this->version,
            true
        );
        
        // Localizar script com dados necessários
        wp_localize_script('bb-tutor-chat', 'bbTutorData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bb_tutor_chat'),
            'strings' => [
                'sending' => __('Enviando...', 'bb-tutor-ia'),
                'send' => __('Enviar', 'bb-tutor-ia'),
                'error' => __('Erro ao processar mensagem', 'bb-tutor-ia'),
                'connectionError' => __('Erro de conexão. Tente novamente.', 'bb-tutor-ia'),
                'sources' => __('Fontes:', 'bb-tutor-ia'),
                'noContext' => __('Esta resposta não foi baseada nos documentos fornecidos.', 'bb-tutor-ia'),
            ]
        ]);
    }
    
    /**
     * Enqueue de scripts e estilos do admin.
     */
    public function enqueue_admin_scripts($hook) {
        // Adicionar timestamp para forçar reload durante desenvolvimento
        $version = $this->version . '.' . time();
        
        // CSS do admin
        wp_enqueue_style(
            'bb-tutor-admin',
            BB_TUTOR_IA_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $version
        );
        
        // JavaScript do admin
        wp_enqueue_script(
            'bb-tutor-admin',
            BB_TUTOR_IA_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            $version,
            true
        );
        
        // Localizar script
        wp_localize_script('bb-tutor-admin', 'bbTutorAdminData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'uploadNonce' => wp_create_nonce('bb_tutor_upload'),
            'deleteNonce' => wp_create_nonce('bb_tutor_delete'),
            'strings' => [
                'uploading' => __('Enviando...', 'bb-tutor-ia'),
                'uploadSuccess' => __('Arquivo enviado com sucesso!', 'bb-tutor-ia'),
                'uploadError' => __('Erro ao enviar arquivo.', 'bb-tutor-ia'),
                'deleteConfirm' => __('Tem certeza que deseja excluir este arquivo?', 'bb-tutor-ia'),
                'deleteSuccess' => __('Arquivo excluído com sucesso!', 'bb-tutor-ia'),
                'deleteError' => __('Erro ao excluir arquivo.', 'bb-tutor-ia'),
            ]
        ]);
    }
    
    /**
     * Verifica versão e executa atualizações se necessário.
     */
    public function check_version() {
        $saved_version = get_option('bb_tutor_ia_version', '0.0.0');
        
        // Comparar versões
        if (version_compare($saved_version, $this->version, '<')) {
            // Log da atualização
            error_log(sprintf(
                'BuddyBoss Tutores IA: Atualizando da versão %s para %s',
                $saved_version,
                $this->version
            ));
            
            // Executar rotinas de atualização
            $this->upgrade($saved_version, $this->version);
            
            // Atualizar versão no banco de dados
            update_option('bb_tutor_ia_version', $this->version);
            
            // Registrar timestamp da última atualização
            update_option('bb_tutor_ia_last_updated', current_time('mysql'));
            
            // Hook para outras ações pós-atualização
            do_action('bb_tutor_ia_version_updated', $saved_version, $this->version);
        }
    }
    
    /**
     * Executa rotinas de atualização.
     *
     * @param string $from_version Versão anterior.
     * @param string $to_version Versão atual.
     */
    private function upgrade($from_version, $to_version) {
        // Executar rotinas de atualização específicas por versão
        // Exemplo: migração de dados, atualização de estruturas, etc.
        
        // Hook para permitir extensões executarem suas próprias atualizações
        do_action('bb_tutor_ia_upgrade', $from_version, $to_version);
        
        // Exemplo de migração específica por versão:
        // if (version_compare($from_version, '1.1.0', '<')) {
        //     $this->upgrade_to_1_1_0();
        // }
        
        // Limpar cache após atualização
        wp_cache_flush();
    }
    
    /**
     * Retorna a versão salva no banco de dados.
     *
     * @return string
     */
    public function get_saved_version() {
        return get_option('bb_tutor_ia_version', '0.0.0');
    }
    
    /**
     * Retorna informações sobre a versão do plugin.
     *
     * @return array
     */
    public function get_version_info() {
        return [
            'current' => $this->version,
            'saved' => $this->get_saved_version(),
            'last_updated' => get_option('bb_tutor_ia_last_updated', ''),
            'needs_update' => version_compare($this->get_saved_version(), $this->version, '<')
        ];
    }
    
    /**
     * Exibe aviso de que BuddyPress é necessário.
     */
    public function buddypress_required_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php _e('BuddyBoss Tutores IA requer BuddyBoss ou BuddyPress ativo.', 'bb-tutor-ia'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Retorna a versão do plugin.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Retorna lista de classes carregadas.
     *
     * @return array
     */
    public function get_loaded_classes() {
        return $this->loaded_classes;
    }
    
    /**
     * Executa limpeza de logs antigos.
     */
    public function cleanup_old_logs() {
        if (class_exists('BB_Tutor_Logger')) {
            BB_Tutor_Logger::clean_old_logs();
        }
    }
}

