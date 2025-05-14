"""A per-day token allowance for the demo, so a public page cannot run up the LLM bill."""

import json
import threading
from datetime import UTC, datetime
from pathlib import Path


class BudgetExceededError(RuntimeError):
    pass


class TokenBudget:
    def __init__(self, path: str, daily_limit: int) -> None:
        self.path = Path(path)
        self.daily_limit = daily_limit
        self._lock = threading.Lock()

    def _today(self) -> str:
        return datetime.now(UTC).date().isoformat()

    def _read(self) -> dict[str, int]:
        try:
            data = json.loads(self.path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            return {}
        return {str(k): int(v) for k, v in data.items()} if isinstance(data, dict) else {}

    def used_today(self) -> int:
        with self._lock:
            return self._read().get(self._today(), 0)

    def ensure_available(self) -> None:
        if self.daily_limit > 0 and self.used_today() >= self.daily_limit:
            raise BudgetExceededError("daily LLM token limit reached")

    def spend(self, tokens: int) -> None:
        with self._lock:
            today = self._today()
            data = {today: self._read().get(today, 0) + max(tokens, 0)}
            self.path.parent.mkdir(parents=True, exist_ok=True)
            self.path.write_text(json.dumps(data), encoding="utf-8")
