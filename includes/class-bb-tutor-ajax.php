<?php
/**
 * Classe de Handlers AJAX do Plugin BuddyBoss Tutores IA
 *
 * Responsável por processar requisições AJAX para upload/exclusão de arquivos
 * e envio de mensagens ao chat do tutor.
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Classe de handlers AJAX.
 */
class BB_Tutor_AJAX {
    
    /**
     * Instância única da classe (Singleton).
     *
     * @var BB_Tutor_AJAX
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
     * @return BB_Tutor_AJAX
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Registra hooks AJAX.
     */
    private function init_hooks() {
        // Handler para envio de mensagens (logado e não logado)
        add_action('wp_ajax_bb_tutor_send_message', [$this, 'send_message']);
        add_action('wp_ajax_nopriv_bb_tutor_send_message', [$this, 'send_message']);
        
        // Handlers para upload e exclusão de arquivos (apenas logado)
        add_action('wp_ajax_bb_tutor_upload_file', [$this, 'upload_file']);
        add_action('wp_ajax_bb_tutor_delete_file', [$this, 'delete_file']);
        
        // Handler para resetar estatísticas (apenas logado)
        add_action('wp_ajax_bb_tutor_reset_stats', [$this, 'reset_stats']);
    }
    
    /**
     * Handler AJAX para envio de mensagens ao chat.
     */
    public function send_message() {
        // Verificar nonce
        check_ajax_referer('bb_tutor_chat', 'nonce');
        
        // Validar parâmetros
        if (!isset($_POST['group_id']) || !isset($_POST['message'])) {
            BB_Tutor_Logger::warning('send_message: Parâmetros inválidos');
            wp_send_json_error([
                'message' => __('Parâmetros inválidos.', 'bb-tutor-ia')
            ]);
        }
        
        $group_id = absint($_POST['group_id']);
        $message = sanitize_textarea_field($_POST['message']);
        
        // Verificar se mensagem não está vazia
        if (empty($message)) {
            wp_send_json_error([
                'message' => __('Mensagem não pode estar vazia.', 'bb-tutor-ia')
            ]);
        }
        
        // VERIFICAÇÃO DE PERMISSÃO: Verificar se usuário pode usar o chat
        if (!bb_tutor_user_can_chat($group_id)) {
            BB_Tutor_Logger::warning('send_message: Permissão negada', ['group_id' => $group_id]);
            wp_send_json_error([
                'message' => __('Você não tem permissão para usar este chat.', 'bb-tutor-ia')
            ]);
        }
        
        // Verificar se tutor está ativo
        if (!groups_get_groupmeta($group_id, '_tutor_ia_enabled')) {
            BB_Tutor_Logger::warning('send_message: Tutor não ativo', ['group_id' => $group_id]);
            wp_send_json_error([
                'message' => __('O tutor não está ativo para este grupo.', 'bb-tutor-ia')
            ]);
        }
        
        // Obter store ID
        $store_id = groups_get_groupmeta($group_id, '_tutor_ia_store_id');
        
        if (!$store_id) {
            BB_Tutor_Logger::error('send_message: Store ID não encontrado', ['group_id' => $group_id]);
            wp_send_json_error([
                'message' => __('O tutor ainda não foi configurado. Entre em contato com o administrador do grupo.', 'bb-tutor-ia')
            ]);
        }
        
        // Verificar se a classe Gemini existe
        if (!class_exists('BB_Tutor_Gemini')) {
            BB_Tutor_Logger::error('send_message: Classe BB_Tutor_Gemini não encontrada');
            wp_send_json_error([
                'message' => __('Erro interno: classe Gemini não encontrada.', 'bb-tutor-ia')
            ]);
        }
        
        // Gerar resposta usando Gemini
        $gemini = new BB_Tutor_Gemini();
        $result = $gemini->generate_content($store_id, $message);
        
        // Verificar se houve erro
        if (isset($result['error'])) {
            BB_Tutor_Logger::error('send_message: Erro ao gerar conteúdo', [
                'group_id' => $group_id,
                'store_id' => $store_id,
                'error' => $result['error']
            ]);
            wp_send_json_error([
                'message' => sprintf(
                    __('Erro ao processar mensagem: %s', 'bb-tutor-ia'),
                    $result['error']
                )
            ]);
        }
        
        // Incrementar contador de uso (se logs estiverem habilitados)
        $settings = get_option('bb_tutor_ia_settings', []);
        if (isset($settings['enable_logs']) && $settings['enable_logs']) {
            $count = (int) groups_get_groupmeta($group_id, '_tutor_ia_usage_count');
            groups_update_groupmeta($group_id, '_tutor_ia_usage_count', $count + 1);
            groups_update_groupmeta($group_id, '_tutor_ia_last_used', current_time('mysql'));
        }
        
        BB_Tutor_Logger::info('send_message: Mensagem processada com sucesso', [
            'group_id' => $group_id,
            'store_id' => $store_id,
            'has_citations' => !empty($result['citations'])
        ]);
        
        // Retornar sucesso com resposta
        wp_send_json_success([
            'response' => $result['response'],
            'citations' => isset($result['citations']) ? $result['citations'] : []
        ]);
    }
    
    /**
     * Handler AJAX para upload de arquivos.
     */
    public function upload_file() {
        // Verificar nonce
        check_ajax_referer('bb_tutor_upload', 'nonce');
        
        // Validar parâmetros
        if (!isset($_POST['group_id'])) {
            BB_Tutor_Logger::warning('upload_file: ID do grupo não especificado');
            wp_send_json_error([
                'message' => __('ID do grupo não especificado.', 'bb-tutor-ia')
            ]);
        }
        
        $group_id = absint($_POST['group_id']);
        
        // VERIFICAÇÃO DE PERMISSÃO: Verificar se usuário pode gerenciar o tutor
        if (!bb_tutor_user_can_manage($group_id)) {
            BB_Tutor_Logger::warning('upload_file: Permissão negada', ['group_id' => $group_id]);
            wp_send_json_error([
                'message' => __('Você não tem permissão para fazer upload de arquivos neste grupo.', 'bb-tutor-ia')
            ]);
        }
        
        // Validar arquivo
        if (!isset($_FILES['file'])) {
            BB_Tutor_Logger::warning('upload_file: Nenhum arquivo enviado', ['group_id' => $group_id]);
            wp_send_json_error([
                'message' => __('Nenhum arquivo foi enviado.', 'bb-tutor-ia')
            ]);
        }
        
        $file = $_FILES['file'];
        
        // Verificar erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            BB_Tutor_Logger::error('upload_file: Erro no upload', [
                'group_id' => $group_id,
                'error_code' => $file['error'],
                'filename' => $file['name']
            ]);
            wp_send_json_error([
                'message' => sprintf(
                    __('Erro no upload: código %d', 'bb-tutor-ia'),
                    $file['error']
                )
            ]);
        }
        
        // Obter configurações
        $settings = get_option('bb_tutor_ia_settings', [
            'max_file_size' => 20971520,
            'allowed_types' => ['application/pdf', 'text/plain']
        ]);
        
        // VALIDAÇÃO DE TIPO DE ARQUIVO (MIME type e extensão)
        $allowed_types = isset($settings['allowed_types']) ? $settings['allowed_types'] : ['application/pdf', 'text/plain'];
        $allowed_extensions = ['pdf', 'txt'];
        
        // Verificar MIME type
        if (!in_array($file['type'], $allowed_types)) {
            BB_Tutor_Logger::warning('upload_file: Tipo de arquivo não permitido', [
                'group_id' => $group_id,
                'filename' => $file['name'],
                'mime_type' => $file['type']
            ]);
            wp_send_json_error([
                'message' => __('Tipo de arquivo não permitido. Apenas PDF e TXT são aceitos.', 'bb-tutor-ia')
            ]);
        }
        
        // Verificar extensão do arquivo (segurança adicional)
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            BB_Tutor_Logger::warning('upload_file: Extensão não permitida', [
                'group_id' => $group_id,
                'filename' => $file['name'],
                'extension' => $file_extension
            ]);
            wp_send_json_error([
                'message' => __('Extensão de arquivo não permitida. Apenas .pdf e .txt são aceitos.', 'bb-tutor-ia')
            ]);
        }
        
        // Validação adicional usando WordPress wp_check_filetype_and_ext
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], ['pdf' => 'application/pdf', 'txt' => 'text/plain']);
        
        if (!$filetype['ext'] || !$filetype['type']) {
            BB_Tutor_Logger::error('upload_file: Arquivo inválido ou corrompido', [
                'group_id' => $group_id,
                'filename' => $file['name']
            ]);
            wp_send_json_error([
                'message' => __('Arquivo inválido ou corrompido. Verifique o arquivo e tente novamente.', 'bb-tutor-ia')
            ]);
        }
        
        // Validar tamanho do arquivo
        $max_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 20971520;
        
        if ($file['size'] > $max_size) {
            $max_size_mb = $max_size / 1048576;
            BB_Tutor_Logger::warning('upload_file: Arquivo muito grande', [
                'group_id' => $group_id,
                'filename' => $file['name'],
                'size' => $file['size'],
                'max_size' => $max_size
            ]);
            wp_send_json_error([
                'message' => sprintf(
                    __('Arquivo muito grande. Tamanho máximo: %d MB', 'bb-tutor-ia'),
                    $max_size_mb
                )
            ]);
        }
        
        // Verificar se a classe Gemini existe
        if (!class_exists('BB_Tutor_Gemini')) {
            BB_Tutor_Logger::error('upload_file: Classe BB_Tutor_Gemini não encontrada');
            wp_send_json_error([
                'message' => __('Erro interno: classe Gemini não encontrada.', 'bb-tutor-ia')
            ]);
        }
        
        // Upload para Gemini
        $gemini = new BB_Tutor_Gemini();
        $result = $gemini->upload_file($file['tmp_name'], $file['type'], $file['name']);
        
        // Verificar se houve erro
        if (isset($result['error'])) {
            BB_Tutor_Logger::error('upload_file: Erro ao fazer upload para Gemini', [
                'group_id' => $group_id,
                'filename' => $file['name'],
                'error' => $result['error']
            ]);
            wp_send_json_error([
                'message' => sprintf(
                    __('Erro ao fazer upload: %s', 'bb-tutor-ia'),
                    $result['error']
                )
            ]);
        }
        
        // Adicionar arquivo ao store
        $store_id = groups_get_groupmeta($group_id, '_tutor_ia_store_id');
        
        if ($store_id) {
            $add_result = $gemini->add_file_to_store($store_id, $result['name']);
            
            if (isset($add_result['error'])) {
                // Se falhou ao adicionar ao store, tentar deletar o arquivo
                $gemini->delete_file($result['name']);
                
                BB_Tutor_Logger::error('upload_file: Erro ao adicionar arquivo ao store', [
                    'group_id' => $group_id,
                    'store_id' => $store_id,
                    'file_id' => $result['name'],
                    'error' => $add_result['error']
                ]);
                wp_send_json_error([
                    'message' => sprintf(
                        __('Erro ao adicionar arquivo ao tutor: %s', 'bb-tutor-ia'),
                        $add_result['error']
                    )
                ]);
            }
        }
        
        // Salvar file ID no group meta
        $files = json_decode(groups_get_groupmeta($group_id, '_tutor_ia_files') ?: '[]', true);
        $files[] = [
            'id' => $result['name'],
            'name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'uploaded_at' => current_time('mysql'),
            'uploaded_by' => get_current_user_id()
        ];
        groups_update_groupmeta($group_id, '_tutor_ia_files', json_encode($files));
        
        BB_Tutor_Logger::info('upload_file: Arquivo enviado com sucesso', [
            'group_id' => $group_id,
            'file_id' => $result['name'],
            'filename' => $file['name'],
            'size' => $file['size']
        ]);
        
        // Retornar sucesso
        wp_send_json_success([
            'file' => [
                'id' => $result['name'],
                'name' => $file['name'],
                'size' => $file['size'],
                'uploaded_at' => current_time('mysql')
            ]
        ]);
    }
    
    /**
     * Handler AJAX para exclusão de arquivos.
     */
    public function delete_file() {
        // Verificar nonce
        check_ajax_referer('bb_tutor_delete', 'nonce');
        
        // Validar parâmetros
        if (!isset($_POST['group_id']) || !isset($_POST['file_id'])) {
            BB_Tutor_Logger::warning('delete_file: Parâmetros inválidos');
            wp_send_json_error([
                'message' => __('Parâmetros inválidos.', 'bb-tutor-ia')
            ]);
        }
        
        $group_id = absint($_POST['group_id']);
        $file_id = sanitize_text_field($_POST['file_id']);
        
        // VERIFICAÇÃO DE PERMISSÃO: Verificar se usuário pode gerenciar o tutor
        if (!bb_tutor_user_can_manage($group_id)) {
            BB_Tutor_Logger::warning('delete_file: Permissão negada', [
                'group_id' => $group_id,
                'file_id' => $file_id
            ]);
            wp_send_json_error([
                'message' => __('Você não tem permissão para excluir arquivos neste grupo.', 'bb-tutor-ia')
            ]);
        }
        
        // Verificar se a classe Gemini existe
        if (!class_exists('BB_Tutor_Gemini')) {
            BB_Tutor_Logger::error('delete_file: Classe BB_Tutor_Gemini não encontrada');
            wp_send_json_error([
                'message' => __('Erro interno: classe Gemini não encontrada.', 'bb-tutor-ia')
            ]);
        }
        
        // Deletar do Gemini
        $gemini = new BB_Tutor_Gemini();
        $deleted = $gemini->delete_file($file_id);
        
        if (!$deleted) {
            BB_Tutor_Logger::error('delete_file: Erro ao deletar arquivo do Gemini', [
                'group_id' => $group_id,
                'file_id' => $file_id
            ]);
        }
        
        // Remover do group meta
        $files = json_decode(groups_get_groupmeta($group_id, '_tutor_ia_files') ?: '[]', true);
        $files = array_filter($files, function($f) use ($file_id) {
            return $f['id'] !== $file_id;
        });
        groups_update_groupmeta($group_id, '_tutor_ia_files', json_encode(array_values($files)));
        
        BB_Tutor_Logger::info('delete_file: Arquivo excluído com sucesso', [
            'group_id' => $group_id,
            'file_id' => $file_id
        ]);
        
        // Retornar sucesso
        wp_send_json_success([
            'message' => __('Arquivo excluído com sucesso.', 'bb-tutor-ia')
        ]);
    }
    
    /**
     * Handler AJAX para resetar estatísticas de uso.
     */
    public function reset_stats() {
        // Verificar nonce
        check_ajax_referer('bb_tutor_reset_stats', 'nonce');
        
        // Validar parâmetros
        if (!isset($_POST['group_id'])) {
            wp_send_json_error([
                'message' => __('ID do grupo não especificado.', 'bb-tutor-ia')
            ]);
        }
        
        $group_id = absint($_POST['group_id']);
        
        // VERIFICAÇÃO DE PERMISSÃO: Verificar se usuário pode gerenciar o tutor
        if (!bb_tutor_user_can_manage($group_id)) {
            wp_send_json_error([
                'message' => __('Você não tem permissão para resetar estatísticas neste grupo.', 'bb-tutor-ia')
            ]);
        }
        
        // Resetar contador e timestamp
        groups_update_groupmeta($group_id, '_tutor_ia_usage_count', 0);
        groups_delete_groupmeta($group_id, '_tutor_ia_last_used');
        
        // Retornar sucesso
        wp_send_json_success([
            'message' => __('Estatísticas resetadas com sucesso!', 'bb-tutor-ia')
        ]);
    }
}
