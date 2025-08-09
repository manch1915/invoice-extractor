FROM python:3.12-slim-bookworm AS base

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    UV_COMPILE_BYTECODE=1 \
    UV_LINK_MODE=copy \
    OMP_THREAD_LIMIT=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends tesseract-ocr tesseract-ocr-rus tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ghcr.io/astral-sh/uv:0.9.6 /uv /usr/local/bin/uv

WORKDIR /app
COPY pyproject.toml uv.lock ./
RUN uv sync --frozen --no-dev --no-install-project

COPY src ./src
RUN uv sync --frozen --no-dev

RUN useradd --create-home --uid 10001 worker && mkdir -p /var/lib/worker && chown worker /var/lib/worker
USER worker

EXPOSE 8001
HEALTHCHECK --interval=10s --timeout=5s --start-period=10s CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8001/health')"
# one process: OCR runs in Tesseract subprocesses from the thread pool, so requests still overlap
CMD ["/app/.venv/bin/uvicorn", "invoice_worker.main:app", "--host", "0.0.0.0", "--port", "8001"]

FROM base AS dev
USER root
RUN uv sync --frozen
COPY tests ./tests
COPY demo ./demo
USER worker
