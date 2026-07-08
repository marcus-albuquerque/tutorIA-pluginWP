<?php
/**
 * Template para configurações do Tutor IA nas configurações do grupo
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}
?>

<div class="bb-tutor-group-settings">
    <h3><?php _e('Tutor IA', 'bb-tutor-ia'); ?></h3>
    
    <fieldset>
        <legend class="screen-reader-text"><?php _e('Configurações do Tutor IA', 'bb-tutor-ia'); ?></legend>
        
        <!-- Ativar/Desativar Tutor -->
        <div class="field-group">
            <label for="tutor-ia-enabled">
                <input 
                    type="checkbox" 
                    id="tutor-ia-enabled" 
                    name="tutor_ia_enabled" 
                    value="1" 
                    <?php checked($enabled, '1'); ?>
                />
                <?php _e('Ativar Tutor IA para este grupo', 'bb-tutor-ia'); ?>
            </label>
            <p class="description">
                <?php _e('Quando ativado, uma nova aba "Tutor IA" será exibida no grupo para os membros.', 'bb-tutor-ia'); ?>
            </p>
        </div>
        
        <!-- Block ID -->
        <div class="field-group">
            <label for="tutor-ia-block-id">
                <?php _e('Block ID da Disciplina', 'bb-tutor-ia'); ?>
            </label>
            <input 
                type="text" 
                id="tutor-ia-block-id" 
                name="tutor_ia_block_id" 
                value="<?php echo esc_attr($block_id); ?>" 
                class="regular-text"
                placeholder="BLOCK123"
            />
            <p class="description">
                <?php _e('Identificador único da disciplina no sistema Infnet.', 'bb-tutor-ia'); ?>
            </p>
        </div>
        
        <!-- Descrição do Tutor -->
        <div class="field-group">
            <label for="tutor-ia-description">
                <?php _e('Descrição do Tutor', 'bb-tutor-ia'); ?>
            </label>
            <textarea 
                id="tutor-ia-description" 
                name="tutor_ia_description" 
                rows="3" 
                class="large-text"
                placeholder="<?php esc_attr_e('Ex: Tutor especializado em Matemática Aplicada', 'bb-tutor-ia'); ?>"
            ><?php echo esc_textarea($description); ?></textarea>
            <p class="description">
                <?php _e('Breve descrição sobre o tutor e a disciplina.', 'bb-tutor-ia'); ?>
            </p>
        </div>
        
        <?php if ($store_id): ?>
            <!-- Informações do Store -->
            <div class="field-group bb-tutor-store-info">
                <p>
                    <strong><?php _e('Store ID:', 'bb-tutor-ia'); ?></strong>
                    <code><?php echo esc_html($store_id); ?></code>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($enabled === '1'): ?>
            <!-- Estatísticas de Uso -->
            <div class="field-group bb-tutor-stats">
                <h4><?php _e('Estatísticas de Uso', 'bb-tutor-ia'); ?></h4>
                <div class="bb-tutor-stats-content">
                    <p>
                        <strong><?php _e('Total de consultas:', 'bb-tutor-ia'); ?></strong>
                        <span class="bb-tutor-usage-count"><?php echo esc_html($usage_count); ?></span>
                    </p>
                    <?php if ($last_used): ?>
                        <p>
                            <strong><?php _e('Última consulta:', 'bb-tutor-ia'); ?></strong>
                            <span class="bb-tutor-last-used"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_used))); ?></span>
                        </p>
                    <?php else: ?>
                        <p>
                            <strong><?php _e('Última consulta:', 'bb-tutor-ia'); ?></strong>
                            <span class="bb-tutor-last-used"><?php _e('Nenhuma consulta ainda', 'bb-tutor-ia'); ?></span>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($usage_count > 0): ?>
                        <div class="bb-tutor-stats-actions">
                            <button 
                                type="button" 
                                class="button button-secondary bb-tutor-reset-stats" 
                                data-group-id="<?php echo esc_attr($group_id); ?>"
                                title="<?php esc_attr_e('Resetar contador de consultas', 'bb-tutor-ia'); ?>"
                            >
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Resetar Contador', 'bb-tutor-ia'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Seção de Upload de Documentos -->
            <div class="field-group bb-tutor-files-section">
                <h4><?php _e('Documentos do Tutor', 'bb-tutor-ia'); ?></h4>
                <p class="description">
                    <?php _e('Envie documentos em PDF ou TXT para alimentar o conhecimento do tutor. Os arquivos serão processados pela IA e usados para responder perguntas dos alunos.', 'bb-tutor-ia'); ?>
                </p>
                
                <!-- Upload de Arquivo -->
                <div class="bb-tutor-upload-area">
                    <div class="bb-tutor-upload-controls">
                        <label for="bb-tutor-file-upload" class="button button-secondary">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Escolher Arquivo', 'bb-tutor-ia'); ?>
                        </label>
                        <input 
                            type="file" 
                            id="bb-tutor-file-upload" 
                            accept=".pdf,.txt,application/pdf,text/plain" 
                            style="display: none;"
                            data-group-id="<?php echo esc_attr($group_id); ?>"
                            data-max-size="20971520"
                        />
                        <span class="bb-tutor-selected-file"></span>
                    </div>
                    
                    <div class="bb-tutor-upload-info">
                        <p class="description">
                            <strong><?php _e('Requisitos:', 'bb-tutor-ia'); ?></strong><br>
                            • <?php _e('Formatos aceitos: PDF (.pdf) e Texto (.txt)', 'bb-tutor-ia'); ?><br>
                            • <?php _e('Tamanho máximo: 20MB por arquivo', 'bb-tutor-ia'); ?><br>
                            • <?php _e('O arquivo será processado automaticamente após o upload', 'bb-tutor-ia'); ?>
                        </p>
                    </div>
                    
                    <div class="bb-tutor-upload-status" style="display: none;">
                        <div class="bb-tutor-upload-progress">
                            <div class="bb-tutor-progress-bar">
                                <div class="bb-tutor-progress-fill" style="width: 0%;"></div>
                            </div>
                            <span class="bb-tutor-progress-text"></span>
                        </div>
                    </div>
                    
                    <div class="bb-tutor-upload-messages">
                        <!-- Mensagens de sucesso/erro serão inseridas aqui via JavaScript -->
                    </div>
                </div>
                
                <!-- Lista de Arquivos Enviados -->
                <div class="bb-tutor-files-list-container">
                    <h5><?php _e('Arquivos Enviados', 'bb-tutor-ia'); ?></h5>
                    
                    <?php if (!empty($files)): ?>
                        <table class="bb-tutor-files-table widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Nome do Arquivo', 'bb-tutor-ia'); ?></th>
                                    <th><?php _e('Data de Upload', 'bb-tutor-ia'); ?></th>
                                    <th><?php _e('Ações', 'bb-tutor-ia'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr data-file-id="<?php echo esc_attr($file['id']); ?>">
                                        <td class="file-name">
                                            <span class="dashicons dashicons-media-document"></span>
                                            <?php echo esc_html($file['name']); ?>
                                        </td>
                                        <td class="file-date">
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($file['uploaded_at']))); ?>
                                        </td>
                                        <td class="file-actions">
                                            <button 
                                                type="button" 
                                                class="button button-small bb-tutor-delete-file" 
                                                data-file-id="<?php echo esc_attr($file['id']); ?>"
                                                data-file-name="<?php echo esc_attr($file['name']); ?>"
                                                data-group-id="<?php echo esc_attr($group_id); ?>"
                                                title="<?php esc_attr_e('Excluir arquivo', 'bb-tutor-ia'); ?>"
                                            >
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e('Excluir', 'bb-tutor-ia'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="bb-tutor-no-files">
                            <p class="description">
                                <span class="dashicons dashicons-info"></span>
                                <?php _e('Nenhum documento enviado ainda. Envie o primeiro arquivo para começar a treinar o tutor.', 'bb-tutor-ia'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php 
            // Nonce específico para upload e exclusão de arquivos
            wp_nonce_field('bb_tutor_upload', 'bb_tutor_upload_nonce', false); 
            wp_nonce_field('bb_tutor_delete', 'bb_tutor_delete_nonce', false);
            wp_nonce_field('bb_tutor_reset_stats', 'bb_tutor_reset_stats_nonce', false);
            ?>
        <?php endif; ?>
        
        <?php wp_nonce_field('bb_tutor_settings', 'bb_tutor_nonce'); ?>
    </fieldset>
</div>
