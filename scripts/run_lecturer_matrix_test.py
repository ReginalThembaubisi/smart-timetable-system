import json
import argparse
import os
import ssl
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import datetime

SSL_CONTEXT = ssl._create_unverified_context()


def get_json(url: str):
    req = urllib.request.Request(url, method="GET")
    with urllib.request.urlopen(req, timeout=30, context=SSL_CONTEXT) as resp:
        data = resp.read().decode("utf-8", errors="replace")
        return json.loads(data)


def post_json(url: str, payload: dict):
    body = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json", "Accept": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=30, context=SSL_CONTEXT) as resp:
            data = resp.read().decode("utf-8", errors="replace")
            return resp.status, json.loads(data)
    except urllib.error.HTTPError as e:
        data = e.read().decode("utf-8", errors="replace")
        try:
            return e.code, json.loads(data)
        except Exception:
            return e.code, {"success": False, "message": data}


def main():
    parser = argparse.ArgumentParser(description="Run lecturer API matrix checks.")
    parser.add_argument(
        "--base-url",
        default=os.environ.get("LECTURER_QA_BASE_URL", "http://127.0.0.1:8090"),
        help="API base URL (default: env LECTURER_QA_BASE_URL or http://127.0.0.1:8090)",
    )
    args = parser.parse_args()
    base_url = args.base_url.rstrip("/")

    report = {
        "timestamp_utc": datetime.utcnow().isoformat() + "Z",
        "base_url": base_url,
        "lecturers_found": [],
        "module_to_lecturers": {},
        "shared_module_ids_by_module": {},
        "checks": {},
        "summary": {},
    }

    lecturers = []
    module_to_lecturers = defaultdict(set)
    lecturer_modules = defaultdict(set)

    # Discover active lecturers via timetable endpoint
    for lecturer_id in range(1, 41):
        url = f"{base_url}/api/get_lecturer_timetable.php?lecturer_id={lecturer_id}"
        try:
            payload = get_json(url)
        except Exception:
            continue
        if payload.get("success") is True:
            data = payload.get("data") or {}
            lecturer = data.get("lecturer") or {}
            sessions = data.get("sessions") or []
            modules = set()
            for s in sessions:
                mid = int(s.get("module_id") or 0)
                if mid > 0:
                    modules.add(mid)
                    module_to_lecturers[mid].add(int(lecturer.get("lecturer_id") or lecturer_id))
            lecturers.append(
                {
                    "lecturer_id": int(lecturer.get("lecturer_id") or lecturer_id),
                    "lecturer_name": lecturer.get("lecturer_name"),
                    "session_count": len(sessions),
                    "module_ids": sorted(modules),
                }
            )
            lecturer_modules[int(lecturer.get("lecturer_id") or lecturer_id)] = modules

    report["lecturers_found"] = sorted(lecturers, key=lambda x: x["lecturer_id"])
    report["module_to_lecturers"] = {
        str(k): sorted(list(v)) for k, v in sorted(module_to_lecturers.items(), key=lambda kv: kv[0])
    }

    # Shared calendar module graph checks
    shared_by_module = {}
    for module_id in sorted(module_to_lecturers.keys()):
        url = f"{base_url}/api/get_shared_assessment_calendar.php?{urllib.parse.urlencode({'module_id': module_id, 'days': 45})}"
        try:
            payload = get_json(url)
            if payload.get("success") is True:
                data = payload.get("data") or {}
                shared_by_module[str(module_id)] = {
                    "shared_module_ids": data.get("shared_module_ids", []),
                    "items_count": len(data.get("items", [])),
                }
        except Exception:
            continue
    report["shared_module_ids_by_module"] = shared_by_module

    # Non-destructive auth checks
    _, bad_login = post_json(
        f"{base_url}/api/lecturer_login_api.php",
        {"login": "invalid_user", "password": "invalid_pass"},
    )
    report["checks"]["invalid_login"] = bad_login

    # Module ownership enforcement checks (write endpoint, but expected to fail before insert)
    unowned_checks = []
    module_ids = sorted(module_to_lecturers.keys())
    for lec in report["lecturers_found"][:5]:
        lid = lec["lecturer_id"]
        owned = set(lec["module_ids"])
        unowned = next((m for m in module_ids if m not in owned), None)
        if unowned is None:
            continue
        status, payload = post_json(
            f"{base_url}/api/create_lecturer_assessment.php",
            {
                "lecturer_id": lid,
                "module_id": unowned,
                "title": "[QA-DRYRUN] Ownership check",
                "assessment_date": "2030-12-31",
                "assessment_time": "09:00:00",
                "duration": 60,
            },
        )
        unowned_checks.append(
            {
                "lecturer_id": lid,
                "unowned_module_id": unowned,
                "http_status": status,
                "response": payload,
            }
        )
    report["checks"]["unowned_module_publish_attempts"] = unowned_checks

    # Production-style criteria summary (based on API evidence)
    shared_modules = [m for m, lecs in module_to_lecturers.items() if len(lecs) > 1]
    report["summary"] = {
        "lecturer_count_with_timetable": len(report["lecturers_found"]),
        "modules_with_multi_lecturer_teaching": len(shared_modules),
        "sample_shared_modules": shared_modules[:10],
        "invalid_login_rejected": bad_login.get("success") is False,
        "unowned_publish_blocked_all": all(
            (c.get("response") or {}).get("success") is False for c in unowned_checks
        )
        if unowned_checks
        else None,
    }

    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
