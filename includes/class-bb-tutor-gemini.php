<?php
/**
 * Gemini API Integration Class
 *
 * Handles all interactions with Google Gemini API including:
 * - File uploads (PDF, TXT)
 * - FileSearchStore creation and management
 * - Content generation with RAG
 * - File deletion
 *
 * @package BuddyBoss_Tutores_IA
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BB_Tutor_Gemini
 *
 * Manages all Gemini API operations using WordPress HTTP API
 */
class BB_Tutor_Gemini {
    
    /**
     * Gemini API Key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Gemini API Base URL
     *
     * @var string
     */
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta';
    
    /**
     * Constructor
     *
     * Initializes the class and retrieves API key from WordPress options
     */
    public function __construct() {
        $this->api_key = get_option('bb_tutor_ia_gemini_api_key');
    }
    
    /**
     * Create a FileSearchStore (Corpus) in Gemini
     *
     * @param string $display_name Display name for the store
     * @return array Response array with 'name' key on success or 'error' key on failure
     */
    public function create_store($display_name) {
        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('create_store', 'API key is not configured');
            return ['error' => 'API do Gemini não está configurada'];
        }
        
        // Validate display name
        if (empty($display_name)) {
            $this->log_error('create_store', 'Display name is empty');
            return ['error' => 'Nome do store é obrigatório'];
        }
        
        // Use FileSearchStores API instead of corpora
        $url = $this->base_url . '/fileSearchStores?key=' . $this->api_key;
        
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'displayName' => $display_name
            ]),
            'timeout' => 30,
            'redirection' => 0
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error('create_store', "Request failed: {$error_message}");
            
            if (strpos($error_message, 'timeout') !== false) {
                return ['error' => 'Timeout ao criar store. Tente novamente.'];
            }
            
            return ['error' => 'Falha ao criar store: ' . $error_message];
        }
        
        // Check HTTP status
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            $this->log_error('create_store', "HTTP error: {$status_code}");
            
            if ($status_code === 401 || $status_code === 403) {
                return ['error' => 'Chave API inválida ou sem permissão'];
            }
            
            if ($status_code === 429) {
                return ['error' => 'Limite de requisições excedido. Aguarde alguns instantes.'];
            }
            
            if ($status_code >= 500) {
                return ['error' => 'Erro no servidor do Gemini. Tente novamente mais tarde.'];
            }
        }
        
        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        
        if (empty($response_body)) {
            $this->log_error('create_store', 'Empty response body');
            return ['error' => 'Resposta vazia do servidor'];
        }
        
        $body = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('create_store', 'Failed to decode JSON: ' . json_last_error_msg());
            return ['error' => 'Resposta inválida do servidor'];
        }
        
        // Check for API errors
        if (isset($body['error'])) {
            $api_error = $body['error']['message'] ?? 'Erro desconhecido';
            $this->log_error('create_store', "API error: {$api_error}");
            return ['error' => 'Erro da API Gemini: ' . $api_error];
        }
        
        // Validate response has store name
        if (!isset($body['name'])) {
            $this->log_error('create_store', 'No store name in response');
            return ['error' => 'Resposta inválida: store name não encontrado'];
        }
        
        return $body;
    }
    
    /**
     * Upload file to Gemini using resumable upload protocol
     *
     * @param string $file_path Path to the file on server
     * @param string $mime_type MIME type of the file
     * @param string $display_name Display name for the file in Gemini
     * @return array Response array with file data on success or 'error' key on failure
     */
    public function upload_file($file_path, $mime_type, $display_name) {
        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('upload_file', 'API key is not configured');
            return ['error' => 'API do Gemini não está configurada'];
        }
        
        // Validate file path
        if (empty($file_path) || !file_exists($file_path)) {
            $this->log_error('upload_file', "File does not exist: {$file_path}");
            return ['error' => 'Arquivo não encontrado'];
        }
        
        // Validate file is readable
        if (!is_readable($file_path)) {
            $this->log_error('upload_file', "File is not readable: {$file_path}");
            return ['error' => 'Não foi possível ler o arquivo'];
        }
        
        // Use the upload endpoint for resumable uploads
        $upload_base_url = 'https://generativelanguage.googleapis.com/upload/v1beta/files';
        
        // Read file content
        $file_content = @file_get_contents($file_path);
        
        if ($file_content === false) {
            $this->log_error('upload_file', "Failed to read file content: {$file_path}");
            return ['error' => 'Falha ao ler o conteúdo do arquivo'];
        }
        
        $file_size = strlen($file_content);
        
        // Validate file size (Gemini has limits)
        if ($file_size === 0) {
            $this->log_error('upload_file', 'File is empty');
            return ['error' => 'O arquivo está vazio'];
        }
        
        if ($file_size > 20971520) { // 20MB
            $this->log_error('upload_file', "File too large: {$file_size} bytes");
            return ['error' => 'Arquivo muito grande (máximo 20MB)'];
        }
        
        // Step 1: Initialize resumable upload
        $init_response = wp_remote_post($upload_base_url . '?key=' . $this->api_key, [
            'headers' => [
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => $file_size,
                'X-Goog-Upload-Header-Content-Type' => $mime_type,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'file' => [
                    'display_name' => $display_name
                ]
            ]),
            'timeout' => 30,
            'redirection' => 0
        ]);
        
        if (is_wp_error($init_response)) {
            $error_message = $init_response->get_error_message();
            $this->log_error('upload_file', "Init upload failed: {$error_message}");
            
            if (strpos($error_message, 'timeout') !== false) {
                return ['error' => 'Timeout ao iniciar upload. Tente novamente.'];
            }
            
            return ['error' => 'Falha ao iniciar upload: ' . $error_message];
        }
        
        // Check HTTP status
        $init_status = wp_remote_retrieve_response_code($init_response);
        
        if ($init_status >= 400) {
            $this->log_error('upload_file', "Init upload HTTP error: {$init_status}");
            
            if ($init_status === 401 || $init_status === 403) {
                return ['error' => 'Chave API inválida ou sem permissão'];
            }
            
            if ($init_status === 429) {
                return ['error' => 'Limite de uploads excedido. Aguarde alguns instantes.'];
            }
            
            return ['error' => "Erro ao iniciar upload (HTTP {$init_status})"];
        }
        
        // Get upload URL from response headers (case-insensitive)
        $upload_url = wp_remote_retrieve_header($init_response, 'x-goog-upload-url');
        
        // Try alternative case variations if not found
        if (!$upload_url) {
            $upload_url = wp_remote_retrieve_header($init_response, 'X-Goog-Upload-URL');
        }
        if (!$upload_url) {
            $upload_url = wp_remote_retrieve_header($init_response, 'X-Goog-Upload-Url');
        }
        
        // If still not found, log all headers for debugging
        if (!$upload_url) {
            $all_headers = wp_remote_retrieve_headers($init_response);
            $this->log_error('upload_file', 'No upload URL in response headers. Available headers: ' . print_r($all_headers, true));
            return ['error' => 'Falha ao obter URL de upload do Gemini'];
        }
        
        // Step 2: Upload file content
        $upload_response = wp_remote_post($upload_url, [
            'headers' => [
                'Content-Length' => $file_size,
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize'
            ],
            'body' => $file_content,
            'timeout' => 120, // Extended timeout for large files
            'redirection' => 0
        ]);
        
        if (is_wp_error($upload_response)) {
            $error_message = $upload_response->get_error_message();
            $this->log_error('upload_file', "File upload failed: {$error_message}");
            
            if (strpos($error_message, 'timeout') !== false) {
                return ['error' => 'Timeout durante upload. O arquivo pode ser muito grande ou a conexão está lenta.'];
            }
            
            return ['error' => 'Falha no upload: ' . $error_message];
        }
        
        // Check HTTP status
        $upload_status = wp_remote_retrieve_response_code($upload_response);
        if ($upload_status >= 400) {
            $this->log_error('upload_file', "Upload HTTP error: {$upload_status}");
            
            if ($upload_status === 413) {
                return ['error' => 'Arquivo muito grande para o servidor'];
            }
            
            if ($upload_status >= 500) {
                return ['error' => 'Erro no servidor do Gemini. Tente novamente mais tarde.'];
            }
            
            return ['error' => "Erro no upload (HTTP {$upload_status})"];
        }
        
        // Parse response
        $response_body = wp_remote_retrieve_body($upload_response);
        
        if (empty($response_body)) {
            $this->log_error('upload_file', 'Empty response body from upload');
            return ['error' => 'Resposta vazia do servidor'];
        }
        
        $body = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('upload_file', 'Failed to decode JSON: ' . json_last_error_msg());
            return ['error' => 'Resposta inválida do servidor'];
        }
        
        // Check for API errors
        if (isset($body['error'])) {
            $api_error = $body['error']['message'] ?? 'Erro desconhecido';
            $this->log_error('upload_file', "API error: {$api_error}");
            return ['error' => 'Erro da API Gemini: ' . $api_error];
        }
        
        // Validate response structure
        if (isset($body['file']) && is_array($body['file'])) {
            return $body['file'];
        }
        
        $this->log_error('upload_file', 'Invalid response structure');
        return ['error' => 'Resposta inválida do Gemini'];
    }
    
    /**
     * Get file status from Gemini
     *
     * @param string $file_id File ID (e.g., 'files/abc')
     * @return array Response array with file data or 'error' key on failure
     */
    public function get_file_status($file_id) {
        if (empty($this->api_key)) {
            return ['error' => 'API key not configured'];
        }
        
        if (empty($file_id)) {
            return ['error' => 'File ID is required'];
        }
        
        $url = $this->base_url . '/' . $file_id . '?key=' . $this->api_key;
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 0
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            return ['error' => "HTTP {$status_code}"];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['error' => $body['error']['message'] ?? 'Unknown error'];
        }
        
        return $body;
    }
    
    /**
     * Add a file to a FileSearchStore (Corpus)
     *
     * @param string $store_id Store ID (e.g., 'corpora/xyz123')
     * @param string $file_id File ID (e.g., 'files/abc')
     * @return array Response array on success or 'error' key on failure
     */
    public function add_file_to_store($store_id, $file_id) {
        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('add_file_to_store', 'API key is not configured');
            return ['error' => 'API do Gemini não está configurada'];
        }
        
        // Validate inputs
        if (empty($store_id)) {
            $this->log_error('add_file_to_store', 'Store ID is empty');
            return ['error' => 'Store ID é obrigatório'];
        }
        
        if (empty($file_id)) {
            $this->log_error('add_file_to_store', 'File ID is empty');
            return ['error' => 'File ID é obrigatório'];
        }
        
        // Wait for file to be ACTIVE (max 30 seconds)
        $max_attempts = 15;
        $attempt = 0;
        $file_state = null;
        
        while ($attempt < $max_attempts) {
            $file_status = $this->get_file_status($file_id);
            
            if (isset($file_status['error'])) {
                $this->log_error('add_file_to_store', "Failed to get file status: {$file_status['error']}");
                return ['error' => 'Falha ao verificar status do arquivo'];
            }
            
            $file_state = $file_status['state'] ?? null;
            
            if ($file_state === 'ACTIVE') {
                break;
            }
            
            if ($file_state === 'FAILED') {
                $this->log_error('add_file_to_store', 'File processing failed');
                return ['error' => 'Falha no processamento do arquivo'];
            }
            
            $attempt++;
            sleep(2); // Wait 2 seconds before next check
        }
        
        if ($file_state !== 'ACTIVE') {
            $this->log_error('add_file_to_store', "File not active after {$max_attempts} attempts. State: {$file_state}");
            return ['error' => 'Timeout aguardando processamento do arquivo'];
        }
        
        // Use the correct endpoint based on store type
        // FileSearchStores use importFile method - using file_name (snake_case)
        $url = $this->base_url . '/' . $store_id . ':importFile?key=' . $this->api_key;
        
        $payload = ['file_name' => $file_id];
        
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload),
            'timeout' => 30,
            'redirection' => 0
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error('add_file_to_store', "Request failed: {$error_message}");
            
            if (strpos($error_message, 'timeout') !== false) {
                return ['error' => 'Timeout ao adicionar arquivo ao store. Tente novamente.'];
            }
            
            return ['error' => 'Falha ao adicionar arquivo: ' . $error_message];
        }
        
        // Check HTTP status
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Log response body for debugging only on errors
        if ($status_code >= 400) {
            $response_body = wp_remote_retrieve_body($response);
            $this->log_error('add_file_to_store', "HTTP error {$status_code}: {$response_body}");
            
            if ($status_code === 401 || $status_code === 403) {
                return ['error' => 'Chave API inválida ou sem permissão'];
            }
            
            if ($status_code === 404) {
                return ['error' => 'Store ou arquivo não encontrado'];
            }
            
            if ($status_code === 429) {
                return ['error' => 'Limite de requisições excedido. Aguarde alguns instantes.'];
            }
            
            if ($status_code >= 500) {
                return ['error' => 'Erro no servidor do Gemini. Tente novamente mais tarde.'];
            }
        }
        
        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        
        if (empty($response_body)) {
            $this->log_error('add_file_to_store', 'Empty response body');
            return ['error' => 'Resposta vazia do servidor'];
        }
        
        $body = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('add_file_to_store', 'Failed to decode JSON: ' . json_last_error_msg());
            return ['error' => 'Resposta inválida do servidor'];
        }
        
        // Check for API errors
        if (isset($body['error'])) {
            $api_error = $body['error']['message'] ?? 'Erro desconhecido';
            $this->log_error('add_file_to_store', "API error: {$api_error}");
            return ['error' => 'Erro da API Gemini: ' . $api_error];
        }
        
        return $body;
    }
    
    /**
     * Delete a file from Gemini
     *
     * @param string $file_id File ID (e.g., 'files/abc')
     * @return bool True on success, false on failure
     */
    public function delete_file($file_id) {
        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('delete_file', 'API key is not configured');
            return false;
        }
        
        // Validate file ID
        if (empty($file_id)) {
            $this->log_error('delete_file', 'File ID is empty');
            return false;
        }
        
        $url = $this->base_url . '/' . $file_id . '?key=' . $this->api_key;
        
        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'timeout' => 30,
            'redirection' => 0
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error('delete_file', "Request failed: {$error_message}");
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Success codes: 200 OK, 204 No Content, or 404 (already deleted)
        if (in_array($status_code, [200, 204, 404])) {
            return true;
        }
        
        // Log other errors
        $this->log_error('delete_file', "HTTP error: {$status_code}");
        
        // Check for API error message
        $response_body = wp_remote_retrieve_body($response);
        if (!empty($response_body)) {
            $body = json_decode($response_body, true);
            if (isset($body['error']['message'])) {
                $this->log_error('delete_file', "API error: {$body['error']['message']}");
            }
        }
        
        return false;
    }
    
    /**
     * Generate content with RAG using FileSearchStore
     *
     * @param string $store_id Store ID (e.g., 'corpora/xyz123')
     * @param string $prompt User's question/prompt
     * @return array Response array with 'response' and 'citations' keys on success or 'error' key on failure
     */
    public function generate_content($store_id, $prompt) {
        // Validate inputs
        if (empty($store_id)) {
            $this->log_error('generate_content', 'Store ID is empty');
            return ['error' => 'Configuração do tutor inválida. Entre em contato com o professor.'];
        }
        
        if (empty($prompt)) {
            return ['error' => 'Por favor, digite uma pergunta.'];
        }
        
        // Validate API key
        if (empty($this->api_key)) {
            $this->log_error('generate_content', 'API key is not configured');
            return ['error' => 'API do Gemini não está configurada. Entre em contato com o administrador.'];
        }
        
        // Sanitize prompt (limit length to prevent abuse)
        $prompt = trim($prompt);
        if (strlen($prompt) > 5000) {
            return ['error' => 'Sua pergunta é muito longa. Por favor, seja mais conciso (máximo 5000 caracteres).'];
        }
        
        // Use gemini-2.5-flash model with generateContent endpoint (supports file search)
        $url = $this->base_url . '/models/gemini-2.5-flash:generateContent?key=' . $this->api_key;
        
        // Build request payload with FileSearchStore (REST API uses snake_case)
        // Use stdClass to ensure proper JSON object encoding
        $file_search_config = new stdClass();
        $file_search_config->file_search_store_names = [$store_id];
        
        $tool = new stdClass();
        $tool->file_search = $file_search_config;
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'tools' => [$tool],
            'systemInstruction' => [
                'parts' => [
                    [
                        'text' => 'Você é um tutor educacional especializado. Use o contexto fornecido pelos documentos para responder perguntas de forma clara, educativa e precisa. Se a pergunta não puder ser respondida com base nos documentos disponíveis, informe educadamente que o conteúdo está fora do escopo dos materiais fornecidos.'
                    ]
                ]
            ]
        ];
        
        // Encode payload and validate JSON
        $json_payload = json_encode($payload);
        if ($json_payload === false) {
            $this->log_error('generate_content', 'Failed to encode JSON payload: ' . json_last_error_msg());
            return ['error' => 'Erro ao processar sua pergunta. Por favor, tente novamente.'];
        }
        
        // Make API request with extended timeout for content generation
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $json_payload,
            'timeout' => 60,
            'redirection' => 0 // Disable redirects for security
        ]);
        
        // Handle connection errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            
            // Log detailed error for debugging
            $this->log_error('generate_content', "WP_Error: [{$error_code}] {$error_message}");
            
            // Provide user-friendly error messages based on error type
            if ($error_code === 'http_request_failed') {
                if (strpos($error_message, 'timeout') !== false || strpos($error_message, 'timed out') !== false) {
                    return ['error' => 'A requisição demorou muito tempo. O servidor pode estar sobrecarregado. Por favor, tente novamente em alguns instantes.'];
                }
                if (strpos($error_message, 'Could not resolve host') !== false) {
                    return ['error' => 'Não foi possível conectar ao servidor do Gemini. Verifique sua conexão com a internet.'];
                }
                if (strpos($error_message, 'SSL') !== false || strpos($error_message, 'certificate') !== false) {
                    return ['error' => 'Erro de segurança na conexão. Entre em contato com o administrador.'];
                }
            }
            
            return ['error' => 'Erro de conexão com o servidor. Por favor, tente novamente.'];
        }
        
        // Get HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Handle HTTP errors
        if ($status_code >= 500) {
            $this->log_error('generate_content', "Server error: HTTP {$status_code}");
            return ['error' => 'O servidor do Gemini está temporariamente indisponível. Por favor, tente novamente em alguns minutos.'];
        }
        
        if ($status_code >= 400 && $status_code < 500) {
            $this->log_error('generate_content', "Client error: HTTP {$status_code}");
        }
        
        // Get response body
        $response_body = wp_remote_retrieve_body($response);
        
        // Validate response body is not empty
        if (empty($response_body)) {
            $this->log_error('generate_content', 'Empty response body from API');
            return ['error' => 'Resposta vazia do servidor. Por favor, tente novamente.'];
        }
        
        // Parse response body
        $body = json_decode($response_body, true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error('generate_content', 'Failed to decode JSON response: ' . json_last_error_msg());
            return ['error' => 'Resposta inválida do servidor. Por favor, tente novamente.'];
        }
        
        // Validate body is an array
        if (!is_array($body)) {
            $this->log_error('generate_content', 'Response body is not an array');
            return ['error' => 'Formato de resposta inválido. Por favor, tente novamente.'];
        }
        
        // Check for API errors
        if (isset($body['error'])) {
            $api_error = $body['error']['message'] ?? 'Erro desconhecido da API';
            $error_code = $body['error']['code'] ?? null;
            $error_status = $body['error']['status'] ?? null;
            
            // Log detailed API error
            $this->log_error('generate_content', "API Error: [{$error_code}] {$error_status} - {$api_error}");
            
            // Handle specific API error codes
            if ($error_code === 429 || $error_status === 'RESOURCE_EXHAUSTED') {
                return ['error' => 'Limite de requisições excedido. Aguarde alguns instantes e tente novamente.'];
            }
            
            if ($error_code === 401 || $error_status === 'UNAUTHENTICATED') {
                return ['error' => 'Chave API inválida. Entre em contato com o administrador.'];
            }
            
            if ($error_code === 403 || $error_status === 'PERMISSION_DENIED') {
                return ['error' => 'Acesso negado pela API. Verifique as permissões da chave API.'];
            }
            
            if ($error_code === 404 || $error_status === 'NOT_FOUND') {
                return ['error' => 'Store não encontrado. O tutor pode não estar configurado corretamente. Entre em contato com o professor.'];
            }
            
            if ($error_code === 400 || $error_status === 'INVALID_ARGUMENT') {
                return ['error' => 'Requisição inválida. Por favor, reformule sua pergunta.'];
            }
            
            if ($error_status === 'DEADLINE_EXCEEDED') {
                return ['error' => 'O processamento demorou muito tempo. Por favor, tente uma pergunta mais simples.'];
            }
            
            // Generic API error
            return ['error' => 'Erro da API Gemini: ' . $api_error];
        }
        
        // Validate response structure
        if (!isset($body['candidates']) || !is_array($body['candidates']) || empty($body['candidates'])) {
            $this->log_error('generate_content', 'No candidates in response');
            return ['error' => 'Nenhuma resposta foi gerada. Por favor, tente reformular sua pergunta.'];
        }
        
        $candidate = $body['candidates'][0];
        
        // Handle blocked or filtered responses
        if (isset($candidate['finishReason'])) {
            $finish_reason = $candidate['finishReason'];
            
            if ($finish_reason === 'SAFETY') {
                $this->log_error('generate_content', 'Response blocked by safety filters');
                return ['error' => 'A resposta foi bloqueada por questões de segurança. Por favor, reformule sua pergunta de forma mais apropriada.'];
            }
            
            if ($finish_reason === 'RECITATION') {
                $this->log_error('generate_content', 'Response blocked due to recitation');
                return ['error' => 'A resposta foi bloqueada por conter conteúdo protegido por direitos autorais. Por favor, reformule sua pergunta.'];
            }
            
            if ($finish_reason === 'MAX_TOKENS') {
                $this->log_error('generate_content', 'Response truncated due to max tokens');
                // This is not necessarily an error, but we should inform the user
            }
            
            if ($finish_reason === 'OTHER') {
                $this->log_error('generate_content', 'Response stopped for unknown reason');
            }
        }
        
        // Extract response text
        if (!isset($candidate['content']['parts']) || !is_array($candidate['content']['parts']) || empty($candidate['content']['parts'])) {
            $this->log_error('generate_content', 'No content parts in candidate');
            return ['error' => 'Resposta vazia do servidor. Por favor, tente novamente.'];
        }
        
        // Get the first text part
        $text_part = null;
        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $text_part = $part['text'];
                break;
            }
        }
        
        if ($text_part === null || trim($text_part) === '') {
            $this->log_error('generate_content', 'No text content in response parts');
            return ['error' => 'Nenhum conteúdo de texto foi gerado. Por favor, tente novamente.'];
        }
        
        // Extract citation metadata if available
        $citations = [];
        if (isset($candidate['citationMetadata']['citationSources']) && is_array($candidate['citationMetadata']['citationSources'])) {
            $citations = $candidate['citationMetadata']['citationSources'];
        }
        
        // Return successful response
        return [
            'response' => trim($text_part),
            'citations' => $citations
        ];
    }
    
    /**
     * Log error messages for debugging
     *
     * @param string $method Method name where error occurred
     * @param string $message Error message
     * @return void
     */
    private function log_error($method, $message) {
        // Use BB_Tutor_Logger if available
        if (class_exists('BB_Tutor_Logger')) {
            BB_Tutor_Logger::error("BB_Tutor_Gemini::{$method} - {$message}");
        } else {
            // Fallback to error_log if logger not available
            error_log("BB_Tutor_Gemini::{$method} - {$message}");
        }
    }
}
