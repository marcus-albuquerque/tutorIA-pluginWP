# BuddyBoss Tutores IA

Plugin WordPress para BuddyBoss que adiciona tutores de IA especializados aos grupos de disciplinas, utilizando a API do Google Gemini com RAG.

## Características

- Integração nativa com grupos BuddyBoss
- Tutores de IA personalizados por grupo
- Upload de documentos (PDF/TXT) para contexto
- Interface de chat responsiva
- Controle de acesso baseado em roles
- Shortcode para incorporar chat em páginas
- Logs de uso simples

## Requisitos

- WordPress 5.8+
- BuddyBoss 1.8+ ou BuddyPress 10.0+
- PHP 7.4+
- Extensão cURL habilitada
- API Key do Google Gemini

## Instalação

1. Faça upload da pasta `buddyboss-tutores-ia` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure a API Key do Gemini nas configurações do plugin
4. Configure tutores nos grupos desejados

## Estrutura do Plugin

```
buddyboss-tutores-ia/
├── buddyboss-tutores-ia.php        # Arquivo principal
├── includes/
│   ├── class-bb-tutor-core.php     # Classe core com autoloader
│   ├── class-bb-tutor-group.php    # Integração com grupos (a implementar)
│   ├── class-bb-tutor-ajax.php     # Handlers AJAX (a implementar)
│   ├── class-bb-tutor-gemini.php   # API Gemini (a implementar)
│   └── class-bb-tutor-admin.php    # Configurações admin (a implementar)
├── assets/
│   ├── css/
│   │   ├── admin.css               # Estilos admin
│   │   └── chat.css                # Estilos do chat
│   └── js/
│       ├── admin.js                # Scripts admin
│       └── chat.js                 # Chat (Vanilla JS)
└── templates/                      # Templates PHP (a implementar)
```

## Funcionalidades Implementadas (Task 1.2)

### Classe Core (BB_Tutor_Core)

- ✅ Padrão Singleton para instância única
- ✅ Autoloader automático de classes (padrão `class-bb-tutor-*.php`)
- ✅ Sistema de hooks de inicialização do WordPress
- ✅ Sistema de versionamento com suporte a upgrades
- ✅ Enqueue automático de scripts e estilos
- ✅ Suporte a shortcode `[tutor_ia]`
- ✅ Verificação de dependências (BuddyPress/BuddyBoss)

### Funções Auxiliares

- ✅ `bb_tutor_user_can_manage($group_id)` - Verifica permissão de gerenciamento
- ✅ `bb_tutor_user_can_chat($group_id)` - Verifica permissão de uso do chat

### Assets

- ✅ CSS do chat com design responsivo
- ✅ CSS do admin com componentes reutilizáveis
- ✅ JavaScript do chat (Vanilla JS)
- ✅ JavaScript do admin (jQuery)

## Uso do Autoloader

O autoloader carrega automaticamente classes que seguem o padrão:

- Nome da classe: `BB_Tutor_NomeClasse`
- Nome do arquivo: `class-bb-tutor-nomeclasse.php`
- Localização: `includes/`

Exemplo:

```php
// A classe BB_Tutor_Group será carregada automaticamente de:
// includes/class-bb-tutor-group.php
```

## Sistema de Versionamento

O plugin verifica automaticamente a versão instalada e executa rotinas de upgrade quando necessário:

```php
// Hook para adicionar rotinas de upgrade personalizadas
add_action('bb_tutor_ia_upgrade', function($from_version, $to_version) {
    // Executar migrações específicas
}, 10, 2);
```

## Shortcode

```php
[tutor_ia group_id="123" height="600px"]
```

Parâmetros:

- `group_id` (obrigatório): ID do grupo BuddyBoss
- `height` (opcional): Altura do container (padrão: 600px)

## Próximos Passos

As seguintes classes precisam ser implementadas nas próximas tarefas:

1. `BB_Tutor_Admin` - Página de configurações globais (Task 1.3)
2. `BB_Tutor_Group` - Integração com grupos BuddyBoss (Task 2.1-2.3)
3. `BB_Tutor_Gemini` - Integração com API Gemini (Task 3.1-3.3)
4. `BB_Tutor_AJAX` - Handlers AJAX (Task 4.1-4.4)

## Licença

GPL v2 or later

## Autor

Desenvolvido para integração BuddyBoss + Gemini AI
