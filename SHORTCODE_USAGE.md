# Uso do Shortcode [tutor_ia]

## Descrição

O shortcode `[tutor_ia]` permite incorporar o chat do tutor IA em qualquer página ou post do WordPress.

## Sintaxe

```
[tutor_ia group_id="123" height="600px"]
```

## Parâmetros

### group_id (obrigatório)

- **Tipo:** Número inteiro
- **Descrição:** ID do grupo do BuddyBoss que possui o tutor IA configurado
- **Exemplo:** `group_id="123"`

### height (opcional)

- **Tipo:** String CSS
- **Padrão:** `600px`
- **Descrição:** Altura do container do chat
- **Exemplos:**
  - `height="600px"`
  - `height="80vh"`
  - `height="500px"`

## Exemplos de Uso

### Exemplo Básico

```
[tutor_ia group_id="123"]
```

### Com Altura Customizada

```
[tutor_ia group_id="123" height="800px"]
```

### Em Viewport Height

```
[tutor_ia group_id="123" height="90vh"]
```

## Validações Implementadas

O shortcode realiza as seguintes validações:

1. **Validação de group_id:** Verifica se o parâmetro foi fornecido
2. **Existência do grupo:** Verifica se o grupo existe no BuddyBoss
3. **Tutor ativo:** Verifica se o tutor IA está ativado para o grupo
4. **Permissões:** Verifica se o usuário é membro do grupo
5. **Store configurado:** Verifica se o store do Gemini foi criado

## Mensagens de Erro

### Group ID não especificado

```
Erro: group_id não especificado no shortcode.
```

### Grupo não encontrado

```
Erro: grupo não encontrado.
```

### Tutor não ativo

```
O tutor IA não está ativo para este grupo.
```

### Sem permissão

```
Você não tem permissão para acessar este tutor. Você precisa ser membro do grupo.
```

### Store não configurado

```
O tutor ainda está sendo configurado. Tente novamente em alguns instantes.
```

## Funcionalidades

- ✅ Enqueue automático de CSS e JavaScript
- ✅ Inicialização automática do chat
- ✅ Suporte a múltiplos shortcodes na mesma página
- ✅ Responsivo para mobile
- ✅ Integração completa com AJAX
- ✅ Exibição de citações das fontes
- ✅ Tratamento de erros

## Requisitos

- Usuário deve estar logado
- Usuário deve ser membro do grupo especificado
- Tutor IA deve estar ativo no grupo
- Store do Gemini deve estar configurado

## Notas Técnicas

### Assets Carregados

- **CSS:** `assets/css/chat.css`
- **JavaScript:** `assets/js/chat.js`

### Objeto JavaScript

O shortcode inicializa o objeto global `BBTutorChat` com:

- `groupId`: ID do grupo
- `container`: Elemento DOM do container

### Segurança

- Sanitização de todos os inputs
- Verificação de nonce em requisições AJAX
- Validação de permissões do usuário
- Escape de outputs HTML

## Suporte a Iframe

O shortcode funciona corretamente quando incorporado em iframes, adaptando automaticamente o layout.

## Compatibilidade

- WordPress 5.8+
- BuddyBoss 1.8+ ou BuddyPress 10.0+
- PHP 7.4+
- Navegadores modernos (Chrome, Firefox, Safari, Edge)
