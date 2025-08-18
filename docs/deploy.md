# Deploying behind an existing reverse proxy

The compose file runs the whole stack on one host. On a server that already has Caddy (or nginx) in front of other sites, keep that proxy and let it reach the app container over a shared Docker network instead of publishing a port.

## 1. Prepare the folder

```bash
sudo mkdir -p /opt/invoice-extractor && cd /opt/invoice-extractor
git clone https://github.com/manch1915/invoice-extractor.git .
cp .env.example .env
```

In `.env` set at least:

```dotenv
APP_ENV=production
APP_KEY=base64:...              # openssl rand -base64 32
APP_URL=https://invoices.example.com
DB_PASSWORD=...                 # openssl rand -hex 24
WORKER_TOKEN=...                # openssl rand -hex 24
DEMO_MODE=true                  # public demo: one-click sign-in, samples, nightly reset
```

Optional: `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_MODEL` and `LLM_DAILY_TOKEN_LIMIT` for the language model engine; the OAuth credentials for Google and Xero.

The app trusts `X-Forwarded-For` and `X-Forwarded-Proto` only from private Docker ranges by default. If the proxy reaches the app from somewhere else, list its address in `TRUSTED_PROXIES`. Horizon's dashboard stays closed while `DEMO_MODE` is on, because every visitor can sign in as the owner.

## 2. Join the proxy's network

Create `compose.override.yaml` next to `compose.yaml` (it is picked up automatically and stays on the server):

```yaml
services:
  app:
    ports: !reset []
    container_name: invoice-extractor-app
    networks: [default, proxy]

networks:
  proxy:
    external: true
    name: proxy_network_name   # the network your Caddy container is attached to
```

## 3. Start

```bash
docker compose up -d --build
docker compose logs -f app       # migrations, then the sample documents load in the background
```

## 4. Add the site to the proxy

Caddy:

```caddy
invoices.example.com {
	encode zstd gzip
	request_body {
		max_size 210MB
	}
	reverse_proxy invoice-extractor-app:8080
}
```

Validate before reloading so a typo cannot take the other sites down:

```bash
cp Caddyfile Caddyfile.bak
caddy validate --config Caddyfile && caddy reload --config Caddyfile
```

## Updating

```bash
git pull
docker compose up -d --build app horizon scheduler worker
```

Migrations run when the app container starts. Horizon restarts with the new code because the container is recreated.

## Resources

The worker runs Tesseract on one core per document and is capped at 2 CPUs and 1.5 GB in `compose.yaml`; the app, Horizon, PostgreSQL and Redis need about 1 GB together. `HORIZON_DOCUMENT_PROCESSES` sets how many documents are read in parallel.
