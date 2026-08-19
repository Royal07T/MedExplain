"""Deterministic parsing of laboratory report text into structured LabTest rows."""

import re

from app.schemas.extraction import DocumentType, LabResultStatus, LabTest

_NUMBER = r"\d+(?:[.,]\d+)?"
_NUMBER_RE = re.compile(_NUMBER)
_RANGE_RE = re.compile(rf"^{_NUMBER}\s*(?:-|–|—|to|~)\s*{_NUMBER}$")
_SINGLE_RE = re.compile(rf"^([<>]=?)\s*({_NUMBER})$")

_STATUS_WORDS = {
    "positive": LabResultStatus.POSITIVE,
    "pos": LabResultStatus.POSITIVE,
    "reactive": LabResultStatus.POSITIVE,
    "detected": LabResultStatus.POSITIVE,
    "negative": LabResultStatus.NEGATIVE,
    "neg": LabResultStatus.NEGATIVE,
    "non-reactive": LabResultStatus.NEGATIVE,
    "normal": LabResultStatus.WITHIN_RANGE,
    "within": LabResultStatus.WITHIN_RANGE,
    "high": LabResultStatus.ABOVE_RANGE,
    "elevated": LabResultStatus.ABOVE_RANGE,
    "above": LabResultStatus.ABOVE_RANGE,
    "low": LabResultStatus.BELOW_RANGE,
    "decreased": LabResultStatus.BELOW_RANGE,
    "below": LabResultStatus.BELOW_RANGE,
}

_SKIP_WORDS = {
    "patient", "date", "name", "test", "result", "unit", "reference", "range",
    "status", "flag", "lab", "specimen", "collected", "received", "reported",
    "clinical", "history", "diagnosis", "doctor", "physician", "hospital",
    "clinic", "address", "phone", "email", "page", "report", "method",
    "instrument", "sample", "bill", "normal", "high", "low",
}

_RADIOLOGY_HINTS = (
    "radiology", "x-ray", "xray", "ct scan", "mri", "ultrasound",
    "echocardiogram", "mammogram", "chest x",
)
_LAB_HINTS = (
    "glucose", "cholesterol", "lipid", "hemoglobin", "hematocrit", "creatinine",
    "sodium", "potassium", "thyroid", "tsh", "bilirubin", "alkaline",
    "triglyceride", "hdl", "ldl", "platelet", "leucocyte", "lymphocyte",
    "neutrophil", "panel", "vitamin", "ferritin", "a1c", "uric acid", "alt",
    "ast", "count",
)


def parse_lab_report(text: str, limit: int = 100) -> list[LabTest]:
    tests: list[LabTest] = []
    for line in text.splitlines():
        test = _parse_line(line)
        if test is None or any(t.name == test.name for t in tests):
            continue
        tests.append(test)
        if len(tests) >= limit:
            break
    return tests


def detect_document_type(text: str, filename: str | None = None) -> DocumentType:
    hay = f"{filename or ''} {text}".lower()
    if any(h in hay for h in _RADIOLOGY_HINTS):
        return DocumentType.RADIOLOGY_REPORT
    if any(h in hay for h in _LAB_HINTS):
        return DocumentType.LAB_REPORT
    return DocumentType.UNKNOWN


def compare_value_to_range(value: str, ref: str | None) -> LabResultStatus:
    """Public wrapper around :func:`_compare` for store-driven status checks."""
    return _compare(value, ref)


def _clean(line: str) -> str:
    return re.sub(r"[\t|•·*]", " ", line).strip()


def _parse_line(line: str) -> LabTest | None:
    s = _clean(line)
    if not s or len(s) < 3:
        return None
    tokens = s.split()
    first = tokens[0].strip(":;,.()").lower()
    if first in _SKIP_WORDS:
        return None

    status = _status_from_tokens(tokens)
    ref = _find_reference(tokens)

    value_tok: str | None = None
    value_idx = -1
    ref_tokens = _reference_tokens(ref)
    for i, tok in enumerate(tokens):
        norm = tok if re.fullmatch(_NUMBER, tok) else None
        if norm is None:
            continue
        if ref_tokens and norm in ref_tokens:
            continue
        value_tok = norm
        value_idx = i
        break

    if value_tok is None:
        return _parse_qualitative(s, status)

    name = " ".join(tokens[:value_idx]).strip().rstrip(":;,-").strip()
    if not name or len(name) > 80 or name.lower() in _SKIP_WORDS:
        return None

    unit = _find_unit(tokens, value_idx, ref_tokens)
    if status == LabResultStatus.UNKNOWN:
        status = _compare(value_tok, ref)

    return LabTest(
        name=name,
        value=value_tok,
        unit=unit,
        reference_range=ref,
        status=status,
    )


def _status_from_tokens(tokens: list[str]) -> LabResultStatus:
    lowered = [t.strip(":;()").lower() for t in tokens]
    joined = " ".join(lowered)
    if "not detected" in joined:
        return LabResultStatus.NEGATIVE
    for word, status in _STATUS_WORDS.items():
        if word in lowered or word in joined:
            return status
    for t in tokens:
        if t in ("+", "↑"):
            return LabResultStatus.POSITIVE
        if t in ("-", "↓"):
            return LabResultStatus.NEGATIVE
    return LabResultStatus.UNKNOWN


def _find_reference(tokens: list[str]) -> str | None:
    for tok in tokens:
        if _RANGE_RE.match(tok.replace(",", ".")):
            return tok
    for i, tok in enumerate(tokens):
        if tok in ("<", ">", "<=", ">=") and i + 1 < len(tokens) and _NUMBER_RE.fullmatch(tokens[i + 1]):
            return f"{tok} {tokens[i + 1]}"
    return None


def _reference_tokens(ref: str | None) -> set[str]:
    if not ref:
        return set()
    return {
        part
        for part in re.split(r"[<>=\s\-–—~to]+", ref)
        if _NUMBER_RE.fullmatch(part)
    }


def _find_unit(tokens: list[str], value_idx: int, ref_tokens: set[str]) -> str | None:
    for tok in tokens[value_idx + 1 :]:
        if re.fullmatch(_NUMBER, tok):
            break
        if tok in ref_tokens:
            continue
        if re.fullmatch(r"[A-Za-zµ%/^]+", tok) and tok.strip(":;").lower() not in _SKIP_WORDS:
            return tok
    return None


def _compare(value: str, ref: str | None) -> LabResultStatus:
    if not ref:
        return LabResultStatus.UNKNOWN
    try:
        val = float(value.replace(",", "."))
    except ValueError:
        return LabResultStatus.UNKNOWN
    normalized = ref.replace(",", ".")
    m = _RANGE_RE.match(normalized)
    if m:
        lo, hi = (float(x) for x in re.split(r"\s*(?:-|–|—|to|~)\s*", m.group(0)))
        if val < lo:
            return LabResultStatus.BELOW_RANGE
        if val > hi:
            return LabResultStatus.ABOVE_RANGE
        return LabResultStatus.WITHIN_RANGE
    m = _SINGLE_RE.match(normalized)
    if m:
        relop, bound = m.group(1), float(m.group(2))
        if relop == ">":
            return LabResultStatus.ABOVE_RANGE if val > bound else LabResultStatus.WITHIN_RANGE
        if relop == ">=":
            return LabResultStatus.ABOVE_RANGE if val >= bound else LabResultStatus.WITHIN_RANGE
        if relop == "<":
            return LabResultStatus.BELOW_RANGE if val < bound else LabResultStatus.WITHIN_RANGE
        if relop == "<=":
            return LabResultStatus.BELOW_RANGE if val <= bound else LabResultStatus.WITHIN_RANGE
    return LabResultStatus.UNKNOWN


def _parse_qualitative(s: str, status: LabResultStatus) -> LabTest | None:
    if status not in (LabResultStatus.POSITIVE, LabResultStatus.NEGATIVE):
        return None
    lowered = s.lower()
    for word in ("not detected", "non-reactive", "negative", "positive", "neg", "pos", "detected", "reactive"):
        idx = lowered.find(word)
        if idx != -1:
            name = s[:idx].strip().rstrip(":;,.()").strip()
            if name and len(name) <= 80 and name.lower() not in _SKIP_WORDS:
                value = "Positive" if status == LabResultStatus.POSITIVE else "Negative"
                return LabTest(name=name, value=value, status=status)
            return None
    return None