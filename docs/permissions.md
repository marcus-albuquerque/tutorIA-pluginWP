# Sistema de Permissões - BuddyBoss Tutores IA

## Visão Geral

O plugin implementa um sistema de controle de acesso baseado em roles do WordPress e funções nativas do BuddyBoss para gerenciar quem pode configurar e usar os tutores IA.

## Funções de Permissão

### `bb_tutor_user_can_manage($group_id)`

Verifica se o usuário atual tem permissão para **gerenciar** (configurar, ativar/desativar, fazer upload de arquivos) o tutor IA de um grupo.

#### Permissões Concedidas

1. **Administradores do WordPress** - Sempre têm permissão total
   - Verificado via: `current_user_can('administrator')`
2. **Professores Organizadores** - Professores que são organizadores do grupo
   - Verificado via: `current_user_can('professor') && groups_is_user_admin($user_id, $group_id)`
3. **Organizadores do Grupo** - Qualquer usuário que seja organizador (admin) do grupo
   - Verificado via: `groups_is_user_admin($user_id, $group_id)`

#### Permissões Negadas

- Usuários não logados
- Membros comuns do grupo (sem role de organizador)
- Professores que não são organizadores do grupo específico
- Usuários que não são membros do grupo

#### Exemplo de Uso

```php
// Verificar antes de exibir configurações
if (bb_tutor_user_can_manage($group_id)) {
    // Exibir formulário de configuração
    include 'templates/group-settings.php';
}

// Verificar antes de salvar configurações
if (!bb_tutor_user_can_manage($group_id)) {
    wp_die('Você não tem permissão para gerenciar este tutor.');
}
```

#### Onde é Usado

- `class-bb-tutor-group.php::render_settings()` - Exibir configurações
- `class-bb-tutor-group.php::save_settings()` - Salvar configurações
- Handlers AJAX de upload/exclusão de arquivos (futuro)

---

### `bb_tutor_user_can_chat($group_id)`

Verifica se o usuário atual tem permissão para **usar o chat** do tutor IA de um grupo.

#### Permissões Concedidas

1. **Membros do Grupo** - Qualquer usuário que seja membro do grupo
   - Verificado via: `groups_is_user_member($user_id, $group_id)`
   - Inclui: membros comuns, moderadores e organizadores

#### Permissões Negadas

- Usuários não logados
- Usuários que não são membros do grupo
- Administradores do WordPress que não são membros do grupo

> **Nota:** Diferente de `bb_tutor_user_can_manage()`, administradores do WordPress **não** têm acesso automático ao chat. Eles precisam ser membros do grupo.

#### Exemplo de Uso

```php
// Verificar antes de exibir chat
if (!bb_tutor_user_can_chat($group_id)) {
    echo '<p>Você precisa ser membro do grupo para usar o tutor.</p>';
    return;
}

// Verificar em handler AJAX
if (!bb_tutor_user_can_chat($group_id)) {
    wp_send_json_error(['message' => 'Sem permissão']);
}
```

#### Onde é Usado

- `class-bb-tutor-group.php::tab_content()` - Exibir interface do chat
- `class-bb-tutor-group.php::setup_group_tab()` - Controlar visibilidade da aba
- Handler AJAX de envio de mensagens (futuro)
- Shortcode `[tutor_ia]` (futuro)

---

## Matriz de Permissões

| Usuário                       | Gerenciar Tutor | Usar Chat |
| ----------------------------- | --------------- | --------- |
| Administrador WP (não-membro) | ✅ Sim          | ❌ Não    |
| Administrador WP (membro)     | ✅ Sim          | ✅ Sim    |
| Professor Organizador         | ✅ Sim          | ✅ Sim    |
| Professor Membro              | ❌ Não          | ✅ Sim    |
| Professor (não-membro)        | ❌ Não          | ❌ Não    |
| Organizador do Grupo          | ✅ Sim          | ✅ Sim    |
| Moderador do Grupo            | ❌ Não          | ✅ Sim    |
| Membro Comum                  | ❌ Não          | ✅ Sim    |
| Não-membro                    | ❌ Não          | ❌ Não    |
| Não-logado                    | ❌ Não          | ❌ Não    |

## Segurança

### Boas Práticas Implementadas

1. **Verificação de Login**
   - Ambas as funções verificam `is_user_logged_in()` primeiro
   - Retornam `false` imediatamente para usuários não autenticados

2. **Uso de Funções Nativas**
   - `current_user_can()` - Verificação de capabilities do WordPress
   - `groups_is_user_admin()` - Verificação de organizador do BuddyBoss
   - `groups_is_user_member()` - Verificação de membro do BuddyBoss
   - `get_current_user_id()` - Obter ID do usuário atual

3. **Verificação em Múltiplas Camadas**
   - Verificação no template (UI)
   - Verificação no handler de salvamento
   - Verificação nos handlers AJAX (futuro)

4. **Princípio do Menor Privilégio**
   - Usuários só têm acesso ao mínimo necessário
   - Separação clara entre "gerenciar" e "usar"

### Recomendações de Uso

1. **Sempre verificar antes de ações sensíveis**

   ```php
   if (!bb_tutor_user_can_manage($group_id)) {
       return; // ou wp_die() / wp_send_json_error()
   }
   ```

2. **Combinar com nonce para formulários**

   ```php
   if (!bb_tutor_user_can_manage($group_id)) {
       return;
   }

   if (!wp_verify_nonce($_POST['nonce'], 'bb_tutor_settings')) {
       return;
   }
   ```

3. **Usar em AJAX handlers**

   ```php
   check_ajax_referer('bb_tutor_action', 'nonce');

   if (!bb_tutor_user_can_manage($group_id)) {
       wp_send_json_error(['message' => 'Sem permissão']);
   }
   ```

## Testes

Os testes unitários estão disponíveis em `tests/test-permissions.php` e cobrem:

- ✅ Administrador pode gerenciar
- ✅ Professor organizador pode gerenciar
- ✅ Professor não-organizador não pode gerenciar
- ✅ Organizador pode gerenciar
- ✅ Membro comum não pode gerenciar
- ✅ Não-membro não pode gerenciar
- ✅ Usuário não logado não pode gerenciar
- ✅ Membro pode usar chat
- ✅ Não-membro não pode usar chat
- ✅ Usuário não logado não pode usar chat
- ✅ Administrador precisa ser membro para usar chat

### Executar Testes

```bash
# Configurar ambiente de testes do WordPress
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Executar testes
phpunit tests/test-permissions.php
```

## Requisitos Atendidos

Este sistema de permissões atende ao **Requisito 4: Controle de Acesso por Role** da especificação:

- ✅ Critério 4.1: Usuários sem permissão não podem acessar configurações
- ✅ Critério 4.2: Professores organizadores podem configurar tutor
- ✅ Critério 4.3: Administradores podem configurar qualquer tutor
- ✅ Critério 4.4: Alunos veem apenas a aba de chat
- ✅ Critério 4.5: Permissões atualizam automaticamente (baseado em funções nativas)

## Referências

- [WordPress Roles and Capabilities](https://wordpress.org/support/article/roles-and-capabilities/)
- [BuddyBoss Groups Functions](https://www.buddyboss.com/resources/dev-docs/)
- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)
