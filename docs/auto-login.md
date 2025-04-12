# Auto-Login no Panel WP Connector

## Descrição

O recurso de auto-login permite que aplicações externas autentiquem usuários automaticamente no WordPress usando a chave de API do Panel WP Connector.

## Como Funciona

1. A aplicação externa faz uma requisição para o endpoint de autenticação com a chave de API e o parâmetro `auto_login=true`
2. O plugin valida a chave de API e identifica o usuário associado
3. Se a chave for válida, o plugin cria um cookie de autenticação para o usuário
4. O usuário é redirecionado para o painel administrativo do WordPress

## Endpoint

```
GET /wp-json/panel-wp/v1/authenticate
```

### Parâmetros

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| api_key | string | Sim | Chave de API válida do Panel WP Connector |
| auto_login | boolean | Sim | Deve ser definido como `true` para ativar o auto-login |
| redirect_url | string | Não | URL para redirecionamento após o login (padrão: painel admin) |

### Cabeçalhos

Alternativamente, a chave de API pode ser fornecida via cabeçalho HTTP:

```
X-Api-Key: sua_chave_api
```

### Exemplo de Uso

```
https://seu-site.com/wp-json/panel-wp/v1/authenticate?api_key=sua_chave_api&auto_login=true
```

## Segurança

- O endpoint só funciona com chaves de API válidas
- Apenas usuários com permissões de administrador podem usar este recurso
- Recomenda-se usar HTTPS para proteger a transmissão da chave de API
- O login é registrado no log do sistema para auditoria

## Integração com Aplicações Externas

Para integrar este recurso em uma aplicação externa, modifique a função `loginWordPress` para:

1. Chamar o endpoint `/wp-json/panel-wp/v1/authenticate` com a chave de API e o parâmetro `auto_login=true`
2. Abrir a URL retornada em uma nova aba ou janela do navegador

### Exemplo de Implementação em JavaScript

```javascript
function loginWordPress(apiKey, siteUrl) {
  const autoLoginUrl = `${siteUrl}/wp-json/panel-wp/v1/authenticate?api_key=${apiKey}&auto_login=true`;
  window.open(autoLoginUrl, '_blank');
}
```
