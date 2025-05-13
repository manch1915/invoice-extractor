import json
from pathlib import Path
from typing import Any

import pytest

DEMO_DIR = Path(__file__).resolve().parents[2] / "database" / "demo"


@pytest.fixture(scope="session")
def manifest() -> list[dict[str, Any]]:
    data: list[dict[str, Any]] = json.loads((DEMO_DIR / "manifest.json").read_text(encoding="utf-8"))
    return data


def demo_file(name: str) -> bytes:
    return (DEMO_DIR / "documents" / name).read_bytes()
