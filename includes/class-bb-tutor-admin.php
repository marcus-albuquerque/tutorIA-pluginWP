<?php
/**
 * Classe de Administração do Plugin BuddyBoss Tutores IA
 *
 * Responsável por criar a página de configurações globais no admin do WordPress,
 * gerenciar a API Key do Gemini e outras configurações do plugin.
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe de administração do plugin.
 */
class BB_Tutor_Admin {
    
    /**
     * Instância única da classe (Singleton).
     *
     * @var BB_Tutor_Admin
     */
    private static $instance = null;
    
    /**
     * Slug da página de configurações.
     *
     * @var string
     */
    private $page_slug = 'bb-tutor-ia-settings';
    
    /**
     * Nome da opção para API Key.
     *
     * @var string
     */
    private $api_key_option = 'bb_tutor_ia_gemini_api_key';
    
    /**
     * Nome da opção para configurações gerais.
     *
     * @var string
     */
    private $settings_option = 'bb_tutor_ia_settings';
    
    /**
     * Construtor privado (Singleton).
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Retorna a instância única da classe.
     *
     * @return BB_Tutor_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Registra hooks de administração.
     */
    private function init_hooks() {
        // Adicionar menu no admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Registrar configurações
        add_action('admin_init', [$this, 'register_settings']);
        
        // Adicionar link de configurações na página de plugins
        add_filter('plugin_action_links_' . plugin_basename(BB_TUTOR_IA_PLUGIN_DIR . 'buddyboss-tutores-ia.php'), [$this, 'add_settings_link']);
    }
    
    /**
     * Adiciona menu no admin do WordPress.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('BuddyBoss Tutores IA', 'bb-tutor-ia'),
            __('Tutores IA', 'bb-tutor-ia'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page'],
            'dashicons-welcome-learn-more',
            80
        );
    }
    
    /**
     * Registra configurações do plugin.
     */
    public function register_settings() {
        // Registrar opção da API Key
        register_setting(
            'bb_tutor_ia_settings_group',
            $this->api_key_option,
            [
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitize_api_key'],
                'default' => ''
            ]
        );
        
        // Registrar opção de configurações gerais
        register_setting(
            'bb_tutor_ia_settings_group',
            $this->settings_option,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings()
            ]
        );
        
        // Seção de API Key
        add_settings_section(
            'bb_tutor_ia_api_section',
            __('Configurações da API', 'bb-tutor-ia'),
            [$this, 'render_api_section_description'],
            $this->page_slug
        );
        
        // Campo de API Key
        add_settings_field(
            'bb_tutor_ia_api_key',
            __('API Key do Google Gemini', 'bb-tutor-ia'),
            [$this, 'render_api_key_field'],
            $this->page_slug,
            'bb_tutor_ia_api_section'
        );
        
        // Seção de configurações gerais
        add_settings_section(
            'bb_tutor_ia_general_section',
            __('Configurações Gerais', 'bb-tutor-ia'),
            [$this, 'render_general_section_description'],
            $this->page_slug
        );
        
        // Campo de tamanho máximo de arquivo
        add_settings_field(
            'bb_tutor_ia_max_file_size',
            __('Tamanho Máximo de Arquivo', 'bb-tutor-ia'),
            [$this, 'render_max_file_size_field'],
            $this->page_slug,
            'bb_tutor_ia_general_section'
        );
        
        // Campo de habilitar logs
        add_settings_field(
            'bb_tutor_ia_enable_logs',
            __('Habilitar Logs', 'bb-tutor-ia'),
            [$this, 'render_enable_logs_field'],
            $this->page_slug,
            'bb_tutor_ia_general_section'
        );
    }
    
    /**
     * Renderiza a página de configurações.
     */
    public function render_settings_page() {
        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'bb-tutor-ia'));
        }
        
        // Verificar se a API Key foi validada
        $api_key = get_option($this->api_key_option);
        $api_key_status = $this->check_api_key_status($api_key);
        
        ?>
        <div class="wrap bb-tutor-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors('bb_tutor_ia_messages'); ?>
            
            <?php if ($api_key_status === 'valid'): ?>
                <div class="notice notice-success">
                    <p>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('API Key configurada e validada com sucesso!', 'bb-tutor-ia'); ?>
                    </p>
                </div>
            <?php elseif ($api_key_status === 'invalid'): ?>
                <div class="notice notice-error">
                    <p>
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('API Key inválida ou com problemas. Verifique sua chave.', 'bb-tutor-ia'); ?>
                    </p>
                </div>
            <?php elseif (empty($api_key)): ?>
                <div class="notice notice-warning">
                    <p>
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Configure sua API Key do Google Gemini para começar a usar o plugin.', 'bb-tutor-ia'); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php" class="bb-tutor-settings-form">
                <?php
                settings_fields('bb_tutor_ia_settings_group');
                do_settings_sections($this->page_slug);
                submit_button(__('Salvar Configurações', 'bb-tutor-ia'));
                ?>
            </form>
            
            <div class="bb-tutor-info-box">
                <h2><?php _e('Como obter sua API Key', 'bb-tutor-ia'); ?></h2>
                <ol>
                    <li><?php _e('Acesse o Google AI Studio:', 'bb-tutor-ia'); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a></li>
                    <li><?php _e('Faça login com sua conta Google', 'bb-tutor-ia'); ?></li>
                    <li><?php _e('Clique em "Create API Key"', 'bb-tutor-ia'); ?></li>
                    <li><?php _e('Copie a chave gerada e cole no campo acima', 'bb-tutor-ia'); ?></li>
                </ol>
                
                <h3><?php _e('Informações do Plugin', 'bb-tutor-ia'); ?></h3>
                <ul>
                    <li><strong><?php _e('Versão:', 'bb-tutor-ia'); ?></strong> <?php echo esc_html(BB_TUTOR_IA_VERSION); ?></li>
                    <li><strong><?php _e('Última Atualização:', 'bb-tutor-ia'); ?></strong> <?php echo esc_html(get_option('bb_tutor_ia_last_updated', __('N/A', 'bb-tutor-ia'))); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza descrição da seção de API.
     */
    public function render_api_section_description() {
        echo '<p>' . __('Configure a API Key do Google Gemini para habilitar os tutores de IA.', 'bb-tutor-ia') . '</p>';
    }
    
    /**
     * Renderiza descrição da seção geral.
     */
    public function render_general_section_description() {
        echo '<p>' . __('Configure opções gerais do plugin.', 'bb-tutor-ia') . '</p>';
    }
    
    /**
     * Renderiza campo de API Key.
     */
    public function render_api_key_field() {
        $api_key = get_option($this->api_key_option, '');
        $masked_key = $this->mask_api_key($api_key);
        ?>
        <input 
            type="password" 
            id="bb_tutor_ia_api_key" 
            name="<?php echo esc_attr($this->api_key_option); ?>" 
            value="<?php echo esc_attr($api_key); ?>" 
            class="regular-text"
            placeholder="AIzaSy..."
        />
        <button type="button" class="button bb-tutor-toggle-key" onclick="bbTutorToggleApiKey()">
            <?php _e('Mostrar/Ocultar', 'bb-tutor-ia'); ?>
        </button>
        <?php if (!empty($api_key)): ?>
            <p class="description">
                <?php printf(__('Chave atual: %s', 'bb-tutor-ia'), '<code>' . esc_html($masked_key) . '</code>'); ?>
            </p>
        <?php endif; ?>
        <p class="description">
            <?php _e('Insira sua API Key do Google Gemini. A chave será validada ao salvar.', 'bb-tutor-ia'); ?>
        </p>
        
        <script>
        function bbTutorToggleApiKey() {
            var input = document.getElementById('bb_tutor_ia_api_key');
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
        </script>
        <?php
    }
    
    /**
     * Renderiza campo de tamanho máximo de arquivo.
     */
    public function render_max_file_size_field() {
        $settings = get_option($this->settings_option, $this->get_default_settings());
        $max_size_mb = isset($settings['max_file_size']) ? $settings['max_file_size'] / 1048576 : 20;
        ?>
        <input 
            type="number" 
            name="<?php echo esc_attr($this->settings_option); ?>[max_file_size_mb]" 
            value="<?php echo esc_attr($max_size_mb); ?>" 
            min="1" 
            max="100" 
            step="1"
            class="small-text"
        /> MB
        <p class="description">
            <?php _e('Tamanho máximo permitido para upload de arquivos (PDF/TXT). Padrão: 20MB.', 'bb-tutor-ia'); ?>
        </p>
        <?php
    }
    
    /**
     * Renderiza campo de habilitar logs.
     */
    public function render_enable_logs_field() {
        $settings = get_option($this->settings_option, $this->get_default_settings());
        $enable_logs = isset($settings['enable_logs']) ? $settings['enable_logs'] : true;
        ?>
        <label>
            <input 
                type="checkbox" 
                name="<?php echo esc_attr($this->settings_option); ?>[enable_logs]" 
                value="1" 
                <?php checked($enable_logs, true); ?>
            />
            <?php _e('Habilitar registro de uso dos tutores', 'bb-tutor-ia'); ?>
        </label>
        <p class="description">
            <?php _e('Quando habilitado, o plugin registrará estatísticas de uso dos tutores (contador de consultas, última utilização, etc.).', 'bb-tutor-ia'); ?>
        </p>
        <?php
    }
    
    /**
     * Sanitiza a API Key.
     *
     * @param string $api_key API Key a ser sanitizada.
     * @return string API Key sanitizada.
     */
    public function sanitize_api_key($api_key) {
        $api_key = sanitize_text_field($api_key);
        
        // Se a chave estiver vazia, não validar
        if (empty($api_key)) {
            return '';
        }
        
        // Validar formato básico da API Key do Google
        if (!preg_match('/^AIza[0-9A-Za-z\-_]{35}$/', $api_key)) {
            add_settings_error(
                'bb_tutor_ia_messages',
                'bb_tutor_ia_api_key_format',
                __('Formato de API Key inválido. A chave deve começar com "AIza" e ter 39 caracteres.', 'bb-tutor-ia'),
                'error'
            );
            return get_option($this->api_key_option, '');
        }
        
        // Validar API Key fazendo uma requisição de teste
        $validation_result = $this->validate_api_key($api_key);
        
        if ($validation_result === true) {
            add_settings_error(
                'bb_tutor_ia_messages',
                'bb_tutor_ia_api_key_valid',
                __('API Key validada com sucesso!', 'bb-tutor-ia'),
                'success'
            );
            return $api_key;
        } else {
            add_settings_error(
                'bb_tutor_ia_messages',
                'bb_tutor_ia_api_key_invalid',
                sprintf(__('Erro ao validar API Key: %s', 'bb-tutor-ia'), $validation_result),
                'error'
            );
            return get_option($this->api_key_option, '');
        }
    }
    
    /**
     * Sanitiza as configurações gerais.
     *
     * @param array $settings Configurações a serem sanitizadas.
     * @return array Configurações sanitizadas.
     */
    public function sanitize_settings($settings) {
        $sanitized = [];
        
        // Sanitizar tamanho máximo de arquivo
        if (isset($settings['max_file_size_mb'])) {
            $max_size_mb = absint($settings['max_file_size_mb']);
            $max_size_mb = max(1, min(100, $max_size_mb)); // Entre 1 e 100 MB
            $sanitized['max_file_size'] = $max_size_mb * 1048576; // Converter para bytes
        } else {
            $sanitized['max_file_size'] = 20971520; // 20MB padrão
        }
        
        // Sanitizar enable_logs
        $sanitized['enable_logs'] = isset($settings['enable_logs']) && $settings['enable_logs'] === '1';
        
        // Tipos de arquivo permitidos (fixo)
        $sanitized['allowed_types'] = ['application/pdf', 'text/plain'];
        
        return $sanitized;
    }
    
    /**
     * Valida a API Key fazendo uma requisição de teste à API do Gemini.
     *
     * @param string $api_key API Key a ser validada.
     * @return bool|string True se válida, mensagem de erro caso contrário.
     */
    private function validate_api_key($api_key) {
        // URL para listar modelos (endpoint simples para validação)
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
        
        // Fazer requisição
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
        
        // Verificar se houve erro na requisição
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }
        
        // Verificar código de resposta
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return true;
        } elseif ($response_code === 400) {
            return __('API Key inválida ou malformada.', 'bb-tutor-ia');
        } elseif ($response_code === 403) {
            return __('API Key sem permissões necessárias ou bloqueada.', 'bb-tutor-ia');
        } elseif ($response_code === 404) {
            return __('Endpoint da API não encontrado. Verifique a URL.', 'bb-tutor-ia');
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Erro desconhecido', 'bb-tutor-ia');
            return sprintf(__('Erro %d: %s', 'bb-tutor-ia'), $response_code, $error_message);
        }
    }
    
    /**
     * Verifica o status da API Key.
     *
     * @param string $api_key API Key a ser verificada.
     * @return string Status: 'valid', 'invalid' ou 'empty'.
     */
    private function check_api_key_status($api_key) {
        if (empty($api_key)) {
            return 'empty';
        }
        
        // Verificar se há um transient de validação em cache
        $cache_key = 'bb_tutor_api_key_status_' . md5($api_key);
        $cached_status = get_transient($cache_key);
        
        if ($cached_status !== false) {
            return $cached_status;
        }
        
        // Validar API Key
        $validation_result = $this->validate_api_key($api_key);
        $status = ($validation_result === true) ? 'valid' : 'invalid';
        
        // Cachear resultado por 1 hora
        set_transient($cache_key, $status, HOUR_IN_SECONDS);
        
        return $status;
    }
    
    /**
     * Mascara a API Key para exibição.
     *
     * @param string $api_key API Key a ser mascarada.
     * @return string API Key mascarada.
     */
    private function mask_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        $length = strlen($api_key);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        // Mostrar primeiros 4 e últimos 4 caracteres
        return substr($api_key, 0, 4) . str_repeat('*', $length - 8) . substr($api_key, -4);
    }
    
    /**
     * Retorna configurações padrão.
     *
     * @return array Configurações padrão.
     */
    private function get_default_settings() {
        return [
            'max_file_size' => 20971520, // 20MB em bytes
            'allowed_types' => ['application/pdf', 'text/plain'],
            'enable_logs' => true
        ];
    }
    
    /**
     * Adiciona link de configurações na página de plugins.
     *
     * @param array $links Links existentes.
     * @return array Links modificados.
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=' . $this->page_slug),
            __('Configurações', 'bb-tutor-ia')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Retorna a API Key armazenada.
     *
     * @return string API Key.
     */
    public function get_api_key() {
        return get_option($this->api_key_option, '');
    }
    
    /**
     * Retorna as configurações gerais.
     *
     * @return array Configurações.
     */
    public function get_settings() {
        return get_option($this->settings_option, $this->get_default_settings());
    }
}
