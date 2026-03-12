#!/usr/bin/env python3
import argparse
import json
import os
import re
import subprocess
import tempfile
import zipfile
from datetime import datetime
from typing import Dict, List, Optional, Tuple

try:
    import pdfplumber  # type: ignore
except Exception:
    pdfplumber = None

try:
    from pypdf import PdfReader  # type: ignore
except Exception:
    PdfReader = None

MONTHS = {
    "jan": 1,
    "january": 1,
    "feb": 2,
    "february": 2,
    "mar": 3,
    "march": 3,
    "apr": 4,
    "april": 4,
    "may": 5,
    "jun": 6,
    "june": 6,
    "jul": 7,
    "july": 7,
    "aug": 8,
    "august": 8,
    "sep": 9,
    "sept": 9,
    "september": 9,
    "oct": 10,
    "october": 10,
    "nov": 11,
    "november": 11,
    "dec": 12,
    "december": 12,
}


def clean_spaces(text: str) -> str:
    text = text.replace("\u2013", "-").replace("\u2014", "-").replace("\u2212", "-")
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def normalize_ocr_noise(text: str) -> str:
    s = text
    s = s.replace("\u2013", "-").replace("\u2014", "-").replace("\u2212", "-")
    s = s.replace("�", " ")
    s = re.sub(r"[^\x09\x0A\x0D\x20-\x7E]", " ", s)
    # Common OCR confusions inside numeric contexts.
    s = re.sub(r"(?<=\d)[Il](?=\d)", "1", s)
    s = re.sub(r"(?<=\d)O(?=\d)", "0", s)
    s = re.sub(r"(?<=\d)S(?=\d)", "5", s)
    s = re.sub(r"(?<=\d)B(?=\d)", "8", s)
    # Handle examples like "l6-20 March 2026" where first digit was OCR'd as letter.
    s = re.sub(r"\b[Il](?=\d\b)", "1", s)
    s = re.sub(r"\b[Il](?=\d{1,2}\s*-\s*\d{1,2}\b)", "1", s)
    s = re.sub(r"\s+", " ", s)
    return s.strip()


def read_txt(path: str) -> str:
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        return f.read()


def read_docx(path: str) -> str:
    pieces: List[str] = []
    with zipfile.ZipFile(path, "r") as zf:
        for name in (
            "word/document.xml",
            "word/header1.xml",
            "word/header2.xml",
            "word/footer1.xml",
            "word/footer2.xml",
        ):
            if name not in zf.namelist():
                continue
            raw = zf.read(name).decode("utf-8", errors="ignore")
            raw = re.sub(r"</w:p>", "\n", raw)
            raw = re.sub(r"<[^>]+>", " ", raw)
            pieces.append(raw)
    return "\n".join(pieces)


def run_cmd(cmd: List[str]) -> Tuple[int, str]:
    try:
        cp = subprocess.run(cmd, capture_output=True, text=True, check=False)
        return cp.returncode, (cp.stdout or "").strip()
    except Exception:
        return 1, ""


def read_pdf(path: str) -> str:
    texts: List[str] = []

    if pdfplumber is not None:
        try:
            with pdfplumber.open(path) as pdf:
                for page in pdf.pages[:15]:
                    page_text = page.extract_text() or ""
                    if page_text.strip():
                        texts.append(page_text)
                    tables = page.extract_tables() or []
                    for table in tables:
                        for row in table:
                            if not row:
                                continue
                            row_text = " | ".join([(cell or "").strip() for cell in row])
                            row_text = row_text.strip(" |")
                            if row_text:
                                texts.append(row_text)
        except Exception:
            pass

    if not texts and PdfReader is not None:
        try:
            reader = PdfReader(path)
            for page in reader.pages[:15]:
                page_text = page.extract_text() or ""
                if page_text.strip():
                    texts.append(page_text)
        except Exception:
            pass

    if not texts:
        code, out = run_cmd(["pdftotext", "-layout", path, "-"])
        if code == 0 and out:
            texts.append(out)

    full_text = "\n".join(texts).strip()
    if len(full_text) >= 120:
        return full_text
    return ocr_pdf(path) or full_text


def ocr_pdf(path: str, max_pages: int = 10) -> str:
    if not shutil_which("pdftoppm") or not shutil_which("tesseract"):
        return ""
    with tempfile.TemporaryDirectory(prefix="ocr_pdf_") as d:
        prefix = os.path.join(d, "page")
        cmd = ["pdftoppm", "-png", "-gray", "-r", "300", "-f", "1", "-l", str(max_pages), path, prefix]
        code, _ = run_cmd(cmd)
        if code != 0:
            return ""
        images = sorted(
            [os.path.join(d, f) for f in os.listdir(d) if f.startswith("page-") and f.endswith(".png")]
        )
        chunks: List[str] = []
        for image in images:
            best = ""
            best_score = -1.0
            for psm in ("6", "11"):
                code, out = run_cmd(["tesseract", image, "stdout", "-l", "eng", "--oem", "1", "--psm", psm])
                if code == 0 and out:
                    score = text_quality_score(out)
                    if score > best_score:
                        best_score = score
                        best = out
            if best.strip():
                chunks.append(best)
        return "\n\n".join(chunks).strip()


def text_quality_score(text: str) -> float:
    s = text.strip()
    if not s:
        return -1000.0
    n = len(s)
    alpha = len(re.findall(r"[A-Za-z]", s))
    digit = len(re.findall(r"\d", s))
    symbols = len(re.findall(r"[^A-Za-z0-9\s]", s))
    words = len(re.findall(r"[A-Za-z]{3,}", s))
    dates = len(re.findall(r"\b(\d{1,2}[/\-.]\d{1,2}(?:[/\-.]\d{2,4})?|\d{4}-\d{2}-\d{2})\b", s))
    return words * 0.8 + dates * 2.5 + ((alpha + digit) / max(1, n)) * 25.0 - (symbols / max(1, n)) * 40.0


def shutil_which(name: str) -> bool:
    code, out = run_cmd(["which", name])
    return code == 0 and bool(out)


def normalize_date(raw: str) -> Optional[str]:
    value = clean_spaces(raw)
    if not value:
        return None
    value = re.sub(r"\b(\d{1,2})(st|nd|rd|th)\b", r"\1", value, flags=re.I)

    m = re.match(r"^(?:week\s+of\s+)?(\d{1,2})\s*-\s*(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})$", value, flags=re.I)
    if m:
        day = int(m.group(1))
        month = MONTHS.get(m.group(3).lower())
        year = int(m.group(4))
        if month and valid_date(year, month, day):
            return f"{year:04d}-{month:02d}-{day:02d}"

    if re.match(r"^\d{4}-\d{2}-\d{2}$", value):
        return value

    for fmt in ("%d/%m/%Y", "%d-%m-%Y", "%d.%m.%Y", "%d/%m/%y", "%d-%m-%y"):
        try:
            return datetime.strptime(value, fmt).strftime("%Y-%m-%d")
        except ValueError:
            pass

    m = re.match(r"^(\d{1,2})[\/\-.](\d{1,2})$", value)
    if m:
        day = int(m.group(1))
        month = int(m.group(2))
        if valid_date(2026, month, day):
            return f"2026-{month:02d}-{day:02d}"

    m = re.match(r"^(\d{1,2})\s+([A-Za-z]{3,9})(?:\s+(\d{4}))?$", value)
    if m:
        day = int(m.group(1))
        month = MONTHS.get(m.group(2).lower())
        year = int(m.group(3) or "2026")
        if month and valid_date(year, month, day):
            return f"{year:04d}-{month:02d}-{day:02d}"

    return None


def valid_date(y: int, m: int, d: int) -> bool:
    try:
        datetime(y, m, d)
        return True
    except ValueError:
        return False


def event_type(text: str) -> str:
    t = text.lower()
    if "test" in t or "quiz" in t:
        return "Test"
    if "exam" in t:
        return "Exam"
    if "practical" in t or "lab" in t:
        return "Practical"
    return "Assignment"


def has_assessment_keyword(text: str) -> bool:
    return bool(re.search(r"\b(test|exam|assignment|practical|quiz|project|submission|due|assessment|sick test)\b", text, re.I))


def extract_time(text: str) -> Optional[str]:
    m = re.search(r"\b([01]?\d|2[0-3]):([0-5]\d)\b", text)
    if m:
        return f"{int(m.group(1)):02d}:{int(m.group(2)):02d}"
    m = re.search(r"\b(1[0-2]|0?[1-9])(?:[:.]([0-5]\d))?\s*(am|pm)\b", text, re.I)
    if m:
        h = int(m.group(1))
        mi = int(m.group(2) or "0")
        ap = m.group(3).lower()
        if ap == "pm" and h != 12:
            h += 12
        if ap == "am" and h == 12:
            h = 0
        return f"{h:02d}:{mi:02d}"
    return None


def clean_title(title: str, fallback: str) -> str:
    t = clean_spaces(title)
    t = re.sub(r"[^A-Za-z0-9\s&:,\-]", " ", t)
    t = clean_spaces(t).strip("-:|")
    if len(t) < 3:
        return fallback
    if len(t) > 120:
        t = t[:117] + "..."
    return t


def is_generic_heading(title: str) -> bool:
    t = title.strip().lower()
    generic = {
        "assessment",
        "assessment week",
        "week",
        "weeks",
        "date",
        "assessment date",
        "assessment dates",
    }
    return t in generic


def find_dates_in_line(line: str) -> List[str]:
    range_pattern = r"(?:week\s+of\s+)?\d{1,2}\s*[–-]\s*\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)\s+\d{4}"
    patterns = [
        r"\d{4}-\d{2}-\d{2}",
        r"\d{1,2}[/\-.]\d{1,2}[/\-.]\d{2,4}",
        r"\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)(?:,?\s*\d{4})?",
        r"(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec|January|February|March|April|June|July|August|September|October|November|December)\s+\d{1,2}(?:st|nd|rd|th)?(?:,?\s*\d{4})?",
    ]
    out: List[str] = []

    remainder = line
    for m in re.finditer(range_pattern, line, flags=re.I):
        out.append(m.group(0))
        remainder = remainder.replace(m.group(0), " ")

    for p in patterns:
        for m in re.finditer(p, remainder, flags=re.I):
            out.append(m.group(0))
    return out


def parse_events(text: str, module_code: str) -> List[Dict]:
    text = normalize_ocr_noise(text)
    lines = [clean_spaces(x) for x in re.split(r"\r?\n", text)]
    lines = [x for x in lines if x]

    events: List[Dict] = []
    seen = set()

    for i, line in enumerate(lines):
        context = " ".join(lines[max(0, i - 1): min(len(lines), i + 2)])
        if not has_assessment_keyword(context):
            continue
        date_candidates = find_dates_in_line(line) + find_dates_in_line(context)
        if not date_candidates:
            continue
        for raw in list(dict.fromkeys(date_candidates)):
            d = normalize_date(raw)
            if not d:
                continue

            title = infer_title_for_date(line, raw, context)
            if is_generic_heading(title):
                continue
            typ = event_type(title + " " + context)
            tm = extract_time(context)
            key = (d, title.lower())
            if key in seen:
                continue
            seen.add(key)
            events.append(
                {
                    "title": title,
                    "date": d,
                    "type": typ,
                    "moduleCode": module_code,
                    "time": tm,
                    "venue": None,
                    "isReminderSet": False,
                }
            )

    events.sort(key=lambda e: (e["date"], e["title"]))
    return events


def infer_title_for_date(line: str, raw_date: str, context: str) -> str:
    escaped = re.escape(raw_date)
    m = re.search(escaped, line, flags=re.I)
    hay = line if m else context
    m = re.search(escaped, hay, flags=re.I)

    if m:
        left = hay[max(0, m.start() - 80):m.start()]
        left = clean_spaces(left)
        pat = re.compile(
            r"(sick\s*test|semester\s*test\s*[0-9il]*|class\s*test\s*[0-9il]*|test\s*[0-9il]+|assignment\s*\d*|quiz(?:zes)?|project(?:\s*submission)?)",
            re.I,
        )
        candidates = list(pat.finditer(left))
        if candidates:
            title = clean_title(candidates[-1].group(1), event_type(left) + " event")
            title = re.sub(r"\btest\s+l\b", "Test 1", title, flags=re.I)
            return title

    # Fallback: avoid using whole noisy OCR line as title.
    return event_type(hay) + " event"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--file", required=True, help="Path to uploaded file")
    parser.add_argument("--filename", required=True, help="Original filename")
    parser.add_argument("--module", required=True, help="Module code")
    args = parser.parse_args()

    ext = os.path.splitext(args.filename.lower())[1]
    text = ""
    try:
        if ext == ".txt":
            text = read_txt(args.file)
        elif ext == ".docx":
            text = read_docx(args.file)
        else:
            text = read_pdf(args.file)
    except Exception:
        text = ""

    events = parse_events(text, args.module) if text else []
    print(json.dumps({"events": events, "text_len": len(text)}, ensure_ascii=False))


if __name__ == "__main__":
    main()
