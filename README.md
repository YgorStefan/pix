# CasePix

API de saques via PIX construída com Hyperf (PHP + Swoole).

## Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) e Docker Compose

## Subir o ambiente

```bash
cp .env.example .env
docker compose up -d --build
```

O container `app` aguarda o MySQL ficar saudável e executa as migrations automaticamente antes de iniciar o servidor. A API fica disponível em `http://localhost:9501`.

## Adicionar saldo a uma conta

Conforme o case, o saldo é ajustado diretamente no banco:

```sql
UPDATE account SET balance = 500.00 WHERE id = '<uuid>';
```

> **Observação:** o case não prevê criação de contas via API — a conta deve existir previamente no banco. Se o `UPDATE` não afetar nenhuma linha (0 rows affected), significa que o `id` informado não existe; verifique o UUID correto consultando a tabela `account` antes de tentar o saque.

Acesse o phpMyAdmin em `http://localhost:8080` (usuário `app`, senha `secret`) para facilitar.

## Rodar os testes

```bash
# Unitários (sem dependências externas)
docker compose exec app vendor/bin/phpunit --testsuite Unit

# Integração (requerem MySQL + Swoole rodando)
docker compose exec app vendor/bin/phpunit -c phpunit.integration.xml

# Concorrência (incluído nos de integração acima)
docker compose exec app vendor/bin/phpunit -c phpunit.integration.xml --filter ConcorrenciaSaqueImediatoTest
```

## Serviços disponíveis

| Serviço    | URL                   | Descrição                   |
|------------|-----------------------|-----------------------------|
| API        | http://localhost:9501 | Aplicação Hyperf            |
| phpMyAdmin | http://localhost:8080 | Interface do banco de dados |
| Mailhog    | http://localhost:8025 | Captura de e-mails de teste |

## Endpoint

### `POST /account/{contaId}/balance/withdraw`

**Saque imediato:**
```json
{
  "method": "PIX",
  "pix": { "type": "email", "key": "usuario@email.com" },
  "amount": 150.75,
  "schedule": null
}
```

**Saque agendado** (fuso `America/Sao_Paulo`):
```json
{
  "method": "PIX",
  "pix": { "type": "email", "key": "usuario@email.com" },
  "amount": 100.00,
  "schedule": "2026-12-31 14:00"
}
```

| Código | Situação |
|--------|----------|
| `201`  | Saque criado (imediato ou agendado) |
| `404`  | Conta não encontrada |
| `422`  | Saldo insuficiente, data no passado ou dados inválidos |

---

## Decisões técnicas

### Dentro do escopo do case

**`SELECT FOR UPDATE` no saque imediato e agendado**
Ao deduzir saldo, o repositório abre uma transação e faz `SELECT ... FOR UPDATE` na linha da conta. Duas requisições concorrentes para a mesma conta ficam serializadas pelo lock — a segunda só lê o saldo após a primeira commitar, tornando impossível deixar o saldo negativo mesmo sob carga paralela.

**Campo `processing_since` para escalabilidade horizontal**
O case pede compatibilidade com múltiplas instâncias. A reserva de saques agendados usa `UPDATE ... WHERE processing_since IS NULL`, operação atômica no MySQL. Apenas a instância que vencer o UPDATE processa aquele saque; as demais pulam. Se o processo morrer antes de concluir, o campo pode ser zerado para reprocessamento.

**Cron a cada 5 segundos (`*/5 * * * * *`)**
Implementado com o componente `Hyperf\Crontab` conforme indicado no case. A expressão de 6 campos (`*/5 * * * * *`) é específica do Hyperf e roda a cada 5 segundos, não a cada 5 minutos.

**Dinheiro em centavos (inteiro)**
A classe `Dinheiro` guarda o valor como `int` de centavos para evitar erros de ponto flutuante. Conversões de/para `string` decimal usam aritmética exata.

**Timezone `America/Sao_Paulo` na entrada, UTC no banco**
Datas de agendamento são recebidas no fuso de Brasília e convertidas para UTC antes de persistir. A cron compara `scheduled_for <= NOW()` em UTC, sem ambiguidades de horário de verão.

**Email não reverte o saque**
A notificação é disparada fora da transação de banco. Falha no SMTP não cancela o saque já registrado — apenas logada. O case não especifica comportamento diferente.

**Extensibilidade de métodos de saque**
A interface `MetodoDeSaque` separa o conceito de método da lógica de saque. Adicionar TED, boleto ou outra chave PIX exige implementar `MetodoDeSaque` e registrar um handler de notificação — o fluxo central não muda.

---

### Fora do escopo do case (extras adicionados)

**phpMyAdmin no Docker Compose**
Não era pedido, mas facilita inspecionar o banco durante o desenvolvimento e avaliação do case.

**Suíte de testes (unitários + integração + concorrência)**
O case não exige testes automatizados. Foram criados 48 testes unitários e 8 de integração (incluindo um teste de concorrência com 20 processos paralelos via `pcntl_fork`) para validar as garantias de atomicidade e escalabilidade descritas no case.

**Validação detalhada de input no controller**
O case define a estrutura obrigatória do body mas não detalha respostas de erro. O controller valida UUID, `method`, `pix.type`, `pix.key` (formato email), `amount` (positivo, máximo 2 casas decimais) e `schedule` (formato `Y-m-d H:i`), retornando todos os erros em uma única resposta `422`.

**Observabilidade via logs estruturados**
Logs JSON estruturados em `runtime/logs/` para cada saque processado, falha de email e execução de cron. Não foi pedido explicitamente, mas o case menciona "observabilidade" como ponto de atenção.
