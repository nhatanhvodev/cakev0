# ai-service

FastAPI service that powers the CakeV0 AI customer-support chat (FAQ / policy
Q&A, order lookup, order creation, human handoff, Messenger channel). Talks to
Gemini for LLM + embeddings, ChromaDB for retrieval, and the PHP app's MySQL
database for orders/sessions/tickets.

## Setup

### Option A — local virtualenv

```bash
cd ai-service
python -m venv venv
venv\Scripts\activate        # Windows (PowerShell: venv\Scripts\Activate.ps1)
# source venv/bin/activate   # macOS/Linux
pip install -r requirements.txt
# create ai-service/.env with the variables you need (see Environment variables below);
# any variable not set falls back to the default in app/config.py
venv\Scripts\python -m uvicorn app.main:app --reload --port 8000
```

The service listens on `http://localhost:8000`. `/health` returns
`{"status": "ok", "engine": "..."}` once it's up.

### Option B — Docker (standalone)

```bash
cd ai-service
docker build -t cakev0-ai-service .
docker run --rm -p 8000:8000 --env-file .env cakev0-ai-service
```

### Option C — full stack via docker compose (PHP + MySQL + ai-service)

From the repo root:

```bash
docker compose up --build
```

This starts three services on the shared `bakery-net` network:

| Service      | Container name    | Port (host)  | Notes                          |
|--------------|--------------------|--------------|---------------------------------|
| `app`        | gau-bakery-app     | 8080 -> 80   | PHP + Apache, base path `/cakev0` |
| `db`         | gau-bakery-db      | 3307 -> 3306 | MySQL 8, seeded from `database/banh_store.sql` |
| `ai-service` | gau-bakery-ai      | 8000 -> 8000 | this FastAPI service            |

Inside the compose network, containers reach each other by **service name**,
not `localhost`:
- The PHP app calls the AI service at `http://ai-service:8000`.
- The AI service calls the PHP internal order API at
  `http://app/cakev0/api/internal/orders/create.php` (see
  `INTERNAL_ORDER_API_URL` in `docker-compose.yml`).
- The AI service connects to MySQL at host `db`, port `3306`.

Set `GEMINI_API_KEY`, `INTERNAL_API_SECRET`, etc. in a `.env` file at the repo
root (docker-compose reads it automatically) or export them in your shell
before running `docker compose up`.

## Environment variables

All settings live in `app/config.py` (`Settings`, loaded from `.env` or the
process environment; unknown keys are ignored). Values below are the defaults.

| Variable | Default | Purpose |
|---|---|---|
| `ENGINE` | `multiagent` | `baseline` or `multiagent` — which engine `/chat/send` uses |
| `GEMINI_API_KEY` | *(empty)* | Google Gemini API key — required for real LLM calls |
| `LLM_MODEL` | `gemini-2.0-flash` | Gemini chat model |
| `EMBEDDING_MODEL` | `text-embedding-004` | Gemini embedding model |
| `LLM_TEMPERATURE` | `0.3` | Sampling temperature |
| `CHROMA_PERSIST_DIR` | `./data/chroma_db` | Local ChromaDB persistence directory |
| `MYSQL_HOST` | `127.0.0.1` | MySQL host (use `db` inside docker compose) |
| `MYSQL_PORT` | `3306` | MySQL port |
| `MYSQL_USER` | `root` | MySQL user |
| `MYSQL_PASSWORD` | *(empty)* | MySQL password |
| `MYSQL_DATABASE` | `banh_store` | MySQL database name |
| `INTERNAL_API_SECRET` | `change-me` | HMAC secret shared with the PHP internal order API — **must match** the PHP side |
| `INTERNAL_ORDER_API_URL` | `http://localhost/cakev0/api/internal/orders/create.php` | PHP endpoint the AI service calls to create orders |
| `HANDOFF_CONFIDENCE_THRESHOLD` | `0.6` | Below this confidence, the handoff policy hands off to a human agent |
| `FB_PAGE_TOKEN` / `FB_VERIFY_TOKEN` / `FB_APP_SECRET` | *(empty)* | Messenger channel credentials — see `docs/messenger-setup.md` |
| `CORS_ORIGINS` | `*` | Comma-separated allowed origins for the chat widget |
| `SITE_BASE_URL` | `https://cake-i8l0.onrender.com/cakev0` | Base URL used to build absolute links (e.g. product cards) |
| `ENABLE_NORMALIZER` | `true` | Enable Vietnamese teencode/slang normalization before routing |

## Indexing knowledge (FAQ, policies, products)

The chat engines retrieve from a Chroma vector store built from
`data/faq_seed.json`, `data/policies/*.md`, and the `products` table.

Via the running API:

```bash
curl -X POST "http://localhost:8000/knowledge/index?source=all"
# source can be: all | faq | policies | products
```

Or one-off from a Python shell (no server needed, useful in CI / scripts):

```bash
cd ai-service
venv\Scripts\python -c "from app.deps import build_deps; from app.knowledge.indexer import reindex; d = build_deps(); print(reindex(d.store, d.conn_factory(), 'all'))"
```

Re-run indexing whenever `data/faq_seed.json`, `data/policies/*.md`, or the
`products` table changes.

## Running tests

```bash
cd ai-service
venv\Scripts\python -m pytest -v
```

Tests that need a live MySQL connection are skipped automatically when the
database isn't reachable (see the `SKIPPED (MySQL...)` entries).

## Running the evaluation harness

`eval/run_eval.py` replays the labeled dataset in `eval/dataset/` through an
engine and writes per-turn results to a JSONL file; `eval/analyze.py` /
`eval.metrics` then compute M1-M6 (grounding, handoff precision/recall,
kappa, etc.).

```bash
cd ai-service
venv\Scripts\python -m eval.run_eval --engine baseline --out eval/results/baseline.jsonl
venv\Scripts\python -m eval.run_eval --engine multiagent --out eval/results/multiagent.jsonl
```

`run_eval.py` is checkpoint-resumable: if `--out` already exists, sample ids
already present are skipped, so an interrupted run can just be re-launched.
It throttles LLM calls with `--sleep` (default `4` seconds, i.e. ~15
req/min) to stay under the Gemini free-tier rate limit (1500 req/day) —
running both engines over a large dataset in one day may require splitting
the run across two days.

## Switching engines

Set `ENGINE=baseline` or `ENGINE=multiagent` (env var / `.env`) and restart
the service. `/health` reports the active engine. `app/deps.get_engine()`
falls back to `BaselineEngine` automatically if the multiagent graph module
fails to import, so `ENGINE=multiagent` is safe even in environments missing
optional multiagent dependencies.

## Rate limiting

`POST /chat/send` is rate-limited to 20 requests/minute per key (the
request's `session_id` if present, else `guest_token`, else client IP —
see `X-Forwarded-For` handling in `app/main.py`). Requests beyond the limit
receive `429 {"error": "rate_limited"}`. The limiter is a simple in-memory
sliding window (module-level dict in `app/main.py`); it resets on process
restart and is per-process (not shared across multiple ai-service replicas).

## Deploying

- **Render**: create a separate **Python Web Service** for `ai-service`
  (root directory `ai-service`, build command
  `pip install -r requirements.txt`, start command
  `uvicorn app.main:app --host 0.0.0.0 --port $PORT`). Set the same
  environment variables listed above, pointing `INTERNAL_ORDER_API_URL` and
  `MYSQL_*` at the deployed PHP app / managed MySQL instance, and share
  `INTERNAL_API_SECRET` with the PHP service's env. This repo's root
  `render.yaml` currently only defines the PHP web service; adding the AI
  service there as a second Render service is a follow-up (out of scope for
  this task) — provision it manually via the Render dashboard in the
  meantime, using the settings above.
- **Docker / self-hosted**: build the image from `ai-service/Dockerfile` and
  run it alongside the PHP app and MySQL, either via the repo-root
  `docker-compose.yml` (`docker compose up --build`) or with your own
  orchestration, making sure the AI service can reach both MySQL and the PHP
  app's internal order API over the network.
