# Sistema de Versionamento - BuddyBoss Tutores IA

## Visão Geral

O plugin implementa um sistema robusto de versionamento seguindo as melhores práticas do WordPress. O sistema gerencia versões, detecta atualizações e executa migrações de dados quando necessário.

## Componentes do Sistema

### 1. Constante de Versão

A versão atual do plugin é definida como uma constante no arquivo principal:

```php
define('BB_TUTOR_IA_VERSION', '1.0.0');
```

Esta constante deve ser atualizada manualmente sempre que uma nova versão for lançada.

### 2. Armazenamento no Banco de Dados

O sistema armazena três valores na tabela `wp_options`:

- **`bb_tutor_ia_version`**: Versão atualmente instalada
- **`bb_tutor_ia_installed_at`**: Timestamp da primeira instalação
- **`bb_tutor_ia_last_updated`**: Timestamp da última atualização

### 3. Verificação Automática

A cada carregamento do plugin, o método `check_version()` compara a versão salva com a versão atual:

```php
public function check_version() {
    $saved_version = get_option('bb_tutor_ia_version', '0.0.0');

    if (version_compare($saved_version, $this->version, '<')) {
        // Executar atualização
    }
}
```

## Fluxo de Atualização

### Primeira Instalação

1. Hook de ativação é executado
2. Versão é salva: `update_option('bb_tutor_ia_version', '1.0.0')`
3. Timestamp de instalação é registrado
4. Role 'professor' é criada

### Atualização de Versão

1. Plugin é atualizado para nova versão
2. `check_version()` detecta diferença de versão
3. Método `upgrade()` é chamado com versões antiga e nova
4. Migrações específicas são executadas (se houver)
5. Nova versão é salva no banco
6. Timestamp de atualização é registrado
7. Cache é limpo

## Adicionando Migrações

Para adicionar uma migração específica para uma nova versão, edite o método `upgrade()` em `class-bb-tutor-core.php`:

```php
private function upgrade($from_version, $to_version) {
    // Migração para versão 1.1.0
    if (version_compare($from_version, '1.1.0', '<')) {
        $this->upgrade_to_1_1_0();
    }

    // Migração para versão 1.2.0
    if (version_compare($from_version, '1.2.0', '<')) {
        $this->upgrade_to_1_2_0();
    }

    // Hook para extensões
    do_action('bb_tutor_ia_upgrade', $from_version, $to_version);

    wp_cache_flush();
}

/**
 * Migração para versão 1.1.0
 */
private function upgrade_to_1_1_0() {
    // Exemplo: adicionar novo meta aos grupos existentes
    global $wpdb;

    $groups = $wpdb->get_results(
        "SELECT group_id FROM {$wpdb->prefix}bp_groups_groupmeta
         WHERE meta_key = '_tutor_ia_enabled' AND meta_value = '1'"
    );

    foreach ($groups as $group) {
        // Adicionar novo campo
        groups_update_groupmeta($group->group_id, '_tutor_ia_new_field', 'default_value');
    }
}
```

## Funções Auxiliares

### `bb_tutor_get_version()`

Retorna a versão atual do plugin:

```php
$version = bb_tutor_get_version();
// Retorna: "1.0.0"
```

### `bb_tutor_get_version_info()`

Retorna informações completas sobre a versão:

```php
$info = bb_tutor_get_version_info();
// Retorna:
// [
//     'current' => '1.0.0',
//     'saved' => '1.0.0',
//     'last_updated' => '2025-02-13 10:30:00',
//     'needs_update' => false
// ]
```

### Método `get_version_info()` da Classe Core

```php
$core = BB_Tutor_Core::get_instance();
$info = $core->get_version_info();
```

## Hooks Disponíveis

### `bb_tutor_ia_upgrade`

Executado durante o processo de atualização:

```php
add_action('bb_tutor_ia_upgrade', function($from_version, $to_version) {
    // Sua lógica de migração personalizada
    error_log("Atualizando de $from_version para $to_version");
}, 10, 2);
```

### `bb_tutor_ia_version_updated`

Executado após a versão ser atualizada com sucesso:

```php
add_action('bb_tutor_ia_version_updated', function($from_version, $to_version) {
    // Ações pós-atualização
    wp_mail(
        get_option('admin_email'),
        'Plugin Atualizado',
        "BuddyBoss Tutores IA foi atualizado de $from_version para $to_version"
    );
}, 10, 2);
```

## Testando o Sistema

### Teste Manual via WP-CLI

Execute o script de teste incluído:

```bash
wp eval-file wp-content/plugins/buddyboss-tutores-ia/tests/test-versioning.php
```

### Teste de Atualização

1. Instale o plugin (versão 1.0.0)
2. Verifique a versão salva: `wp option get bb_tutor_ia_version`
3. Altere a constante para 1.1.0
4. Recarregue o plugin
5. Verifique se a versão foi atualizada

### Verificação no Admin

Adicione este código temporário em um arquivo de teste:

```php
$info = bb_tutor_get_version_info();
echo '<pre>';
print_r($info);
echo '</pre>';
```

## Boas Práticas

1. **Sempre incremente a versão** ao fazer alterações no plugin
2. **Use versionamento semântico**: MAJOR.MINOR.PATCH
   - MAJOR: Mudanças incompatíveis
   - MINOR: Novas funcionalidades compatíveis
   - PATCH: Correções de bugs
3. **Documente migrações** no changelog
4. **Teste migrações** em ambiente de desenvolvimento antes de lançar
5. **Faça backup** antes de executar migrações em produção
6. **Use transações** para migrações de banco de dados quando possível

## Exemplo de Changelog

```
## [1.1.0] - 2025-03-01
### Adicionado
- Nova funcionalidade X
- Suporte para Y

### Migração
- Adiciona campo _tutor_ia_new_field aos grupos existentes

## [1.0.1] - 2025-02-20
### Corrigido
- Bug no upload de arquivos
- Problema de permissões

## [1.0.0] - 2025-02-13
### Inicial
- Lançamento inicial do plugin
```

## Troubleshooting

### Versão não atualiza

1. Verifique se a constante foi alterada
2. Limpe o cache do WordPress
3. Desative e reative o plugin
4. Verifique logs de erro

### Migração falhou

1. Verifique logs: `wp-content/debug.log`
2. Restaure backup do banco de dados
3. Execute migração manualmente via WP-CLI
4. Contate suporte se necessário

## Referências

- [WordPress Plugin Handbook - Versioning](https://developer.wordpress.org/plugins/plugin-basics/best-practices/#versioning)
- [Semantic Versioning](https://semver.org/)
- [WordPress Database API](https://developer.wordpress.org/apis/handbook/database/)
