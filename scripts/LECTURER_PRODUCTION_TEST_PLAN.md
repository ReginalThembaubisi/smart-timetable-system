# Lecturer Production-Style Test Plan

This plan is designed for your main concern: different lecturers, different modules, and shared modules/cohorts exactly like production behavior.

## 1) Seed Production-Like Data

Run the SQL seed file:

- `scripts/seed_lecturer_production_test_data.sql`

QA lecturer logins created by the seed:

- `qa.lecturer.alpha` / `qaPass!123`
- `qa.lecturer.beta` / `qaPass!123`
- `qa.lecturer.gamma` / `qaPass!123`
- `qa.lecturer.delta` / `qaPass!123`

## 2) Test Matrix (Production-Equivalent)

### A. Different lecturers, different modules

1. Login as `qa.lecturer.alpha`.
2. Confirm timetable includes only Alpha sessions.
3. Publish a test on `QA-CS201`.
4. Logout.
5. Login as `qa.lecturer.gamma`.
6. Confirm Gamma timetable does not include Alpha-only modules unless genuinely shared by sessions.

Expected:

- Each lecturer sees only their own timetable sessions from `sessions.lecturer_id`.
- Publish dropdown includes modules from that lecturer's sessions only.

### B. Same module taught by different lecturers

Seed includes shared teaching for `QA-CS202` by Alpha and Beta.

1. Login as Alpha and publish on `QA-CS202`.
2. Login as Beta and open shared calendar for `QA-CS202`.

Expected:

- Beta sees Alpha's published item in shared calendar.
- Shared calendar item includes module and lecturer name.

### C. Shared cohort conflict detection

Conflict scoring uses shared cohort modules from `student_modules` overlap.

1. Login as Alpha.
2. Publish on `QA-CS201` around `CURDATE()+12` to `+14` days.
3. Observe response risk and message.

Expected:

- Because seed places nearby exams/assessments on related modules, risk should increase.
- High risk blocks publish unless `force_publish=true` is used (API level behavior).

### D. Isolation for low-sharing path

`QA260006` is enrolled only in `QA-PHY210` (control path).

1. Login as Gamma.
2. Check shared calendar for `QA-PHY210`.

Expected:

- Lower shared-item density compared to heavily shared modules like `QA-CS201`/`QA-CS202`.

### E. Notifications fan-out correctness

1. Publish one test for a module with multiple students, e.g. `QA-CS202`.
2. Verify queued notifications:

```sql
SELECT COUNT(*) AS total
FROM student_assessment_notifications san
JOIN lecturer_assessments la ON la.assessment_id = san.assessment_id
WHERE la.title LIKE '[QA] %';
```

Expected:

- Immediate notifications + future reminders are created for enrolled students only.

## 3) API Checks You Can Run Quickly

### Invalid login check

```bash
curl -s -X POST "https://web-production-f8792.up.railway.app/api/lecturer_login_api.php" \
  -H "Content-Type: application/json" \
  -d '{"login":"bad.user","password":"bad.pass"}'
```

Expected:

- `{"success":false,"message":"Invalid login or password"}`

### Timetable check by lecturer id

```bash
curl -s "https://web-production-f8792.up.railway.app/api/get_lecturer_timetable.php?lecturer_id=<ID>"
```

Expected:

- Returns only sessions for that lecturer.

## 4) Sign-Off Criteria for Production Readiness

- At least 3 lecturers can login independently with no session leakage.
- Shared modules show cross-lecturer assessments in the shared calendar.
- Conflict scoring changes as nearby items are added/removed.
- Notification queue volume matches enrolled students for published assessments.
- No lecturer can publish for a module not linked to their timetable sessions.

## 5) Fast Rollback of QA Data

Re-run the same seed file; it clears old QA-tagged data and recreates a clean dataset.
