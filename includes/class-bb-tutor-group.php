<?php
/**
 * Classe de Integração com Grupos BuddyBoss
 *
 * Responsável por adicionar a aba "Tutor IA" nos grupos, gerenciar configurações
 * do tutor no grupo e integrar com a API do Gemini.
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe de integração com grupos BuddyBoss.
 */
class BB_Tutor_Group {
    
    /**
     * Instância única da classe (Singleton).
     *
     * @var BB_Tutor_Group
     */
    private static $instance = null;
    
    /**
     * Construtor privado (Singleton).
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Retorna a instância única da classe.
     *
     * @return BB_Tutor_Group
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Registra hooks de integração com grupos.
     */
    private function init_hooks() {
        // Adicionar seção nas configurações do grupo
        add_action('bp_after_group_settings_admin', [$this, 'render_settings']);
        
        // Salvar configurações do grupo
        add_action('groups_group_settings_edited', [$this, 'save_settings']);
        
        // Adicionar aba "Tutor IA" nos grupos (múltiplos hooks para compatibilidade)
        add_action('bp_setup_nav', [$this, 'setup_group_tab'], 100);
        add_action('bp_actions', [$this, 'setup_group_tab'], 5);
        
        // Handler para renderizar conteúdo da aba
        add_action('bp_actions', [$this, 'handle_group_tab'], 10);
        
        // Enqueue assets nas páginas de configuração do grupo
        add_action('bp_enqueue_scripts', [$this, 'enqueue_group_settings_assets']);
    }
    
    /**
     * Enfileira assets nas páginas de configuração do grupo.
     */
    public function enqueue_group_settings_assets() {
        // Verificar se estamos na página de configurações do grupo
        if (!bp_is_group() || !bp_is_group_admin_page()) {
            return;
        }
        
        // Adicionar timestamp para forçar reload
        $version = BB_TUTOR_IA_VERSION . '.' . time();
        
        // Enfileirar CSS do admin
        wp_enqueue_style(
            'bb-tutor-admin',
            BB_TUTOR_IA_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $version
        );
        
        // Enfileirar JavaScript do admin
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
     * Renderiza seção de configurações do tutor nas configurações do grupo.
     */
    public function render_settings() {
        $group_id = bp_get_current_group_id();
        
        // Verificar permissão
        if (!bb_tutor_user_can_manage($group_id)) {
            return;
        }
        
        // Obter configurações atuais
        $enabled = groups_get_groupmeta($group_id, '_tutor_ia_enabled');
        $block_id = groups_get_groupmeta($group_id, '_tutor_ia_block_id');
        $description = groups_get_groupmeta($group_id, '_tutor_ia_description');
        $store_id = groups_get_groupmeta($group_id, '_tutor_ia_store_id');
        
        // Obter arquivos enviados
        $files = json_decode(groups_get_groupmeta($group_id, '_tutor_ia_files') ?: '[]', true);
        
        // Obter estatísticas
        $usage_count = groups_get_groupmeta($group_id, '_tutor_ia_usage_count') ?: 0;
        $last_used = groups_get_groupmeta($group_id, '_tutor_ia_last_used');
        
        // Incluir template
        include BB_TUTOR_IA_PLUGIN_DIR . 'templates/group-settings.php';
    }
    
    /**
     * Salva configurações do tutor do grupo.
     *
     * @param int $group_id ID do grupo.
     */
    public function save_settings($group_id) {
        // Verificar permissão
        if (!bb_tutor_user_can_manage($group_id)) {
            BB_Tutor_Logger::warning('save_settings: Permissão negada', ['group_id' => $group_id]);
            return;
        }
        
        // Verificar nonce
        if (!isset($_POST['bb_tutor_nonce']) || !wp_verify_nonce($_POST['bb_tutor_nonce'], 'bb_tutor_settings')) {
            BB_Tutor_Logger::warning('save_settings: Nonce inválido', ['group_id' => $group_id]);
            return;
        }
        
        // Salvar ativação do tutor
        $enabled = isset($_POST['tutor_ia_enabled']) ? '1' : '0';
        groups_update_groupmeta($group_id, '_tutor_ia_enabled', $enabled);
        
        // Salvar block_id
        if (isset($_POST['tutor_ia_block_id'])) {
            $block_id = sanitize_text_field($_POST['tutor_ia_block_id']);
            groups_update_groupmeta($group_id, '_tutor_ia_block_id', $block_id);
        }
        
        // Salvar descrição
        if (isset($_POST['tutor_ia_description'])) {
            $description = sanitize_textarea_field($_POST['tutor_ia_description']);
            groups_update_groupmeta($group_id, '_tutor_ia_description', $description);
        }
        
        // Criar store no Gemini se tutor foi ativado e store não existe
        if ($enabled === '1' && !groups_get_groupmeta($group_id, '_tutor_ia_store_id')) {
            BB_Tutor_Logger::info('save_settings: Criando store para grupo', ['group_id' => $group_id]);
            $this->create_gemini_store($group_id);
        }
    }
    
    /**
     * Cria um store no Gemini para o grupo.
     *
     * @param int $group_id ID do grupo.
     * @return bool True se criado com sucesso, false caso contrário.
     */
    private function create_gemini_store($group_id) {
        // Verificar se a classe Gemini existe
        if (!class_exists('BB_Tutor_Gemini')) {
            BB_Tutor_Logger::error('create_gemini_store: Classe BB_Tutor_Gemini não encontrada', [
                'group_id' => $group_id
            ]);
            return false;
        }
        
        // Obter nome do grupo
        $group = groups_get_group($group_id);
        $display_name = sprintf(__('Grupo: %s', 'bb-tutor-ia'), $group->name);
        
        // Criar store
        $gemini = new BB_Tutor_Gemini();
        $store = $gemini->create_store($display_name);
        
        // Verificar se houve erro
        if (isset($store['error'])) {
            BB_Tutor_Logger::error('create_gemini_store: Erro ao criar store', [
                'group_id' => $group_id,
                'group_name' => $group->name,
                'error' => $store['error']
            ]);
            
            // Adicionar mensagem de erro para o usuário
            bp_core_add_message(
                sprintf(__('Erro ao criar tutor: %s', 'bb-tutor-ia'), $store['error']),
                'error'
            );
            
            return false;
        }
        
        // Salvar store_id
        if (isset($store['name'])) {
            groups_update_groupmeta($group_id, '_tutor_ia_store_id', $store['name']);
            
            BB_Tutor_Logger::info('create_gemini_store: Store criado com sucesso', [
                'group_id' => $group_id,
                'store_id' => $store['name']
            ]);
            
            // Adicionar mensagem de sucesso
            bp_core_add_message(
                __('Tutor IA criado com sucesso!', 'bb-tutor-ia'),
                'success'
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Adiciona aba "Tutor IA" no grupo.
     */
    public function setup_group_tab() {
        // Evitar registro duplicado
        static $registered = false;
        if ($registered) {
            return;
        }

        // Verificar se estamos em um grupo
        if (!bp_is_group()) {
            return;
        }

        $group_id = bp_get_current_group_id();

        // Verificar se tutor está ativo
        $tutor_enabled = groups_get_groupmeta($group_id, '_tutor_ia_enabled');

        if (!$tutor_enabled || $tutor_enabled === '0') {
            return;
        }

        // Verificar se usuário tem acesso
        $user_has_access = bb_tutor_user_can_chat($group_id);

        // Adicionar aba
        bp_core_new_subnav_item([
            'name' => __('Tutor IA', 'bb-tutor-ia'),
            'slug' => 'tutor-ia',
            'parent_url' => bp_get_group_permalink(groups_get_current_group()),
            'parent_slug' => bp_get_current_group_slug(),
            'screen_function' => [$this, 'render_tab_content'],
            'position' => 90,
            'user_has_access' => $user_has_access,
            'item_css_id' => 'tutor-ia'
        ]);

        $registered = true;
    }

    
    /**
     * Renderiza conteúdo da aba "Tutor IA".
     */
    public function render_tab_content() {
        add_action('bp_template_content', [$this, 'tab_content']);
        bp_core_load_template('groups/single/plugins');
    }
    
    /**
     * Conteúdo da aba "Tutor IA".
     */
    public function tab_content() {
        $group_id = bp_get_current_group_id();
        
        // Verificar permissão
        if (!bb_tutor_user_can_chat($group_id)) {
            echo '<div class="bp-feedback error">';
            echo '<span class="bp-icon" aria-hidden="true"></span>';
            echo '<p>' . esc_html__('Você não tem permissão para acessar este tutor.', 'bb-tutor-ia') . '</p>';
            echo '</div>';
            return;
        }
        
        // Verificar se tutor está ativo
        if (!groups_get_groupmeta($group_id, '_tutor_ia_enabled')) {
            echo '<div class="bp-feedback info">';
            echo '<span class="bp-icon" aria-hidden="true"></span>';
            echo '<p>' . esc_html__('O tutor IA não está ativo para este grupo.', 'bb-tutor-ia') . '</p>';
            echo '</div>';
            return;
        }
        
        // Verificar se store foi criado
        $store_id = groups_get_groupmeta($group_id, '_tutor_ia_store_id');
        if (!$store_id) {
            echo '<div class="bp-feedback warning">';
            echo '<span class="bp-icon" aria-hidden="true"></span>';
            echo '<p>' . esc_html__('O tutor ainda está sendo configurado. Tente novamente em alguns instantes.', 'bb-tutor-ia') . '</p>';
            echo '</div>';
            return;
        }
        
        // Enqueue assets do chat
        wp_enqueue_style('bb-tutor-chat');
        wp_enqueue_script('bb-tutor-chat');
        
        // Incluir template do chat
        include BB_TUTOR_IA_PLUGIN_DIR . 'templates/group-tab.php';
    }
    
    /**
     * Handler para ações da aba do grupo.
     */
    public function handle_group_tab() {
        // Verificar se estamos na aba do tutor
        if (!bp_is_group() || !bp_is_current_action('tutor-ia')) {
            return;
        }
        
        // Ações específicas podem ser adicionadas aqui no futuro
    }
    
    /**
     * Retorna informações do tutor do grupo.
     *
     * @param int $group_id ID do grupo.
     * @return array Informações do tutor.
     */
    public function get_tutor_info($group_id) {
        return [
            'enabled' => groups_get_groupmeta($group_id, '_tutor_ia_enabled'),
            'block_id' => groups_get_groupmeta($group_id, '_tutor_ia_block_id'),
            'description' => groups_get_groupmeta($group_id, '_tutor_ia_description'),
            'store_id' => groups_get_groupmeta($group_id, '_tutor_ia_store_id'),
            'files' => json_decode(groups_get_groupmeta($group_id, '_tutor_ia_files') ?: '[]', true),
            'usage_count' => groups_get_groupmeta($group_id, '_tutor_ia_usage_count') ?: 0,
            'last_used' => groups_get_groupmeta($group_id, '_tutor_ia_last_used')
        ];
    }
}
