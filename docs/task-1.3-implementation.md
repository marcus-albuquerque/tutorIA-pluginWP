# Task 1.3 Implementation - Página de Configurações Globais

## Resumo

Implementação completa da página de configurações globais do plugin BuddyBoss Tutores IA, incluindo gerenciamento da API Key do Google Gemini e configurações gerais.

## Arquivos Criados/Modificados

### 1. `includes/class-bb-tutor-admin.php` (NOVO)

Classe principal de administração do plugin com as seguintes funcionalidades:

#### Funcionalidades Implementadas:

- **Padrão Singleton**: Garante uma única instância da classe
- **Menu no Admin**: Adiciona menu "Tutores IA" no WordPress admin
- **Página de Configurações**: Interface completa para gerenciar configurações
- **Gerenciamento de API Key**:
  - Campo de input com tipo password
  - Botão para mostrar/ocultar a chave
  - Máscara de segurança (mostra apenas primeiros e últimos 4 caracteres)
  - Validação de formato (regex para chaves do Google)
  - Validação online (testa a chave com a API do Gemini)
  - Cache de validação (1 hora) para evitar requisições desnecessárias
  - Mensagens de erro detalhadas
- **Configurações Gerais**:
  - Tamanho máximo de arquivo (1-100 MB, padrão 20MB)
  - Habilitar/desabilitar logs de uso
  - Tipos de arquivo permitidos (PDF, TXT)
- **Link de Configurações**: Adiciona link direto na página de plugins
- **Informações do Plugin**: Exibe versão e última atualização
- **Guia de Uso**: Instruções para obter API Key do Google

#### Métodos Principais:

- `get_instance()`: Retorna instância singleton
- `add_admin_menu()`: Adiciona menu no admin
- `register_settings()`: Registra configurações no WordPress
- `render_settings_page()`: Renderiza página de configurações
- `sanitize_api_key()`: Valida e sanitiza API Key
- `validate_api_key()`: Testa API Key com a API do Gemini
- `check_api_key_status()`: Verifica status da chave (com cache)
- `get_api_key()`: Retorna API Key armazenada
- `get_settings()`: Retorna configurações gerais

#### Segurança:

- Sanitização de todos os inputs
- Validação de permissões (`manage_options`)
- Uso de nonces (via Settings API)
- Escape de outputs
- Validação de formato e online da API Key

### 2. `assets/css/admin.css` (MODIFICADO)

Adicionados estilos específicos para a página de configurações:

#### Estilos Adicionados:

- Layout da página de configurações
- Formulário de configurações
- Campos de input (password, text, number)
- Botões e notices
- Box de informações
- Responsividade para mobile
- Estilos para dashicons
- Estilos para código inline

### 3. `includes/class-bb-tutor-core.php` (MODIFICADO)

Ajustado para inicializar a classe admin corretamente:

#### Mudanças:

- Adicionado hook `admin_init` para inicializar admin
- Método `init_admin()` agora é público e carrega a classe explicitamente
- Admin é inicializado independentemente do BuddyPress (permite configurar API Key antes)

### 4. `tests/test-admin.php` (NOVO)

Teste manual para verificar a implementação da classe admin:

#### Testes Incluídos:

- Verificação de existência da classe
- Teste do padrão Singleton
- Verificação de métodos públicos
- Teste de `get_api_key()`
- Teste de `get_settings()`
- Verificação de opções no banco de dados
- Verificação de hooks registrados
- Verificação de menu no admin

## Opções do WordPress

### API Key

- **Nome**: `bb_tutor_ia_gemini_api_key`
- **Tipo**: string
- **Descrição**: API Key do Google Gemini
- **Validação**: Formato e teste online

### Configurações Gerais

- **Nome**: `bb_tutor_ia_settings`
- **Tipo**: array
- **Campos**:
  - `max_file_size`: Tamanho máximo em bytes (padrão: 20971520 = 20MB)
  - `allowed_types`: Array de MIME types permitidos
  - `enable_logs`: Boolean para habilitar logs

## Validação da API Key

### Formato Esperado

- Deve começar com "AIza"
- Deve ter exatamente 39 caracteres
- Pode conter: A-Z, a-z, 0-9, hífen (-), underscore (\_)
- Regex: `/^AIza[0-9A-Za-z\-_]{35}$/`

### Validação Online

- Endpoint usado: `https://generativelanguage.googleapis.com/v1beta/models`
- Método: GET
- Timeout: 10 segundos
- Códigos de resposta tratados:
  - 200: API Key válida
  - 400: API Key malformada
  - 403: Sem permissões ou bloqueada
  - 404: Endpoint não encontrado
  - Outros: Mensagem de erro da API

### Cache de Validação

- Transient: `bb_tutor_api_key_status_{md5(api_key)}`
- Duração: 1 hora
- Valores: 'valid', 'invalid', 'empty'

## Interface do Usuário

### Página de Configurações

- **URL**: `/wp-admin/admin.php?page=bb-tutor-ia-settings`
- **Capability**: `manage_options`
- **Ícone**: `dashicons-welcome-learn-more`
- **Posição**: 80 (após Plugins)

### Seções

1. **Configurações da API**
   - Campo de API Key (password)
   - Botão mostrar/ocultar
   - Status da validação
2. **Configurações Gerais**
   - Tamanho máximo de arquivo
   - Habilitar logs

3. **Informações**
   - Como obter API Key
   - Versão do plugin
   - Última atualização

### Notices

- **Sucesso**: API Key validada
- **Erro**: API Key inválida
- **Aviso**: API Key não configurada

## Como Testar

### Via WP-CLI

```bash
# Testar classe admin
wp eval-file wp-content/plugins/buddyboss-tutores-ia/tests/test-admin.php

# Verificar opções
wp option get bb_tutor_ia_gemini_api_key
wp option get bb_tutor_ia_settings

# Definir API Key (para teste)
wp option update bb_tutor_ia_gemini_api_key "AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
```

### Via Interface

1. Ativar o plugin
2. Acessar "Tutores IA" no menu do admin
3. Inserir uma API Key válida do Google Gemini
4. Clicar em "Salvar Configurações"
5. Verificar mensagem de sucesso/erro

## Próximos Passos

Com a página de configurações implementada, os próximos passos são:

1. Implementar integração com grupos BuddyBoss (Task 2.1)
2. Criar classe Gemini API (Task 3.1)
3. Implementar upload de arquivos (Task 4.1)

## Notas Técnicas

- A classe usa o padrão Singleton para garantir uma única instância
- Todas as configurações usam a Settings API do WordPress
- A validação da API Key é feita de forma assíncrona (não bloqueia o salvamento)
- O cache de validação evita requisições desnecessárias à API do Gemini
- A interface é totalmente responsiva
- Todos os textos são traduzíveis (text domain: 'bb-tutor-ia')
