
# Paysera Fund Transfer API
 
Secure fund transfer REST API built with PHP 8.4 + Symfony 8 + MySQL 8 + Redis 7.
 
## Architecture Highlights
- **Redis distributed lock** (SETNX) prevents race conditions under high load
- **MySQL SELECT FOR UPDATE** ensures atomic balance updates
- **Idempotency keys** prevent duplicate transfers on client retries
- **Doctrine transactions** guarantee debit+credit atomicity
- **Swagger UI** available at http://localhost:8080/api/doc
 
## Quick Start
 
> Requirements: [Docker Desktop](https://www.docker.com/products/docker-desktop)
 
```bash
# 1. Clone and start
git clone https://github.com/kmtech183/paysera-transfer
cd paysera-transfer
cp .env.example .env
docker compose up -d && docker compose exec php composer install && docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
 
#2. Open Swagger UI
http://localhost:8080/api/doc

# 3. Run All tests
docker compose exec php bash -c "DATABASE_URL='mysql://root:rootsecret@db:3306/paysera?serverVersion=8.0' php bin/console doctrine:database:create --env=test --if-not-exists --no-interaction"
docker compose exec php bash -c "DATABASE_URL='mysql://root:rootsecret@db:3306/paysera?serverVersion=8.0' php bin/console doctrine:migrations:migrate --env=test --no-interaction"
docker compose exec php php bin/phpunit --testdox
```
 
API: http://localhost:8080  |  Swagger UI: http://localhost:8080/api/doc
 
## API Endpoints
 
| Method | Endpoint                    | Description              |
|--------|-----------------------------|--------------------------|
| POST   | /api/v1/accounts            | Create account           |
| GET    | /api/v1/accounts/{uuid}     | Get balance              |
| POST   | /api/v1/transfers           | Transfer funds           |
| GET    | /api/v1/transfers/{uuid}    | Get transaction status   |
 
## Example Transfer
 
```bash
# Create sender (copy uuid from response)
curl -X POST http://localhost:8080/api/v1/accounts \
  -H 'Content-Type: application/json' \
  -d '{"owner_name":"Alice","currency":"EUR","initial_balance":500}'
 
# Create receiver
curl -X POST http://localhost:8080/api/v1/accounts \
  -H 'Content-Type: application/json' \
  -d '{"owner_name":"Bob","currency":"EUR"}'
 
# Transfer 100 EUR
curl -X POST http://localhost:8080/api/v1/transfers \
  -H 'Content-Type: application/json' \
  -d '{
    "from_account": "ALICE_UUID",
    "to_account":   "BOB_UUID",
    "amount":       "100.00",
    "currency":     "EUR",
    "idempotency_key": "unique-key-001"
  }'
```
 
## Time Spent
~14 hours
 
## AI Tools Used
Claude (Anthropic) — used for boilerplate generation.
All architecture decisions, code review, and understanding are my own.