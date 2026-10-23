\# Trippers Shield

A Laravel REST API for travel insurance policy requests. An agent submits a request for a customer, the system calculates the premium, sends it to a **mock insurer**, and issues a policy. It is built to handle duplicate requests and duplicate insurer callbacks safely.

## Features

- Create a policy request with full validation
- Automatic premium calculation, stored as a snapshot at creation time
- Mock insurer integration (no real credentials needed)
- Idempotent intake: the same `agent_id` + `client_request_id` never creates two records
- Insurer callback (webhook) endpoint that ignores duplicates and blocks status regression
- Feature and unit tests

## Tech Stack

- PHP 8.2+ and Laravel 11
- MySQL (SQLite works for tests)
- PHPUnit

## Project Structure

> The code in this repo lives in `_source/`. Copy its contents into a fresh Laravel project (see Setup).

```
_source/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── PolicyRequestController.php     # create + fetch policy requests
│   │   │   └── InsurerCallbackController.php   # insurer webhook handler
│   │   └── Requests/StorePolicyRequestRequest.php   # validation rules
│   ├── Models/
│   │   ├── PolicyRequest.php                   # status transition rules
│   │   └── Policy.php
│   └── Services/
│       ├── PremiumCalculatorService.php        # pricing logic
│       └── MockInsurerService.php              # simulated insurer
├── database/migrations/                        # policy_requests, policies
├── routes/api.php
└── tests/
    ├── Feature/PolicyRequestTest.php
    └── Unit/PremiumCalculatorTest.php
```

## Setup

1. **Create a Laravel project and enter it**
   ```bash
   composer create-project laravel/laravel trippers-shield
   cd trippers-shield
   ```

2. **Copy the source files** from this repo's `_source/` folder into the project (merge `app/`, `database/`, `routes/`, `tests/`).

3. **Enable API routes** (Laravel 11 does not include them by default)
   ```bash
   php artisan install:api
   ```
   Make sure `routes/api.php` is the one from this repo.

4. **Configure the environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Edit `.env` with your database details (default database name is `trippers_shield`).

5. **Create the database and run migrations**
   ```bash
   php artisan migrate
   ```

6. **Start the server**
   ```bash
   php artisan serve
   ```
   The API is now at `http://localhost:8000/api`.

## Running Tests

```bash
php artisan test
```

## API Endpoints

Base URL: `http://localhost:8000/api`

| Method | Endpoint                | Description                              |
|--------|-------------------------|------------------------------------------|
| GET    | `/health`               | Health check                             |
| POST   | `/policy-requests`      | Create a policy request                  |
| GET    | `/policy-requests/{id}` | Get the status of a policy request       |
| POST   | `/insurer/callback`     | Insurer webhook (issued / failed update) |

### POST `/policy-requests`

**Request body**

```json
{
  "agent_id": "AGENT001",
  "client_request_id": "REQ-1001",
  "customer_name": "Rahul Sharma",
  "customer_email": "rahul@example.com",
  "customer_dob": "1990-05-15",
  "destination": "international",
  "plan_type": "standard",
  "start_date": "2026-12-01",
  "end_date": "2026-12-11",
  "coverage_amount": 100000
}
```

**Validation rules**

| Field               | Rule                                          |
|---------------------|-----------------------------------------------|
| `agent_id`          | required, string, max 50                      |
| `client_request_id` | required, string, max 100                     |
| `customer_name`     | required, string, max 255                     |
| `customer_email`    | required, valid email                         |
| `customer_dob`      | required, date, customer must be 18 or older  |
| `destination`       | `domestic`, `international` or `schengen`     |
| `plan_type`         | `basic`, `standard` or `premium`              |
| `start_date`        | required, today or later                      |
| `end_date`          | required, after `start_date`                  |
| `coverage_amount`   | numeric, between 10,000 and 10,000,000        |

**Responses**

- `201 Created`: request processed, returns the record with its final status
- `200 OK`: duplicate request, returns the existing record with `"is_duplicate": true`
- `422 Unprocessable Entity`: validation errors

**Example**

```bash
curl -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "agent_id": "AGENT001",
    "client_request_id": "REQ-1001",
    "customer_name": "Rahul Sharma",
    "customer_email": "rahul@example.com",
    "customer_dob": "1990-05-15",
    "destination": "international",
    "plan_type": "standard",
    "start_date": "2026-12-01",
    "end_date": "2026-12-11",
    "coverage_amount": 100000
  }'
```

### GET `/policy-requests/{id}`

Returns the current state of a request, including `status`, `premium_amount` and `insurer_policy_number`.

### POST `/insurer/callback`

```json
{
  "policy_request_id": 1,
  "policy_number": "TPP-20261001120000-000001",
  "status": "issued"
}
```

`status` must be `issued` or `failed`.

## Premium Calculation

```
premium = max( days × daily_rate × destination_multiplier , 5.00 )
```

| Plan     | Daily rate |
|----------|-----------:|
| basic    | $1.50      |
| standard | $3.00      |
| premium  | $6.00      |

| Destination   | Multiplier |
|---------------|-----------:|
| domestic      | 1.0x       |
| international | 1.5x       |
| schengen      | 2.0x       |

Example: standard plan, international, 10 days = 10 × 3.00 × 1.5 = **$45.00**

The minimum premium is **$5.00**.

## Request Lifecycle

```
pending → submitted → issued
                   ↘ failed
```

`issued` and `failed` are terminal states and cannot change again.

## Reliability and Design Decisions

- **Duplicate intake:** a unique database index on (`agent_id`, `client_request_id`) plus an application-level check means retries return the original record instead of creating a new one.
- **Duplicate callbacks:** if the insurer sends the same callback twice, the second one returns `200` with `is_duplicate: true` and nothing is re-processed.
- **No status regression:** allowed transitions are defined in `PolicyRequest::VALID_TRANSITIONS`. Invalid transitions return `422`.
- **Unique policy numbers:** `policies.policy_number` has a unique index, so a policy number cannot be issued twice.
- **Premium snapshot:** the premium is calculated once and stored, so later pricing changes do not alter existing requests.

## Mock Insurer

No real insurer is called. `MockInsurerService` simulates one:

- Any normal request is **issued** with a generated policy number like `TPP-20261001120000-000001`.
- If `client_request_id` **ends with `_FAIL`** (for example `REQ-1002_FAIL`), the insurer rejects it and the request is marked `failed`. Use this to test the failure path.

## Assumptions

- No authentication is implemented; `agent_id` is passed in the request body.
- Currency is USD.
- The insurer is simulated and responds synchronously.

## AI Assistance Disclosure

I come from an **AI/ML background** and have no prior experience with PHP or Laravel. To complete this assignment, I used the following AI coding assistants:

- **ChatGPT**
- **Claude**
- **Antigravity**

They helped me with:

- Understanding Laravel concepts (routing, controllers, form requests, Eloquent models, migrations, service classes)
- Generating and structuring the code in this repository
- Writing the tests and this README
- Fixing Git and setup errors

I used these tools openly as learning and coding aids. I went through the project to understand how the flow works: request validation, premium calculation, mock insurer submission, duplicate handling, and the status lifecycle.

## Author

Sonu Kumar
