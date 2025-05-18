"""A per-day token allowance for the demo, so a public page cannot run up the LLM bill.

Tokens are reserved before a call (prompt estimate plus the answer cap) and settled with the real
usage afterwards. Several uvicorn processes share one file, guarded by an OS file lock.
"""

import json
import os
import threading
from collections.abc import Iterator
from contextlib import contextmanager
from datetime import UTC, datetime
from pathlib import Path

try:
    import fcntl
except ImportError:  # Windows dev machines: the thread lock below is enough for a single process
    fcntl = None  # type: ignore[assignment]


class BudgetExceededError(RuntimeError):
    pass


class TokenBudget:
    def __init__(self, path: str, daily_limit: int) -> None:
        self.path = Path(path)
        self.daily_limit = daily_limit
        self._lock = threading.Lock()

    def _today(self) -> str:
        return datetime.now(UTC).date().isoformat()

    @contextmanager
    def _locked(self) -> Iterator[None]:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        with self._lock:
            fd = os.open(self.path.with_suffix(".lock"), os.O_CREAT | os.O_RDWR, 0o600)
            try:
                if fcntl is not None:
                    fcntl.flock(fd, fcntl.LOCK_EX)
                yield
            finally:
                if fcntl is not None:
                    fcntl.flock(fd, fcntl.LOCK_UN)
                os.close(fd)

    def _used(self) -> int:
        if not self.path.exists():
            return 0
        try:
            data = json.loads(self.path.read_text(encoding="utf-8"))
            return int(data.get(self._today(), 0)) if isinstance(data, dict) else self._exhausted()
        except (OSError, ValueError, TypeError):
            # a damaged ledger must not turn into an unlimited budget
            return self._exhausted()

    def _exhausted(self) -> int:
        return max(self.daily_limit, 1)

    def _write(self, used: int) -> None:
        tmp = self.path.with_suffix(".tmp")
        tmp.write_text(json.dumps({self._today(): used}), encoding="utf-8")
        tmp.replace(self.path)

    def used_today(self) -> int:
        with self._locked():
            return self._used()

    def reserve(self, tokens: int) -> int:
        """Book tokens before calling the model; refuses if they would not fit in today's allowance."""
        tokens = max(tokens, 1)
        with self._locked():
            used = self._used()
            if self.daily_limit > 0 and used + tokens > self.daily_limit:
                raise BudgetExceededError("daily LLM token limit reached")
            self._write(used + tokens)
        return tokens

    def settle(self, reserved: int, actual: int | None) -> None:
        """Replace the reservation with the real usage. Unknown usage keeps the reservation."""
        if actual is None or actual < 0:
            return
        with self._locked():
            self._write(max(self._used() - reserved + actual, 0))

    def ensure_available(self) -> None:
        if self.daily_limit > 0 and self.used_today() >= self.daily_limit:
            raise BudgetExceededError("daily LLM token limit reached")
