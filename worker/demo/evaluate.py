"""Field-level accuracy of the extraction pipeline on the demo set.

uv run python demo/evaluate.py ../database/demo [--only-text] [--engine rules|llm]
"""

import argparse
import json
import sys
from collections import defaultdict
from decimal import Decimal
from pathlib import Path

from invoice_worker.config import get_settings
from invoice_worker.engines.base import EngineContext
from invoice_worker.main import build_pipeline

FIELDS = ["document_number", "issue_date", "due_date", "vendor_name", "vendor_tax_id", "currency",
          "subtotal", "tax_amount", "total"]  # fmt: skip


def same(key: str, got: str | None, want: object) -> bool:
    if want is None:
        return got is None
    if got is None:
        return False
    if key in ("subtotal", "tax_amount", "total"):
        return Decimal(got) == Decimal(str(want))
    if key == "vendor_name":
        norm = lambda s: s.lower().replace("«", "").replace("»", "").replace('"', "").strip()  # noqa: E731
        return norm(got) == norm(str(want))
    return got.replace(" ", "") == str(want).replace(" ", "")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("demo_dir", type=Path)
    parser.add_argument("--only-text", action="store_true", help="skip scans and photos (no tesseract needed)")
    parser.add_argument("--engine", default="rules")
    parser.add_argument("--verbose", "-v", action="store_true")
    args = parser.parse_args()

    manifest = json.loads((args.demo_dir / "manifest.json").read_text(encoding="utf-8"))
    pipeline = build_pipeline(get_settings(), args.engine)
    hits: dict[str, list[bool]] = defaultdict(list)
    by_quality: dict[str, list[bool]] = defaultdict(list)

    for entry in manifest:
        if args.only_text and entry["quality"] != "clean":
            continue
        data = (args.demo_dir / "documents" / entry["file"]).read_bytes()
        result = pipeline.run(data, entry["file"], EngineContext())
        exp = entry["expected"]
        misses = []
        for key in FIELDS:
            got = result.fields[key].value if key in result.fields else None  # type: ignore[index]
            ok = same(key, got, exp[key])
            hits[key].append(ok)
            by_quality[entry["quality"]].append(ok)
            if not ok:
                misses.append(f"{key}: got {got!r}, want {exp[key]!r}")
        items_ok = len(result.line_items) == exp["line_items"]
        hits["line_items"].append(items_ok)
        if not items_ok:
            misses.append(f"line_items: got {len(result.line_items)}, want {exp['line_items']}")
        type_ok = result.document.doc_type == exp["doc_type"]
        hits["doc_type"].append(type_ok)
        if not type_ok:
            misses.append(f"doc_type: got {result.document.doc_type}, want {exp['doc_type']}")
        status = "ok " if not misses else "MISS"
        print(f"{status} {entry['file']:<32} {result.document.duration_ms:>5} ms")
        if args.verbose:
            for m in misses:
                print(f"       {m}")

    print()
    total = [x for v in hits.values() for x in v]
    for key, values in hits.items():
        print(f"{key:<16} {sum(values):>3}/{len(values):<3} {100 * sum(values) / len(values):5.1f}%")
    for quality, values in by_quality.items():
        print(f"quality {quality:<8} {100 * sum(values) / len(values):5.1f}%")
    print(f"overall          {100 * sum(total) / len(total):5.1f}%")
    return 0


if __name__ == "__main__":
    sys.exit(main())
