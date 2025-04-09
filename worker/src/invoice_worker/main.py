import json
import logging
import secrets
from typing import Annotated

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile, status
from fastapi.concurrency import run_in_threadpool

from invoice_worker import ocr
from invoice_worker.config import Settings, get_settings
from invoice_worker.documents import UnsupportedDocumentError
from invoice_worker.engines.base import Category, Engine, EngineContext
from invoice_worker.engines.rules import RulesEngine
from invoice_worker.pipeline import Pipeline
from invoice_worker.schemas import ExtractionResult

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")

app = FastAPI(title="Invoice Extractor worker", version="1.0.0")


def require_token(
    settings: Annotated[Settings, Depends(get_settings)],
    authorization: Annotated[str | None, Header()] = None,
) -> None:
    if not settings.token:
        return
    expected = f"Bearer {settings.token}"
    if authorization is None or not secrets.compare_digest(authorization, expected):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "invalid worker token")


def build_pipeline(settings: Settings, engine: str) -> Pipeline:
    rules = RulesEngine()
    primary: Engine = rules
    if engine != "rules" and settings.llm_enabled:
        from invoice_worker.engines.llm import LlmEngine

        primary = LlmEngine.from_settings(settings)
    return Pipeline(primary, rules, settings.ocr_languages, settings.max_pages)


@app.get("/health")
def health(settings: Annotated[Settings, Depends(get_settings)]) -> dict[str, object]:
    return {"status": "ok", "ocr": ocr.available(), "llm": settings.llm_enabled}


@app.post("/v1/extract", dependencies=[Depends(require_token)])
async def extract(
    settings: Annotated[Settings, Depends(get_settings)],
    file: Annotated[UploadFile, File()],
    categories: Annotated[str, Form()] = "[]",
    engine: Annotated[str, Form(pattern="^(auto|rules|llm)$")] = "auto",
) -> ExtractionResult:
    data = await file.read(settings.max_upload_mb * 1024 * 1024 + 1)
    if len(data) > settings.max_upload_mb * 1024 * 1024:
        raise HTTPException(status.HTTP_413_CONTENT_TOO_LARGE, "file is too large")
    if not data:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "empty file")

    try:
        parsed = json.loads(categories)
        context = EngineContext(categories=[Category(code=str(c["code"]), name=str(c["name"])) for c in parsed])
    except (ValueError, KeyError, TypeError) as exc:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, "categories must be a JSON list") from exc

    pipeline = build_pipeline(settings, engine)
    try:
        return await run_in_threadpool(pipeline.run, data, file.filename or "upload", context)
    except UnsupportedDocumentError as exc:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_CONTENT, str(exc)) from exc
