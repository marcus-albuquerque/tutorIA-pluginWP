<?php
/**
 * Plugin Name: BuddyBoss Tutores IA
 * Plugin URI: https://github.com/seu-usuario/buddyboss-tutores-ia
 * Description: Plugin WordPress para BuddyBoss que adiciona tutores de IA especializados aos grupos de disciplinas, utilizando a API do Google Gemini com RAG.
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seusite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bb-tutor-ia
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: buddyboss-platform
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Versão atual do plugin.
 */
define('BB_TUTOR_IA_VERSION', '1.0.0');

/**
 * Caminho do diretório do plugin.
 */
define('BB_TUTOR_IA_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * URL do diretório do plugin.
 */
define('BB_TUTOR_IA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Código executado durante a ativação do plugin.
 */
function bb_tutor_ia_activate() {
    // Verificar se BuddyBoss ou BuddyPress está ativo
    if (!function_exists('bp_is_active')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Este plugin requer BuddyBoss ou BuddyPress ativo.', 'bb-tutor-ia'),
            __('Erro de Ativação', 'bb-tutor-ia'),
            ['back_link' => true]
        );
    }
    
    // Criar role 'professor' se não existir
    if (!get_role('professor')) {
        add_role('professor', __('Professor', 'bb-tutor-ia'), [
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'upload_files' => true,
            'bp_moderate' => true
        ]);
    }
    
    // Salvar versão do plugin
    update_option('bb_tutor_ia_version', BB_TUTOR_IA_VERSION);
    
    // Registrar timestamp da instalação/ativação
    if (!get_option('bb_tutor_ia_installed_at')) {
        update_option('bb_tutor_ia_installed_at', current_time('mysql'));
    }
    update_option('bb_tutor_ia_last_updated', current_time('mysql'));
}
register_activation_hook(__FILE__, 'bb_tutor_ia_activate');

/**
 * Código executado durante a desativação do plugin.
 */
function bb_tutor_ia_deactivate() {
    // Limpar cache se necessário
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'bb_tutor_ia_deactivate');

/**
 * Carregar classe core do plugin.
 */
require_once BB_TUTOR_IA_PLUGIN_DIR . 'includes/class-bb-tutor-core.php';

/**
 * Inicializar o plugin.
 */
function bb_tutor_ia_init() {
    // Inicializar instância única da classe core
    BB_Tutor_Core::get_instance();
}
add_action('plugins_loaded', 'bb_tutor_ia_init');

/**
 * Funções auxiliares globais
 */

/**
 * Verifica se usuário pode gerenciar tutor do grupo.
 *
 * Esta função verifica se o usuário atual tem permissão para configurar
 * e gerenciar o tutor IA de um grupo específico do BuddyBoss.
 *
 * Permissões concedidas:
 * - Administradores do WordPress (sempre têm permissão)
 * - Usuários com role 'professor' que são organizadores do grupo
 * - Organizadores (admins) do grupo
 *
 * @since 1.0.0
 *
 * @param int $group_id ID do grupo do BuddyBoss.
 * @return bool True se o usuário pode gerenciar, false caso contrário.
 */
function bb_tutor_user_can_manage($group_id) {
    // Usuários não logados não têm permissão
    if (!is_user_logged_in()) {
        return false;
    }
    
    $user_id = get_current_user_id();
    
    // Administradores sempre podem gerenciar
    if (current_user_can('administrator')) {
        return true;
    }
    
    // Professores que são organizadores do grupo podem gerenciar
    if (current_user_can('professor') && groups_is_user_admin($user_id, $group_id)) {
        return true;
    }
    
    // Organizadores do grupo podem gerenciar
    if (groups_is_user_admin($user_id, $group_id)) {
        return true;
    }
    
    return false;
}

/**
 * Verifica se usuário pode usar o chat do tutor.
 *
 * Esta função verifica se o usuário atual tem permissão para usar
 * o chat do tutor IA de um grupo específico do BuddyBoss.
 *
 * Permissões concedidas:
 * - Membros do grupo (incluindo organizadores e moderadores)
 *
 * @since 1.0.0
 *
 * @param int $group_id ID do grupo do BuddyBoss.
 * @return bool True se o usuário pode usar o chat, false caso contrário.
 */
function bb_tutor_user_can_chat($group_id) {
    // Usuários não logados não têm permissão
    if (!is_user_logged_in()) {
        return false;
    }
    
    // Membros do grupo podem usar o chat
    return groups_is_user_member(get_current_user_id(), $group_id);
}

/**
 * Retorna informações sobre a versão do plugin.
 *
 * @return array Array com informações de versão.
 */
function bb_tutor_get_version_info() {
    $core = BB_Tutor_Core::get_instance();
    return $core->get_version_info();
}

/**
 * Retorna a versão atual do plugin.
 *
 * @return string
 */
function bb_tutor_get_version() {
    return BB_TUTOR_IA_VERSION;
}

/**
 * Shortcode para renderizar o chat do tutor em páginas/posts.
 *
 * Uso: [tutor_ia group_id="123" height="600px"]
 *
 * @since 1.0.0
 *
 * @param array $atts Atributos do shortcode.
 * @return string HTML do chat ou mensagem de erro.
 */
function bb_tutor_shortcode($atts) {
    // Parse dos atributos
    $atts = shortcode_atts([
        'group_id' => 0,
        'height' => '600px'
    ], $atts);
    
    $group_id = absint($atts['group_id']);
    
    // Validar group_id
    if (!$group_id) {
        return '<div class="bb-tutor-error"><p>' . 
               esc_html__('Erro: group_id não especificado no shortcode.', 'bb-tutor-ia') . 
               '</p></div>';
    }
    
    // Verificar se o grupo existe
    $group = groups_get_group($group_id);
    if (!$group || !isset($group->id)) {
        return '<div class="bb-tutor-error"><p>' . 
               esc_html__('Erro: grupo não encontrado.', 'bb-tutor-ia') . 
               '</p></div>';
    }
    
    // Verificar se tutor está ativo
    if (!groups_get_groupmeta($group_id, '_tutor_ia_enabled')) {
        return '<div class="bb-tutor-info"><p>' . 
               esc_html__('O tutor IA não está ativo para este grupo.', 'bb-tutor-ia') . 
               '</p></div>';
    }
    
    // VERIFICAÇÃO DE PERMISSÃO: Verificar se usuário pode usar o chat
    if (!bb_tutor_user_can_chat($group_id)) {
        return '<div class="bb-tutor-error"><p>' . 
               esc_html__('Você não tem permissão para acessar este tutor. Você precisa ser membro do grupo.', 'bb-tutor-ia') . 
               '</p></div>';
    }
    
    // Verificar se store foi criado
    $store_id = groups_get_groupmeta($group_id, '_tutor_ia_store_id');
    if (!$store_id) {
        return '<div class="bb-tutor-warning"><p>' . 
               esc_html__('O tutor ainda está sendo configurado. Tente novamente em alguns instantes.', 'bb-tutor-ia') . 
               '</p></div>';
    }
    
    // Enqueue assets do chat
    wp_enqueue_style('bb-tutor-chat');
    wp_enqueue_script('bb-tutor-chat');
    
    // Gerar HTML do chat
    ob_start();
    ?>
    <div class="bb-tutor-shortcode-wrapper" style="height: <?php echo esc_attr($atts['height']); ?>">
        <div id="bb-tutor-chat-container-<?php echo esc_attr($group_id); ?>" class="bb-tutor-chat-container"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof BBTutorChat !== 'undefined') {
                BBTutorChat.init(
                    <?php echo absint($group_id); ?>, 
                    document.getElementById('bb-tutor-chat-container-<?php echo esc_attr($group_id); ?>')
                );
            } else {
                console.error('BBTutorChat não está carregado');
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('tutor_ia', 'bb_tutor_shortcode');
