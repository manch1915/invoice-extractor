"""A per-day token allowance for the demo, so a public page cannot run up the LLM bill.

Tokens are reserved before a call (prompt estimate plus the answer cap) and settled with the real
usage afterwards. Several uvicorn processes share one file, guarded by an OS file lock.
"""

import json
import os
import threading
from collections.abc import Iterator
from contextlib import contextmanager
from dataclasses import dataclass
from datetime import UTC, datetime
from pathlib import Path

try:
    import fcntl
except ImportError:  # Windows dev machines: the thread lock below is enough for a single process
    fcntl = None  # type: ignore[assignment]

KEEP_DAYS = 7


class BudgetExceededError(RuntimeError):
    pass


@dataclass(frozen=True)
class Reservation:
    day: str
    tokens: int


class TokenBudget:
    def __init__(self, path: str, daily_limit: int) -> None:
        self.path = Path(path)
        self.daily_limit = daily_limit
        self._lock = threading.Lock()

    def _today(self) -> str:
        return datetime.now(UTC).date().isoformat()

    @contextmanager
    def _locked(self) -> Iterator[dict[str, int]]:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        with self._lock:
            fd = os.open(self.path.with_suffix(".lock"), os.O_CREAT | os.O_RDWR, 0o600)
            try:
                if fcntl is not None:
                    fcntl.flock(fd, fcntl.LOCK_EX)
                ledger = self._read()
                yield ledger
                self._write(ledger)
            finally:
                if fcntl is not None:
                    fcntl.flock(fd, fcntl.LOCK_UN)
                os.close(fd)

    def _read(self) -> dict[str, int]:
        if not self.path.exists():
            return {}
        try:
            data = json.loads(self.path.read_text(encoding="utf-8"))
            if not isinstance(data, dict):
                raise ValueError("ledger is not an object")
            return {str(day): int(used) for day, used in data.items()}
        except (OSError, ValueError, TypeError):
            # a damaged ledger must not turn into an unlimited budget
            return {self._today(): max(self.daily_limit, 1)}

    def _write(self, ledger: dict[str, int]) -> None:
        kept = dict(sorted(ledger.items())[-KEEP_DAYS:])
        tmp = self.path.with_suffix(".tmp")
        tmp.write_text(json.dumps(kept), encoding="utf-8")
        tmp.replace(self.path)

    def used_today(self) -> int:
        with self._locked() as ledger:
            return ledger.get(self._today(), 0)

    def reserve(self, tokens: int) -> Reservation:
        """Book tokens before calling the model; refuses if they would not fit in today's allowance."""
        tokens = max(tokens, 1)
        with self._locked() as ledger:
            day = self._today()
            used = ledger.get(day, 0)
            if self.daily_limit > 0 and used + tokens > self.daily_limit:
                raise BudgetExceededError("daily LLM token limit reached")
            ledger[day] = used + tokens
        return Reservation(day, tokens)

    def settle(self, reservation: Reservation, actual: int | None) -> None:
        """Replace the reservation with the real usage. Unknown usage keeps the reservation."""
        if actual is None or actual < 0:
            return
        with self._locked() as ledger:
            if reservation.day in ledger:
                ledger[reservation.day] = max(ledger[reservation.day] - reservation.tokens + actual, 0)
            elif actual > reservation.tokens:
                # the day rolled over meanwhile: only charge what went past the booking
                today = self._today()
                ledger[today] = ledger.get(today, 0) + actual - reservation.tokens

    def ensure_available(self) -> None:
        if self.daily_limit > 0 and self.used_today() >= self.daily_limit:
            raise BudgetExceededError("daily LLM token limit reached")
