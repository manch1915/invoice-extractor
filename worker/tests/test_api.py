import json

import pytest
from fastapi.testclient import TestClient

from invoice_worker.config import Settings, get_settings
from invoice_worker.main import app
from tests.conftest import demo_file


@pytest.fixture
def client() -> TestClient:
    app.dependency_overrides[get_settings] = lambda: Settings(token="secret", llm_api_key="")
    yield TestClient(app)
    app.dependency_overrides.clear()


def test_requires_token(client: TestClient) -> None:
    response = client.post("/v1/extract", files={"file": ("a.pdf", b"%PDF-1.4", "application/pdf")})
    assert response.status_code == 401


def test_extracts_a_pdf(client: TestClient) -> None:
    response = client.post(
        "/v1/extract",
        headers={"Authorization": "Bearer secret"},
        files={"file": ("canvasly-07.pdf", demo_file("canvasly-07.pdf"), "application/pdf")},
        data={"categories": json.dumps([{"code": "software", "name": "Software"}])},
    )
    assert response.status_code == 200
    body = response.json()
    assert body["document"]["source"] == "text_layer"
    assert body["document"]["engine"] == "rules"
    assert body["fields"]["total"]["value"] == "45.00"
    assert body["pages"][0]["image"]
    assert len(body["pages"][0]["words"]) > 20


def test_rejects_garbage(client: TestClient) -> None:
    response = client.post(
        "/v1/extract",
        headers={"Authorization": "Bearer secret"},
        files={"file": ("notes.txt", b"just some text", "text/plain")},
    )
    assert response.status_code == 422


def test_rejects_bad_categories(client: TestClient) -> None:
    response = client.post(
        "/v1/extract",
        headers={"Authorization": "Bearer secret"},
        files={"file": ("a.pdf", demo_file("canvasly-07.pdf"), "application/pdf")},
        data={"categories": "{not json"},
    )
    assert response.status_code == 422
