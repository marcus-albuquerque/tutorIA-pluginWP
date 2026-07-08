# Sistema de Logging - BuddyBoss Tutores IA

## Visão Geral

O plugin agora possui um sistema completo de logging que registra erros, avisos e eventos importantes em arquivos de texto para facilitar o debug e monitoramento.

## Localização dos Logs

Os logs são armazenados em:

```
wp-content/plugins/buddyboss-tutores-ia/logs/
```

## Formato dos Arquivos

- **Nome**: `bb-tutor-YYYY-MM-DD.log` (um arquivo por dia)
- **Rotação**: Arquivos maiores que 5MB são automaticamente rotacionados para `.log.old`
- **Limpeza**: Logs com mais de 30 dias são automaticamente deletados

## Níveis de Log

### ERROR

Erros críticos que impedem o funcionamento normal:

- Falhas na API do Gemini
- Arquivos não encontrados
- Classes não carregadas
- Erros de upload/download

### WARNING

Avisos sobre situações anormais mas não críticas:

- Permissões negadas
- Validações de arquivo falhadas
- Nonce inválido
- Parâmetros inválidos

### INFO

Eventos importantes do sistema:

- Upload de arquivo bem-sucedido
- Mensagem processada com sucesso
- Store criado
- Arquivo deletado

### DEBUG

Informações detalhadas para desenvolvimento (apenas se WP_DEBUG estiver ativo)

## Formato das Entradas

Cada entrada de log contém:

```
[YYYY-MM-DD HH:MM:SS] [LEVEL] Mensagem | Context: {...} | User: username (ID: 123) | URL: /path
```

## Segurança

- Arquivo `.htaccess` impede acesso direto aos logs via HTTP
- Arquivo `index.php` previne listagem de diretório
- Logs contêm apenas informações técnicas (sem senhas ou dados sensíveis)

## Eventos Registrados

### API Gemini (class-bb-tutor-gemini.php)

- Erros de conexão
- Timeouts
- Erros HTTP (401, 403, 429, 500+)
- Falhas de upload
- Erros ao criar store
- Erros ao adicionar arquivo ao store

### AJAX Handlers (class-bb-tutor-ajax.php)

- Permissões negadas
- Validações de arquivo
- Erros de upload
- Erros ao processar mensagens
- Sucesso em operações

### Integração com Grupos (class-bb-tutor-group.php)

- Erros ao criar store
- Permissões negadas
- Sucesso ao criar store

## Manutenção Automática

O sistema executa limpeza automática diariamente via WordPress Cron:

- Remove logs com mais de 30 dias
- Executado automaticamente pelo hook `bb_tutor_ia_daily_cleanup`

## Uso Programático

### Registrar Erro

```php
BB_Tutor_Logger::error('Mensagem de erro', [
    'context_key' => 'valor',
    'outro_dado' => 123
]);
```

### Registrar Aviso

```php
BB_Tutor_Logger::warning('Mensagem de aviso', ['group_id' => $group_id]);
```

### Registrar Info

```php
BB_Tutor_Logger::info('Operação bem-sucedida', ['file_id' => $file_id]);
```

### Registrar Debug

```php
BB_Tutor_Logger::debug('Informação de debug', ['data' => $data]);
```

## Visualização dos Logs

Para visualizar os logs:

1. **Via FTP/SSH**: Acesse a pasta `wp-content/plugins/buddyboss-tutores-ia/logs/`
2. **Via Código**: Use `BB_Tutor_Logger::get_log_files()` e `BB_Tutor_Logger::read_log($filename)`

## Troubleshooting

### Logs não estão sendo criados

- Verifique permissões da pasta `logs/`
- Certifique-se que o plugin está ativo
- Verifique se há erros no error_log do WordPress

### Logs muito grandes

- O sistema rotaciona automaticamente arquivos > 5MB
- Logs antigos são deletados após 30 dias
- Considere ajustar os limites em `class-bb-tutor-logger.php` se necessário

### Não consigo acessar os logs via HTTP

- Isso é intencional por segurança
- Use FTP/SSH ou métodos programáticos para acessar
