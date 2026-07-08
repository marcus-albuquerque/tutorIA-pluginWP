<?php
/**
 * Template da Aba "Tutor IA" no Grupo BuddyBoss
 *
 * Este template renderiza a interface de chat do tutor IA na aba do grupo.
 * É incluído pelo método tab_content() da classe BB_Tutor_Group.
 *
 * Variáveis disponíveis:
 * - $group_id: ID do grupo atual (via bp_get_current_group_id())
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Obter ID do grupo
$group_id = bp_get_current_group_id();

// Obter informações do tutor
$tutor_description = groups_get_groupmeta($group_id, '_tutor_ia_description');
?>

<div class="bb-tutor-tab-wrapper">
    
    <?php if ($tutor_description): ?>
        <div class="bb-tutor-description">
            <p><?php echo esc_html($tutor_description); ?></p>
        </div>
    <?php endif; ?>
    
    <div id="bb-tutor-chat-container" class="bb-tutor-chat-container">
        <!-- Estrutura HTML do Chat -->
        <div class="bb-tutor-chat">
            <!-- Área de Mensagens com Scroll Automático -->
            <div class="bb-tutor-messages" id="bb-tutor-messages">
                <div class="bb-tutor-welcome-message">
                    <p><?php _e('Olá! Sou o tutor de IA deste grupo. Faça suas perguntas sobre o conteúdo da disciplina.', 'bb-tutor-ia'); ?></p>
                </div>
            </div>
            
            <!-- Área de Input com Textarea e Botão Enviar -->
            <div class="bb-tutor-input">
                <textarea 
                    id="bb-tutor-message-input" 
                    placeholder="<?php esc_attr_e('Digite sua pergunta... (Ctrl+Enter para enviar)', 'bb-tutor-ia'); ?>"
                    rows="3"
                    aria-label="<?php esc_attr_e('Campo de mensagem', 'bb-tutor-ia'); ?>"
                ></textarea>
                <button 
                    id="bb-tutor-send-btn" 
                    class="button"
                    aria-label="<?php esc_attr_e('Enviar mensagem', 'bb-tutor-ia'); ?>"
                >
                    <?php _e('Enviar', 'bb-tutor-ia'); ?>
                </button>
            </div>
        </div>
    </div>
    
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof BBTutorChat !== 'undefined') {
            BBTutorChat.init(
                <?php echo absint($group_id); ?>, 
                document.getElementById('bb-tutor-chat-container')
            );
        } else {
            console.error('BBTutorChat não está carregado');
        }
    });
</script>
