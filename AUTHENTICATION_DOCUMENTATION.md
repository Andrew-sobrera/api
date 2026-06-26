# Autenticação da API

## Escopos

A API suporta:

- Registro manual com e-mail e senha
- Login manual com e-mail e senha
- Login via Google OAuth 2.0
- Logout via token Sanctum

## Configuração do Google OAuth

1. Crie um OAuth Client no Google Cloud Console:
   - Acesse https://console.cloud.google.com/apis/credentials
   - Crie um novo OAuth 2.0 Client ID
   - Selecione "Application type": Web application

2. Adicione os seguintes redirect URIs:
   - `http://localhost:8000/api/auth/google/callback`
   - URL de produção equivalente (ex: `https://api.seudominio.com/api/auth/google/callback`)

3. Copie as credenciais para o arquivo `.env`:

```env
GOOGLE_CLIENT_ID=seu_client_id
GOOGLE_CLIENT_SECRET=seu_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

## Variáveis de ambiente

No `.env` local ou de produção configure:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

## Rotas de autenticação

### Registro manual

POST `/api/auth/register`

Request body:

```json
{
  "name": "Nome usuário",
  "email": "email@email.com",
  "password": "senha",
  "password_confirmation": "senha"
}
```

Response:

```json
{
  "user": { ... },
  "token": "xxxxx"
}
```

### Login manual

POST `/api/auth/login`

Request body:

```json
{
  "email": "email@email.com",
  "password": "senha"
}
```

Response:

```json
{
  "user": { ... },
  "token": "xxxxx"
}
```

### Autenticação com Google

GET `/api/auth/google/redirect`

Response:

```json
{
  "url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

Use a URL retornada pelo endpoint para redirecionar o usuário ao Google.

### Callback do Google

GET `/api/auth/google/callback`

Após o Google autenticar, a API recebe o callback e retorna:

```json
{
  "user": { ... },
  "token": "xxxxx"
}
```

### Logout

POST `/api/auth/logout`

Headers:

```http
Authorization: Bearer {token}
```

Response:

```json
{
  "message": "Logged out successfully"
}
```

## Teste com Postman

1. Faça `POST /api/auth/register` com um novo e-mail e senha.
2. Copie o token retornado e use no header `Authorization: Bearer {token}`.
3. Faça `POST /api/auth/login` para autenticação manual.
4. Faça `GET /api/auth/google/redirect` para obter a URL do Google.
5. Acesse a URL e complete o fluxo de login.
6. Ao receber o callback, confirme se o token e o usuário foram retornados.

## Observações

- O login manual usa rate limit `throttle:5,1`.
- O `password` não é retornado nas respostas JSON porque está oculto no modelo.
- Usuários existentes são compatíveis e serão atualizados ao logar com Google pelo mesmo e-mail.
