## Gerenciador de Pagamentos Multi‑Gateway (Teste Prático BeTalent) (Nível 3)

API RESTful em Laravel para gerenciar pagamentos utilizando múltiplos gateways com fallback, cálculo de valor no back‑end, controle de acesso por roles e cobertura de testes automatizados (Feature, Integration e Unit).

---

## 🧭 Sumário

- **Sobre o projeto**
- **Requisitos**
- **Configuração do ambiente**
- **Subindo o projeto com Sail (Docker)**
- **Configuração dos mocks de gateways**
- **Rotas principais**
- **Regras de acesso (roles)**
- **Modelo de dados (visão geral)**
- **Testes automatizados**
- **Ferramentas de apoio utilizadas**
- **Impressões pessoais sobre o desafio**

---

## 📋 Sobre o projeto

Este projeto implementa o desafio proposto em `INSTRUCOES.md` para o **Teste Prático Back‑end BeTalent**, utilizando **Laravel 12** e **MySQL**.

Principais características:

- Pagamentos processados por **múltiplos gateways** com **ordem de prioridade** e **fallback automático** em caso de falha.
- Valor da compra sempre calculado no **back‑end** a partir dos produtos e quantidades.
- **Gateways configuráveis** (ativos, inativos, prioridade) via API.
- **Controle de acesso por roles**:
  - `ADMIN`, `MANAGER`, `FINANCE`, `USER`.
- Persistência completa de:
  - Clientes, transações, produtos e relacionamento `transaction_products` (inclui snapshot de preço e subtotal).
- **Testes automatizados** cobrindo:
  - Fluxos de autenticação, compras, clientes, usuários, produtos, gateways e regras de role.
  - Regras internas de orquestração de gateways e DTOs.

---

## ✅ Requisitos

### Requisitos técnicos

- Docker e Docker Compose instalados (com WSL2).
- PHP 8.5+ (opcional se for rodar **apenas** via Sail).
- Composer.

### Tecnologias principais

- **Laravel 12**
- **Laravel Sail** (ambiente Docker oficial)
- **MySQL 8.4**
- **Laravel Sanctum** (autenticação via token)
- **Pest / PHPUnit** (testes)

---

## ⚙️ Configuração do ambiente

1. **Clonar o repositório**

```bash
git clone https://github.com/felipemaejima/beTalent-TestePratico.git
cd beTalent-TestePratico/app
```

2. **Instalar dependências PHP**

Se você tiver PHP e Composer instalados localmente:

```bash
composer install
```

Se preferir usar apenas o Sail:

```bash
composer install --ignore-platform-reqs
```

3. **Criar o arquivo `.env`**

Caso ainda não exista:

```bash
cp .env.example .env
```

Os valores padrão já estão preparados para uso com Sail e MySQL:

- `DB_CONNECTION=mysql`
- `DB_HOST=mysql`
- `DB_DATABASE=app`
- `DB_USERNAME=sail`
- `DB_PASSWORD=password`

Também existem variáveis para os gateways:

- `GATEWAY1_URL=http://host.docker.internal:3001`
- `GATEWAY2_URL=http://host.docker.internal:3002`

4. **Gerar chave da aplicação**

```bash
php artisan key:generate
```

> Se estiver usando apenas o Sail, este comando pode ser executado dentro do container (ver próxima seção).

---

## 🐳 Subindo o projeto com Sail (Docker)

O projeto utiliza **Laravel Sail** e um arquivo `compose.yaml` para orquestrar:

- **laravel.test**: aplicação Laravel (PHP‑FPM + Nginx).
- **mysql**: banco de dados MySQL 8.4 (com banco de teste criado automaticamente).

### 1. Subir os containers

Na pasta `app`, dentro do bash do WSL:

```bash
./vendor/bin/sail build
```
 > Caso os containers do Docker ainda não estejam construidos

```bash
./vendor/bin/sail up -d
```

### 2. Executar migrações e seeders

```bash
./vendor/bin/sail artisan migrate
```

Se existirem seeders relevantes (por exemplo, usuários padrão ou gateways), rode:

```bash
./vendor/bin/sail artisan db:seed
```

### 3. Acessar a aplicação

Por padrão, a aplicação fica disponível em:

- `http://localhost` (porta 80 mapeada pelo Sail)

---

## 🔌 Configuração dos mocks de gateways

O projeto foi estruturado para trabalhar com os mocks de gateways descritos em `INSTRUCOES.md`.

### Subindo os mocks com autenticação

Em outro terminal (fora do Sail):

```bash
docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock
```

Isso disponibiliza:

- **Gateway 1**: `http://localhost:3001`
- **Gateway 2**: `http://localhost:3002`

As rotas seguem o enunciado:

- Gateway 1:
  - `POST /login`
  - `GET /transactions`
  - `POST /transactions`
  - `POST /transactions/:id/charge_back`
- Gateway 2:
  - `GET /transacoes`
  - `POST /transacoes`
  - `POST /transacoes/reembolso`

### Verificação automática nos testes de integração

Os testes de integração utilizam `IntegrationTestCase`, que:

- Verifica se o **Gateway 1** aceita login com as credenciais de `config/gateways.php`.
- Verifica se o **Gateway 2** responde à listagem de transações.
- Caso algum mock não esteja acessível, os testes de integração são **marcados como ignorados** com uma mensagem clara explicando como subir o container.

---

## 🧪 Testes automatizados

O projeto utiliza **Pest** e **PHPUnit** com três camadas de testes:

- **Unit** (`tests/Unit`):
  - `GatewayManagerTest`: regras internas de fallback, seleção de gateway, tratamento de falhas e refund.
  - `ModelTest`: métodos de domínio (`isAdmin`, `isPaid`, `isRefunded`, escopos, relacionamentos, soft delete, subtotal, snapshot de preço).
- **Feature** (`tests/Feature`):
  - `AuthTest`: login/logout, validação, proteção de rotas privadas.
  - `UserTest`: CRUD de usuários com roles e validação.
  - `ProductTest`: CRUD de produtos com roles e soft delete.
  - `ClientTest`: listagem e detalhe de clientes com histórico de compras.
  - `GatewayTest`: ativação/desativação e prioridade de gateways com validação.
  - `TransactionTest`: fluxo de compra, validações, criação/reuso de cliente, refund, listagem e regras de role.
- **Integration** (`tests/Integration`):
  - `PurchaseFlowIntegrationTest`: fluxo completo contra os mocks reais dos gateways, incluindo fallback, cálculo de valor, persistência em banco, reembolso e roles.

### Como rodar os testes

> Todos os comandos abaixo assumem que você já está com o Sail rodando (`./vendor/bin/sail up -d`).

#### 1. Rodar toda a suíte de testes

```bash
./vendor/bin/sail artisan test --coverage
```

#### 2. Rodar apenas testes de unidade

```bash
./vendor/bin/sail artisan test --testsuite=Unit
```

#### 3. Rodar apenas testes de feature

```bash
./vendor/bin/sail artisan test --testsuite=Feature
```

#### 4. Rodar apenas testes de integração (que dependem dos mocks)

Certifique‑se de que o container dos mocks de gateway está rodando (veja seção “Configuração dos mocks de gateways”).

```bash
./vendor/bin/sail artisan test --testsuite=Integration
```

Você também pode filtrar por nome de teste específico:

```bash
./vendor/bin/sail artisan test --filter=PurchaseFlowIntegrationTest
```

---

## 🌐 Rotas principais

### Rotas públicas

- `POST /api/login`
  - Autenticação do usuário e retorno de token Sanctum.
- `POST /api/transactions`
  - Criação de uma transação (compra) informando produto e quantidade.
  - Qualquer usuário externo pode realizar a compra (sem autenticação).

### Rotas privadas (exemplos)

Todas as rotas privadas utilizam middleware `auth:sanctum` + `RoleMiddleware`:

- **Usuários**
  - `GET /api/users` (ADMIN, MANAGER)
  - `POST /api/users` (ADMIN, MANAGER)
  - `GET /api/users/{user}` (ADMIN, MANAGER)
  - `PUT /api/users/{user}` (ADMIN)
  - `DELETE /api/users/{user}` (ADMIN)
- **Produtos**
  - `GET /api/products` (ADMIN, MANAGER, FINANCE)
  - `GET /api/products/{product}` (ADMIN, MANAGER, FINANCE)
  - `POST /api/products` (ADMIN, MANAGER)
  - `PUT /api/products/{product}` (ADMIN, MANAGER)
  - `DELETE /api/products/{product}` (ADMIN, MANAGER)
- **Gateways**
  - `PATCH /api/gateways/{gateway}/toggle` (ADMIN)
  - `PATCH /api/gateways/{gateway}/priority` (ADMIN)
- **Clientes**
  - `GET /api/clients` (ADMIN, MANAGER, FINANCE)
  - `GET /api/clients/{client}` (ADMIN, MANAGER, FINANCE)
- **Transações**
  - `GET /api/transactions` (ADMIN, MANAGER, FINANCE)
  - `GET /api/transactions/{transaction}` (ADMIN, MANAGER, FINANCE)
  - `POST /api/transactions/{transaction}/refund` (ADMIN, FINANCE)

---

## 🔐 Regras de acesso (roles)

As roles são armazenadas na tabela `users.role` e aplicadas pelo `RoleMiddleware`:

- `ADMIN`
  - Acesso completo: gerencia usuários, produtos, gateways, transações e clientes.
- `MANAGER`
  - Pode gerenciar usuários (listagem/criação), gerenciar produtos, consultar clientes e transações.
- `FINANCE`
  - Pode listar produtos, clientes e transações; pode realizar reembolso.
- `USER`
  - Não possui acesso às rotas administrativas; pode utilizar o fluxo público de compra.

---

## 🗄 Modelo de dados (visão geral)

- `users`
  - `id`, `name`, `email`, `password`, `role`, timestamps, soft delete.
- `gateways`
  - `id`, `name`, `is_active`, `priority`, timestamps.
- `clients`
  - `id`, `name`, `email`, timestamps.
- `products`
  - `id`, `name`, `amount` (em centavos), timestamps, soft delete.
- `transactions`
  - `id`, `client_id`, `gateway_id`, `external_id`, `status`, `amount`, `card_last_numbers`, timestamps.
- `transaction_products`
  - `transaction_id`, `product_id`, `quantity`, `unit_amount`.

---

## 🧰 Ferramentas de apoio utilizadas

Durante o desenvolvimento deste projeto foram utilizadas ferramentas de auxílio para aumentar a qualidade do código e a velocidade de entrega:

- **Claude AI (versão web gratuita)**  
  - Apoio principalmente na construção dos testes e escrita bruta de código.
- **Assistente de IA Cursor (versão gratuita)**  
  - Auxílio direto na análise dos requisitos, cruzamento com os testes, revisão das rotas/middlewares e criação desta documentação.

Todas as decisões finais de implementação, regras de negócio e ajustes foram revisadas manualmente para garantir aderência ao enunciado do teste.

---

## 📝 Impressões pessoais sobre o projeto


- **Dificuldades encontradas**
  - Devido à circunstâncias pessoais, não consegui tempo suficiente para revisar os testes da forma que eu gostaria, gostaria de te-los deixado mais completos.
- **Pontos que eu priorizei na solução (código limpo, testes, arquitetura, etc.)**
  - Tentei deixar o código o mais limpo possível, e estruturar de forma organizada, para facilitar o entendimento da lógica e a escalabilidade do código. 
  - Resolvi adotar o uso de Laravel Sail para montar o ambiente em docker, uma vez que a organização das dependências de ambiente e containers ficam mais fáceis de gerenciar.
- **Possíveis melhorias futuras**
  - Os principais pontos de melhora que gostaria de ter acrescentado é a padronização das respostas em JSON. Não fiquei 100% satisfeito com o generalismo que tive que utilizar neste primeiro momento. Como complemento a isso, diminuir os usos de try catch (preferência pessoal) e tratar os erros de forma mais organizada.

---

## 🔚 Considerações finais

- O projeto foi estruturado para facilitar a manutenção e evolução futura, com separação clara entre camadas (controllers, serviços, DTOs, models) e boa cobertura de testes.
- A documentação e os testes foram pensados para permitir que outra pessoa rode o projeto e valide os fluxos principais rapidamente, inclusive utilizando os mocks oficiais de gateways descritos no enunciado.

