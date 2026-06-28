# Integração Asaas — Documentação Técnica

> **Projeto:** EvenTche API  
> **Stack:** Laravel 12 / PHP 8.2  
> **Data:** Junho 2026  
> **Escopo:** Checkout, Split de Pagamentos, Subcontas, Estornos, Chargeback, Dashboard Financeiro

---

## Índice

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Estrutura de Arquivos](#2-estrutura-de-arquivos)
3. [Banco de Dados](#3-banco-de-dados)
4. [Fluxo Completo End-to-End](#4-fluxo-completo-end-to-end)
   - 4.1 [Cadastro de Produtor](#41-cadastro-de-produtor)
   - 4.2 [Cadastro via Google OAuth](#42-cadastro-via-google-oauth)
   - 4.3 [Criação de Evento](#43-criação-de-evento)
   - 4.4 [Checkout e Cálculo de Taxas](#44-checkout-e-cálculo-de-taxas)
   - 4.5 [Pagamento com Split](#45-pagamento-com-split)
   - 4.6 [Confirmação via Webhook](#46-confirmação-via-webhook)
   - 4.7 [Cancelamento e Estorno](#47-cancelamento-e-estorno)
   - 4.8 [Chargeback](#48-chargeback)
   - 4.9 [Liberação de Saldo](#49-liberação-de-saldo)
   - 4.10 [Dashboard Financeiro](#410-dashboard-financeiro)
5. [Serviços e Responsabilidades](#5-serviços-e-responsabilidades)
6. [Endpoints da API](#6-endpoints-da-api)
7. [Cálculo de Taxas e Split](#7-cálculo-de-taxas-e-split)
8. [Configuração do Ambiente](#8-configuração-do-ambiente)
9. [Variáveis de Ambiente](#9-variáveis-de-ambiente)
10. [Executando Migrations e Seeders](#10-executando-migrations-e-seeders)
11. [Decisões Arquiteturais](#11-decisões-arquiteturais)

---

## 1. Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Cliente (Frontend)                           │
└───────────────────────────────┬─────────────────────────────────────┘
                                │ HTTP
┌───────────────────────────────▼─────────────────────────────────────┐
│                         Laravel API                                  │
│                                                                      │
│  Controllers → Services → Repositories → Models                     │
│                    │                                                  │
│                    ▼                                                  │
│           AsaasClient (HTTP centralizado)                            │
│                    │                                                  │
└───────────────────┬─────────────────────────────────────────────────┘
                    │ HTTPS
┌───────────────────▼─────────────────────────────────────────────────┐
│                      API Asaas v3                                    │
│  /customers  /payments  /accounts  /finance                         │
└─────────────────────────────────────────────────────────────────────┘
```

### Princípios aplicados

| Princípio | Aplicação |
|-----------|-----------|
| **Single Responsibility** | Cada serviço tem uma única razão para mudar |
| **Open/Closed** | `PaymentGatewayInterface` permite trocar o gateway sem alterar o checkout |
| **Dependency Injection** | Todos os serviços recebem dependências via construtor |
| **Repository Pattern** | Acesso a dados isolado em repositórios (sem queries no controller) |
| **DTO** | Dados externos trafegam em objetos tipados (`CheckoutFeeBreakdown`, `AsaasAccountData`) |
| **Job Queue** | Processos lentos (subconta, estorno) são assíncronos via RabbitMQ |
| **Auditoria** | Toda chamada à API Asaas é registrada em `asaas_transactions` |

---

## 2. Estrutura de Arquivos

```
app/
├── DTOs/
│   ├── CheckoutFeeBreakdown.php     # Breakdown financeiro do checkout
│   └── AsaasAccountData.php         # Dados retornados ao criar subconta
│
├── Enums/
│   ├── PaymentFeeMode.php           # CUSTOMER | PRODUCER
│   ├── AsaasAccountStatus.php       # PENDING | ACTIVE | INACTIVE | REJECTED
│   └── OrderChargebackStatus.php    # REQUESTED | IN_DISPUTE | REVERSED | DONE
│
├── Exceptions/
│   ├── AsaasException.php           # Erro da API Asaas com response
│   └── ProducerNotReadyException.php # Produtor sem conta ativa
│
├── Models/
│   ├── Producer.php                 # + campos Asaas + isAsaasReady()
│   ├── Order.php                    # + gateway_fee, producer_amount, etc.
│   ├── AsaasTax.php                 # Tabela de taxas dinâmicas
│   ├── ProducerPaymentMethod.php    # Métodos aceitos por produtor
│   └── AsaasTransaction.php         # Auditoria de todas as chamadas
│
├── Rules/
│   └── ProducerCanCreateEventRule.php # Valida se produtor pode criar evento
│
├── Jobs/
│   ├── CreateAsaasSubaccountJob.php  # Criação assíncrona da subconta
│   └── ProcessAsaasRefundJob.php     # Estorno assíncrono (chargeback)
│
├── Services/
│   ├── Payments/
│   │   ├── AsaasClient.php           # HTTP client centralizado
│   │   ├── AsaasPaymentGateway.php   # Gateway de pagamentos (atualizado)
│   │   ├── AsaasAccountService.php   # Subcontas
│   │   ├── AsaasFeeCalculatorService.php # Cálculo de taxas
│   │   ├── AsaasSplitService.php     # Montagem do split
│   │   └── AsaasRefundService.php    # Cancelamentos e estornos
│   ├── ProducerService.php           # Negócio do produtor
│   ├── ProducerBalanceReleaseService.php # Regras de liberação de saldo
│   ├── CheckoutService.php           # Checkout (atualizado com taxas)
│   ├── OrderPaymentService.php       # Orquestração do pagamento (atualizado)
│   └── PaymentWebhookService.php     # Webhooks (atualizado: REFUND/CHARGEBACK)
│
├── Repositories/
│   ├── ProducerRepository.php        # + findByUserId, getOrdersForProducer
│   └── OrderRepository.php           # + cancelOrder
│
├── Http/
│   ├── Controllers/
│   │   ├── ProducerController.php         # Perfil + configurações financeiras
│   │   ├── ProducerFinancialController.php # Dashboard + pedidos + estorno + calculadora
│   │   ├── EventController.php             # + validação ProducerCanCreateEventRule
│   │   └── AsaasWebhookController.php      # + eventos CHARGEBACK/REFUND
│   ├── Requests/
│   │   ├── CompleteFinancialProfileRequest.php
│   │   ├── ProducerPaymentSettingsRequest.php
│   │   └── CalculatorRequest.php
│   └── Resources/
│       ├── ProducerResource.php
│       └── ProducerOrderResource.php
│
database/
├── migrations/
│   ├── …_add_asaas_fields_to_producers_table.php
│   ├── …_create_asaas_taxes_table.php
│   ├── …_create_producer_payment_methods_table.php
│   ├── …_create_asaas_transactions_table.php
│   └── …_add_fee_fields_to_orders_table.php
└── seeders/
    └── AsaasTaxSeeder.php
```

---

## 3. Banco de Dados

### Tabela `producers` — novos campos

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `fantasy_name` | `varchar` | Nome fantasia |
| `phone` | `varchar` | Telefone do produtor |
| `email` | `varchar` | E-mail do produtor |
| `address` | `json` | Endereço completo |
| `asaas_account_id` | `varchar` | ID da subconta no Asaas |
| `asaas_wallet_id` | `varchar` | Wallet ID para receber split |
| `asaas_status` | `varchar` | `PENDING \| ACTIVE \| INACTIVE \| REJECTED` |
| `asaas_onboarding_completed` | `boolean` | Se completou o cadastro financeiro |
| `asaas_created_at` | `timestamp` | Quando a subconta foi criada |
| `ticket_commission_percentage` | `decimal(5,2)` | Comissão da plataforma (padrão: 5%) |
| `payment_fee_mode` | `varchar` | `CUSTOMER \| PRODUCER` |

### Tabela `asaas_taxes`

Taxas dinâmicas do Asaas. Nunca hardcoded no código.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `payment_type` | `varchar` | `PIX, CREDIT_CARD, BOLETO, DEBIT_CARD` |
| `installment_min` | `tinyint` | Parcela mínima que essa taxa cobre |
| `installment_max` | `tinyint` | Parcela máxima |
| `fixed_fee` | `int` | Taxa fixa em **centavos** |
| `percentage_fee` | `decimal(5,4)` | Taxa percentual (ex: `0.0299` = 2,99%) |
| `valid_from / valid_until` | `date` | Validade da taxa (para promoções) |
| `active` | `boolean` | Se está ativa |

### Tabela `producer_payment_methods`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `producer_id` | `FK` | Produtor dono |
| `payment_method` | `varchar` | `PIX, CREDIT_CARD, BOLETO, DEBIT_CARD` |
| `max_installments` | `tinyint` | Máximo de parcelas aceitas |
| `active` | `boolean` | Se está ativo |

### Tabela `asaas_transactions`

Auditoria completa de **toda** chamada feita ao Asaas.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `asaas_payment_id` | `varchar` | ID do pagamento no Asaas |
| `producer_id / order_id / event_id` | `FK` | Contexto da transação |
| `type` | `varchar` | `PAYMENT, REFUND, CHARGEBACK, SUBCONTA` |
| `status` | `varchar` | Status da operação |
| `amount / fee_amount / net_amount` | `int` | Valores em centavos |
| `request_payload` | `json` | O que foi enviado ao Asaas |
| `response_payload` | `json` | O que o Asaas respondeu |
| `error_message` | `text` | Mensagem de erro se houver |

### Tabela `orders` — novos campos

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `ticket_amount` | `int` | Valor bruto dos ingressos (centavos) |
| `gateway_fee` | `int` | Taxa do gateway (centavos) |
| `platform_commission` | `int` | Comissão da plataforma (centavos) |
| `producer_amount` | `int` | Valor líquido do produtor (centavos) |
| `installments` | `tinyint` | Número de parcelas |
| `payment_fee_mode` | `varchar` | `CUSTOMER \| PRODUCER` |
| `chargeback_status` | `varchar` | Status do chargeback se houver |
| `refunded_at` | `timestamp` | Quando foi estornado |

---

## 4. Fluxo Completo End-to-End

### 4.1 Cadastro de Produtor

```
POST /auth/register (role: producer)
         │
         ▼
   AuthService::register()
         │
         ├─── DB Transaction ──────────────────────────────────────────┐
         │    1. ProducerRepository::create()                          │
         │       → producers: name, cnpj, phone, email, address        │
         │    2. ProducerPaymentMethod::create()                        │
         │       → PIX ativo por padrão                                │
         │    3. UserRepository::create()                               │
         │       → users: role=producer, producer_id                   │
         └─────────────────────────────────────────────────────────────┘
         │
         ▼
   CreateAsaasSubaccountJob::dispatch()
   (Queue: asaas.subaccounts)
         │
         ▼ (assíncrono)
   AsaasAccountService::createSubaccount()
         │
         ├─── POST /accounts → Asaas API
         │    payload: name, cpfCnpj, email, phone, address
         │
         ▼
   Atualiza producer:
     asaas_account_id = response.id
     asaas_wallet_id  = response.walletId
     asaas_status     = ACTIVE
     asaas_onboarding_completed = true
```

> **Nota:** A criação da subconta é **assíncrona** (Job) para não bloquear o registro. O produtor entra com status `PENDING` e fica `ACTIVE` quando o Job conclui.

---

### 4.2 Cadastro via Google OAuth

Quando o produtor entra via Google, os dados de CNPJ, endereço e telefone podem estar ausentes. Nesse caso:

```
GET /auth/google/callback
         │
         ▼
   GoogleAuthController → AuthService::loginWithGoogle()
   → Usuário criado SEM cnpj/phone → asaas_onboarding_completed = false

         │
         ▼  (Frontend detecta needs_financial_profile = true)

POST /producer/complete-financial-profile
         │  { cnpj, phone, address, fantasy_name, email }
         │
         ▼
   ProducerService::completeFinancialProfile()
         │
         ├─── Atualiza campos no produtor
         └─── AsaasAccountService::createSubaccount()
                   │
                   └─── Subconta criada imediatamente (síncrono aqui)
```

---

### 4.3 Criação de Evento

```
POST /events
         │
         ▼
   EventController::create()
         │
         ▼
   Validator::make(['producer' => $producer], [
       'producer' => ['required', new ProducerCanCreateEventRule($producer)]
   ])
         │
         ├── asaas_account_id = null?
         │   → Erro 422: "Configure sua conta financeira antes de criar eventos."
         │
         ├── asaas_status ≠ ACTIVE?
         │   → Erro 422: "Sua conta Asaas ainda não está ativa."
         │
         └── OK → EventService::create()
```

> **Bypass:** `ASAAS_BYPASS_PRODUCER_VALIDATION=true` desativa essa validação em desenvolvimento/sandbox.

---

### 4.4 Checkout e Cálculo de Taxas

```
POST /checkout  ou  POST /cart/checkout
         │
         ▼
   CheckoutService::checkoutFromCart() / checkoutFromItems()
         │
         ├─ 1. Resolve itens e calcula ticket_amount (soma dos ingressos)
         │
         ├─ 2. Carrega Event.producer
         │
         ├─ 3. AsaasFeeCalculatorService::calculateForProducer()
         │       │
         │       ├─ Busca taxa na tabela asaas_taxes
         │       │   WHERE payment_type = 'PIX' (ou CREDIT_CARD)
         │       │     AND installment_min <= N <= installment_max
         │       │     AND active = true
         │       │     AND valid_from/until dentro da data atual
         │       │
         │       ├─ gateway_fee = fixed_fee + (ticket_amount × percentage_fee)
         │       │
         │       ├─ platform_commission = ticket_amount × commission_percentage
         │       │
         │       ├─ Se payment_fee_mode = CUSTOMER:
         │       │     total_customer = ticket_amount + gateway_fee
         │       │     producer_amount = ticket_amount - platform_commission
         │       │
         │       └─ Se payment_fee_mode = PRODUCER:
         │             total_customer = ticket_amount
         │             producer_amount = ticket_amount - platform_commission - gateway_fee
         │
         ├─ 4. Cria Order no banco com todos os campos financeiros
         │
         └─ 5. OrderPaymentService::processPayment(order, cardToken, breakdown)
```

**Exemplo — PIX com fee_mode = CUSTOMER:**

```
ticket_amount        = R$ 50,00
gateway_fee (PIX)    = R$ 0,99
platform_commission  = R$ 50 × 5% = R$ 2,50
total_customer       = R$ 50 + R$ 0,99 = R$ 50,99
producer_amount      = R$ 50 - R$ 2,50 = R$ 47,50
```

**Exemplo — Cartão 1x com fee_mode = PRODUCER:**

```
ticket_amount        = R$ 100,00
gateway_fee (CC 1x)  = R$ 0,49 + (R$ 100 × 2,99%) = R$ 3,48
platform_commission  = R$ 100 × 5% = R$ 5,00
total_customer       = R$ 100,00  ← comprador paga apenas o ingresso
producer_amount      = R$ 100 - R$ 5 - R$ 3,48 = R$ 91,52
```

---

### 4.5 Pagamento com Split

```
OrderPaymentService::processPayment()
         │
         ▼
   AsaasPaymentGateway::createPixPayment() / createCreditCardPayment()
         │
         ├─ 1. getOrCreateCustomer(user)
         │       └─ GET /customers?email=... → se não existe → POST /customers
         │
         ├─ 2. Monta payload base:
         │     { customer, billingType, value: total_customer/100,
         │       dueDate, externalReference: order.id }
         │
         ├─ 3. Se CREDIT_CARD: adiciona creditCardToken + installmentCount
         │
         ├─ 4. Se producer.asaas_wallet_id:
         │       AsaasSplitService::buildSplitPayload()
         │       → split: [{ walletId: producer.asaas_wallet_id,
         │                   fixedValue: producer_amount/100 }]
         │
         ├─ 5. AsaasClient::createPayment(payload)
         │       → POST /payments → Asaas API
         │
         ├─ 6. logTransaction() → asaas_transactions
         │
         └─ 7. Se PIX: busca QR code → GET /payments/{id}/pixQrCode
```

> O split funciona assim: a plataforma cria a cobrança pela **conta principal** e repassa o `producer_amount` diretamente para o wallet do produtor. A plataforma retém automaticamente a comissão.

---

### 4.6 Confirmação via Webhook

```
POST /webhooks/asaas
  Header: asaas-access-token: {ASAAS_WEBHOOK_TOKEN}
         │
         ▼
   AsaasWebhookController::handle()
         │
         ├─ Valida token
         ├─ Extrai payment_id e event_name
         ├─ mapEventToStatus(): traduz evento → status interno
         ├─ Verifica idempotência (payment_webhook_events)
         │
         └─ ProcessAsaasWebhookJob::dispatch()
              (Queue: payments.webhook)
                   │
                   ▼
            PaymentWebhookService::process()
                   │
                   ├── CONFIRMED / RECEIVED
                   │       └─ confirmPayment() → status PAID
                   │          GenerateTicketsJob → gera ingressos + QR
                   │          SendPurchaseEmailJob → e-mail comprador
                   │
                   ├── REFUNDED / DELETED
                   │       └─ cancelOrder() + libera estoque das reservas
                   │
                   ├── CHARGEBACK_REQUESTED / CHARGEBACK_DISPUTE
                   │       └─ atualiza chargeback_status
                   │          Se REVERSED → ProcessAsaasRefundJob
                   │
                   └── FAILED / OVERDUE
                           └─ failPayment() + libera reservas
                              SendPurchaseEmailJob (falha)
```

**Eventos Asaas suportados:**

| Evento Asaas | Ação |
|-------------|------|
| `PAYMENT_CONFIRMED` | Aprova pedido, gera ingressos |
| `PAYMENT_RECEIVED` | Aprova pedido, gera ingressos |
| `PAYMENT_REFUNDED` | Cancela pedido, libera estoque |
| `PAYMENT_DELETED` | Cancela pedido, libera estoque |
| `PAYMENT_OVERDUE` | Falha no pagamento, libera estoque |
| `PAYMENT_CHARGEBACK_REQUESTED` | Marca `chargeback_status = REQUESTED` |
| `PAYMENT_CHARGEBACK_DISPUTE` | Marca `chargeback_status = IN_DISPUTE` |
| `PAYMENT_CHARGEBACK_REVERSED` | Dispara estorno via `ProcessAsaasRefundJob` |

---

### 4.7 Cancelamento e Estorno

```
POST /producer/financial/orders/{id}/refund
  (autenticado como produtor)
         │
         ▼
   ProducerFinancialController::refundOrder()
         │
         ├─ Verifica que o pedido pertence a um evento do produtor
         │
         ▼
   AsaasRefundService::refundOrder()
         │
         ├─ Se payment_status = PAID/CONFIRMED:
         │       AsaasClient::refundPayment(asaas_payment_id)
         │       → POST /payments/{id}/refund
         │
         ├─ Se payment_status = PENDING:
         │       AsaasClient::cancelPayment(asaas_payment_id)
         │       → DELETE /payments/{id}
         │
         └─ DB Transaction:
               order.status = CANCELLED
               order.payment_status = CANCELLED
               order.refunded_at = now()
               logTransaction(type=REFUND)
```

---

### 4.8 Chargeback

```
Webhook: PAYMENT_CHARGEBACK_REQUESTED
         │
         ▼
   PaymentWebhookService::handleChargeback()
         │
         ├─ Atualiza order.chargeback_status = REQUESTED
         │
         └─ Log de warning com dados do pedido

Webhook: PAYMENT_CHARGEBACK_REVERSED
         │
         ▼
   order.chargeback_status = REVERSED
         │
         └─ ProcessAsaasRefundJob::dispatch()
                   │
                   ▼
            AsaasRefundService::refundOrder()
            (estorna automaticamente)
```

---

### 4.9 Liberação de Saldo

```
Regra: ingressos só podem ser sacados 1 dia após o evento.

ProducerBalanceReleaseService::getReleaseDate(event)
  → event.date + 1 dia (início do dia)

ProducerBalanceReleaseService::isReleased(event)
  → now() >= release_date

ProducerBalanceReleaseService::getFutureReleasesForProducer(producer)
  → Para cada evento futuro com pedidos PAID:
       {
         event_id, event_name, event_date,
         release_date,
         is_released: false/true,
         days_until_release: N,
         producer_amount: R$ X
       }
```

**Exemplo:**

```
Evento: 10/06 22:00
Release date: 11/06 00:00
→ A partir de 11/06 o produtor pode sacar os valores
```

> O controle do bloqueio físico do saldo é feito pelo Asaas internamente (D+2 após liquidação). O `ProducerBalanceReleaseService` fornece a visibilidade desses valores no dashboard.

---

### 4.10 Dashboard Financeiro

```
GET /producer/financial/dashboard
         │
         ▼
   ProducerFinancialController::dashboard()
         │
         ├─ ProducerService::getFinancialDashboard(producer)
         │       │
         │       └─ AsaasAccountService::getBalance(producer)
         │               → GET /finance/balance (live do Asaas)
         │
         ├─ ProducerBalanceReleaseService::getFutureReleasesForProducer()
         │       → Cálculo local com dados do DB
         │
         └─ ProducerBalanceReleaseService::getTotalPendingReleaseCents()

Resposta:
{
  "data": {
    "asaas_ready": true,
    "asaas_status": "ACTIVE",
    "available_balance": 150.00,     ← saldo disponível no Asaas (live)
    "pending_amount": 2450.00,       ← pedidos pagos em eventos futuros
    "total_pending_release": 2450.00,
    "future_releases": [
      {
        "event_id": 5,
        "event_name": "Show XYZ",
        "event_date": "2026-07-15",
        "release_date": "2026-07-16",
        "is_released": false,
        "days_until_release": 17,
        "producer_amount": 2450.00
      }
    ]
  }
}
```

---

## 5. Serviços e Responsabilidades

| Serviço | Responsabilidade | Depende de |
|---------|-----------------|------------|
| `AsaasClient` | HTTP client centralizado — autenticação, logging, retry, auditoria | `AsaasTransaction` |
| `AsaasAccountService` | CRUD de subcontas no Asaas | `AsaasClient` |
| `AsaasFeeCalculatorService` | Lookup de taxas no DB + cálculo do breakdown | `AsaasTax` |
| `AsaasSplitService` | Monta o array `split` para o payload de pagamento | `CheckoutFeeBreakdown` |
| `AsaasRefundService` | Cancela cobranças e solicita estornos | `AsaasClient`, `OrderRepository` |
| `AsaasPaymentGateway` | Cria cobranças PIX/CC com split | `AsaasClient`, `AsaasSplitService` |
| `OrderPaymentService` | Orquestra criação do pagamento e persiste dados | `AsaasPaymentGateway` |
| `CheckoutService` | Orquestra todo o fluxo de checkout | `AsaasFeeCalculatorService`, `OrderPaymentService` |
| `PaymentWebhookService` | Processa eventos do webhook Asaas | `OrderRepository`, `AsaasRefundService` |
| `ProducerService` | Negócio do produtor (perfil, settings, calculadora, dashboard) | `AsaasAccountService`, `AsaasFeeCalculatorService` |
| `ProducerBalanceReleaseService` | Regras de liberação de saldo | `Order`, `Event` |

---

## 6. Endpoints da API

Todos os endpoints abaixo requerem autenticação `auth:sanctum` e usuário com `role = producer`.

### Perfil e Configurações

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/producer` | Dados do produtor (inclui status Asaas) |
| `POST` | `/producer/complete-financial-profile` | Completa perfil de produtor Google OAuth |
| `PATCH` | `/producer/payment-settings` | Atualiza payment_fee_mode e métodos aceitos |

### Dashboard Financeiro

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/producer/financial/dashboard` | Saldo Asaas (live) + liberações futuras |
| `GET` | `/producer/financial/orders` | Pedidos dos eventos do produtor (paginado) |
| `POST` | `/producer/financial/orders/{id}/refund` | Cancelar pedido e solicitar estorno |
| `POST` | `/producer/financial/calculate` | Calculadora financeira |

### Calculadora — Payload de entrada

```json
{
  "ticket_price": 50.00,
  "quantity": 2,
  "payment_type": "CREDIT_CARD",
  "installments": 3,
  "fee_mode": "CUSTOMER"
}
```

### Calculadora — Resposta

```json
{
  "data": {
    "ticket_amount": 100.00,
    "gateway_fee": 3.97,
    "platform_commission": 5.00,
    "producer_amount": 95.00,
    "total_customer_amount": 103.97,
    "payment_fee_mode": "CUSTOMER",
    "installments": 3
  }
}
```

### Payment Settings — Payload

```json
{
  "payment_fee_mode": "CUSTOMER",
  "payment_methods": [
    { "payment_method": "PIX", "max_installments": 1, "active": true },
    { "payment_method": "CREDIT_CARD", "max_installments": 12, "active": true }
  ]
}
```

---

## 7. Cálculo de Taxas e Split

### Lookup de Taxas

A tabela `asaas_taxes` é consultada com:

```sql
SELECT * FROM asaas_taxes
WHERE payment_type = :type
  AND active = true
  AND installment_min <= :installments
  AND installment_max >= :installments
  AND (valid_from IS NULL OR valid_from <= CURDATE())
  AND (valid_until IS NULL OR valid_until >= CURDATE())
ORDER BY id DESC
LIMIT 1
```

O resultado fica em cache por **1 hora** (`Cache::remember`).

### Tabela de Taxas (seed inicial)

| Método | Parcelas | Taxa Fixa | Percentual |
|--------|----------|-----------|------------|
| PIX | 1 | R$ 0,99 | 0% |
| Boleto | 1 | R$ 0,99 | 0% |
| Débito | 1 | R$ 0,35 | 1,89% |
| Crédito | 1x | R$ 0,49 | 2,99% |
| Crédito | 2–6x | R$ 0,49 | 3,49% |
| Crédito | 7–12x | R$ 0,49 | 3,99% |

### Fórmula do Split

```
gateway_fee = fixed_fee + round(ticket_amount × percentage_fee)

platform_commission = round(ticket_amount × commission_pct / 100)

# CUSTOMER absorve taxa:
total_customer = ticket_amount + gateway_fee
producer_amount = ticket_amount - platform_commission

# PRODUCER absorve taxa:
total_customer = ticket_amount
producer_amount = ticket_amount - platform_commission - gateway_fee

# Payload enviado ao Asaas:
split: [{
  walletId: producer.asaas_wallet_id,
  fixedValue: producer_amount / 100  # em reais
}]
```

> **Regra crítica:** A comissão da plataforma é **sempre calculada sobre o valor bruto** dos ingressos, nunca sobre o líquido.

---

## 8. Configuração do Ambiente

### Filas RabbitMQ necessárias

| Queue | Job(s) |
|-------|--------|
| `payments.create` | `CreatePaymentJob` |
| `payments.webhook` | `ProcessAsaasWebhookJob` |
| `tickets.generation` | `GenerateTicketsJob` |
| `tickets.expiration` | `ExpireTicketReservationJob`, `ExpireCartReservationJob` |
| `emails` | `SendPurchaseEmailJob`, `SendTicketsEmailJob` |
| `geocoding` | `GeocodePlaceJob` |
| `asaas.subaccounts` | `CreateAsaasSubaccountJob` ← **novo** |
| `asaas.refunds` | `ProcessAsaasRefundJob` ← **novo** |

### Webhook Asaas

Configure no painel Asaas:

- **URL:** `https://seudominio.com/webhooks/asaas`
- **Token:** `ASAAS_WEBHOOK_TOKEN` (qualquer string segura)
- **Eventos:** todos os `PAYMENT_*`

---

## 9. Variáveis de Ambiente

Adicionar ao `.env`:

```env
# Asaas existentes
ASAAS_API_KEY=your_api_key_here
ASAAS_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_TOKEN=your_webhook_secret
ASAAS_WEBHOOK_PROCESS_SYNC=false
ASAAS_BILLING_TYPE=PIX
ASAAS_PAYMENT_DUE_DAYS=1

# Asaas novos
ASAAS_PLATFORM_WALLET_ID=your_platform_wallet_id
ASAAS_DEFAULT_COMMISSION_PERCENTAGE=5
ASAAS_TIMEOUT=30
ASAAS_RETRY_TIMES=3
ASAAS_RETRY_SLEEP=1000
ASAAS_BYPASS_PRODUCER_VALIDATION=false   # true apenas em desenvolvimento
```

> **Como obter o `ASAAS_PLATFORM_WALLET_ID`:** No painel Asaas, vá em *Minha Conta → Dados da Conta → Wallet ID*.

---

## 10. Executando Migrations e Seeders

```bash
# Rodar todas as migrations novas
php artisan migrate

# Popular tabela de taxas Asaas
php artisan db:seed --class=AsaasTaxSeeder

# Ou rodar apenas o seeder
php artisan db:seed --class=Database\\Seeders\\AsaasTaxSeeder
```

---

## 11. Decisões Arquiteturais

### Por que `AsaasClient` em vez de injetar `Http` direto nos serviços?

O projeto original tinha chamadas HTTP espalhadas em `AsaasPaymentGateway`. Isso dificultava:
- Centralizar logs e auditoria
- Aplicar retry de forma uniforme
- Testar os serviços individualmente (mock)

O `AsaasClient` é um **singleton** registrado no `AppServiceProvider`, centraliza autenticação, logging e retry, e todos os serviços Asaas dependem dele.

### Por que o split usa `fixedValue` e não `percentualValue`?

O `percentualValue` no Asaas é impreciso para valores com casas decimais. Usando `fixedValue` (calculado previamente pelo `AsaasSplitService`) garantimos que o produtor receba exatamente o valor calculado e a plataforma retém o restante, eliminando erros de arredondamento.

### Por que a subconta é criada de forma assíncrona?

A criação da subconta no Asaas pode levar alguns segundos ou falhar por instabilidade. Colocar esse processo em um Job:
- Não bloqueia o registro do produtor
- Permite retry automático (até 5 tentativas com backoff exponencial)
- Mantém a UX fluida

### Por que `ProducerBalanceReleaseService` não busca o saldo do Asaas?

O saldo disponível é buscado em tempo real do Asaas no momento que o produtor abre o dashboard. Mas o **detalhamento por evento** (quanto vai liberar e quando) é calculado localmente, pois o Asaas não tem visão dos nossos "eventos" como entidades. Essa separação mantém o dashboard responsivo.

### Por que as taxas ficam no banco e não no código?

As taxas do Asaas mudam periodicamente (promoções, reajustes). Manter na tabela `asaas_taxes` permite:
- Atualizar sem deploy de código
- Suportar taxas com validade (`valid_from`, `valid_until`)
- Múltiplas taxas para o mesmo método (histórico)
- Rollback fácil ativando/desativando registros

### Idempotência nos webhooks

A tabela `payment_webhook_events` previne processamento duplicado. O `AsaasWebhookController` verifica via `PaymentWebhookAuditService::wasProcessed()` antes de despachar o job.
