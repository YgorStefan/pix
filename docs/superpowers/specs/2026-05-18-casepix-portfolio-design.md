# CasePix — Portfolio com CRUD e Demo Interativo

**Data:** 2026-05-18  
**Status:** Aprovado  

---

## Objetivo

Transformar o case técnico de saque via PIX em um projeto de portfólio funcional e visualmente profissional, acessível publicamente via `casepix.ygorstefan.com`. Qualquer pessoa pode criar contas, definir saldos, executar saques imediatos e agendados, e ver o histórico em tempo real — sem instalar nada, sem Postman.

---

## Arquitetura

### Componentes

```
Browser do avaliador
        ↓
   nginx :80  (casepix.ygorstefan.com)
   ├── GET /           → serve public/index.html (estático)
   └── /account/*      → proxy para app:9501 (Hyperf/Swoole)
              ↓
         MySQL + Mailhog
```

### Arquivos adicionados / modificados

| Status | Arquivo | Descrição |
|--------|---------|-----------|
| `+` | `public/index.html` | Frontend single-page (dashboard) |
| `+` | `docker/nginx/nginx.conf` | Proxy + servir estático |
| `+` | `docker/nginx/Dockerfile` | Imagem nginx alpine |
| `~` | `docker-compose.yml` | Adiciona serviço nginx na porta 80 |
| `~` | `.gitignore` | Adiciona `.superpowers/` |
| `~` | `README.md` | Seção de deploy no servidor |

Sem seed data fixo — o CRUD permite criar contas pela própria UI.

---

## Endpoints

### Existente (mantido)

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/account/{id}/balance/withdraw` | Saque imediato ou agendado |

### Novos

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/account` | Criar conta (`name`, `balance`) |
| `GET` | `/account` | Listar todas as contas |
| `PATCH` | `/account/{id}/balance` | Atualizar saldo (`balance`) |
| `DELETE` | `/account/{id}` | Excluir conta |
| `GET` | `/account/{id}/withdrawals` | Listar saques da conta |

### Respostas de erro padrão

- `404` — conta não encontrada
- `422` — dados inválidos (com lista de erros)
- `500` — erro interno

---

## Frontend

### Visual

- Estilo **Terminal/Hacker**: fundo `#020617`, verde `#22c55e`, fonte monospace
- Idioma: **pt-BR**
- Single-page, sem framework (HTML/CSS/JS vanilla)
- Sem autenticação — portfólio é público por definição

### Layout — Dashboard duas colunas

```
┌─────────────────┬────────────────────────────────────────┐
│   SIDEBAR       │   PAINEL PRINCIPAL                     │
│                 │                                        │
│ ▶ CASEPIX       │  conta_demo_a          [Editar] [Del]  │
│                 │  Saldo: R$ 500,00                      │
│ CONTAS          │                                        │
│ ┌─────────────┐ │  NOVO SAQUE                            │
│ │ conta_a ✓  │ │  [Valor ] [Chave PIX email  ] [SACAR]  │
│ │ R$ 500,00  │ │  [Agendar para: YYYY-MM-DD HH:mm     ] │
│ └─────────────┘ │                                        │
│ ┌─────────────┐ │  OUTPUT (terminal)                     │
│ │ conta_b    │ │  > POST /account/{id}/balance/withdraw  │
│ │ R$ 50,00   │ │  < 201 {"id":"...","done":true,...}     │
│ └─────────────┘ │                                        │
│                 │  HISTÓRICO DE SAQUES                   │
│ [+ Nova conta] │  ✓ CONCLUÍDO  R$ 150,00 → pix@x.com   │
│                 │  ⏳ AGENDADO  R$ 100,00  2026-06-01    │
│                 │  ✗ ERRO       R$ 600,00  saldo insuf.  │
└─────────────────┴────────────────────────────────────────┘
```

### Comportamento da sidebar

- Lista todas as contas via `GET /account` ao carregar
- Clique em uma conta → carrega detalhes + histórico no painel
- Botão "+ Nova conta" → form inline na sidebar (nome + saldo)
- Submit → `POST /account` → atualiza lista

### Comportamento do painel principal

- **Editar saldo**: abre input inline, submit → `PATCH /account/{id}/balance`
- **Excluir**: confirmação inline → `DELETE /account/{id}` → remove da sidebar
- **Novo saque**: submit → `POST /account/{id}/balance/withdraw` → exibe resposta JSON no output terminal e recarrega saldo + histórico
- **Output terminal**: mostra método, rota, status code e JSON formatado. Cor verde para 2xx, vermelho para 4xx/5xx
- **Histórico**: carrega via `GET /account/{id}/withdrawals` ao selecionar conta. Status coloridos: verde (concluído), amarelo (agendado), vermelho (erro)

---

## Novos componentes de backend

### Camada de domínio / aplicação

O domínio `Conta` já existe. Os novos casos de uso são simples — sem regras de negócio complexas além das já existentes.

| Componente | Tipo | Descrição |
|-----------|------|-----------|
| `CriarConta` | Application service | Cria `Conta` com UUID gerado e persiste |
| `ListarContas` | Application service | Retorna todas as contas do repositório |
| `AtualizarSaldo` | Application service | Atualiza saldo de uma conta existente |
| `ExcluirConta` | Application service | Remove conta se existir |
| `ListarSaquesDaConta` | Application service | Retorna saques de uma conta com status |

### Repositórios

- `ContaRepositorio`: adicionar `listar(): array` e `excluir(string $id): void`
- `SaqueRepositorio`: adicionar `listarPorConta(string $contaId): array`

### Controller

- Expandir `app/Infrastructure/Http/ContaController.php` existente com as rotas CRUD
- Validações: nome não vazio, saldo ≥ 0, UUID válido

---

## Infraestrutura

### nginx.conf

```nginx
server {
    listen 80;

    location /account {
        proxy_pass http://app:9501;
        proxy_set_header Host $host;
    }

    location / {
        root /usr/share/nginx/html;
        try_files $uri $uri/ /index.html;
    }
}
```

### docker-compose.yml — serviço nginx

```yaml
nginx:
  build: ./docker/nginx
  ports:
    - "80:80"
  volumes:
    - ./public:/usr/share/nginx/html:ro
  depends_on:
    app:
      condition: service_started
```

### Deploy no servidor (README)

1. Instalar Docker e Docker Compose no servidor
2. Clonar o repositório
3. `cp .env.example .env` e ajustar variáveis
4. `docker compose up -d --build`
5. Apontar subdomínio `casepix.ygorstefan.com` para o IP do servidor (painel Hostinger)
6. Opcional: configurar HTTPS com Certbot/Let's Encrypt

---

## Fora do escopo

- Autenticação ou controle de acesso
- Outros tipos de chave PIX além de `email` (definido pelo case original)
- Transferências entre contas
- Configuração de DNS no painel da Hostinger (feito manualmente)
- HTTPS/SSL (opcional, documentado mas não implementado)
