<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lecturer_system.php';

$pdo = Database::getInstance()->getConnection();
ensureLecturerSystemTables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assessment'])) {
    try {
        $lecturerId = isset($_POST['lecturer_id']) ? (int) $_POST['lecturer_id'] : 0;
        $moduleId = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $assessmentDate = (string) ($_POST['assessment_date'] ?? '');
        $assessmentTime = (string) ($_POST['assessment_time'] ?? '');
        $duration = isset($_POST['duration']) ? (int) $_POST['duration'] : 60;
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($lecturerId <= 0 || $moduleId <= 0 || $title === '') {
            throw new Exception('Lecturer, module and title are required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $assessmentDate)) {
            throw new Exception('Assessment date must use YYYY-MM-DD.');
        }
        if (!validateTimeFormat($assessmentTime)) {
            throw new Exception('Assessment time must use HH:MM or HH:MM:SS.');
        }
        if ($duration < 30 || $duration > 240) {
            throw new Exception('Duration must be between 30 and 240 minutes.');
        }

        $ownsModuleStmt = $pdo->prepare("
            SELECT COUNT(*) FROM sessions WHERE lecturer_id = ? AND module_id = ?
        ");
        $ownsModuleStmt->execute([$lecturerId, $moduleId]);
        if ((int) $ownsModuleStmt->fetchColumn() === 0) {
            throw new Exception('This lecturer is not assigned to that module timetable.');
        }

        $sharedModuleIds = getSharedCohortModuleIds($pdo, $moduleId);
        $conflictCount = getAssessmentConflictCount($pdo, $sharedModuleIds, $assessmentDate, 2);
        $risk = $conflictCount >= 4 ? 'High' : ($conflictCount >= 2 ? 'Medium' : 'Low');

        $pdo->beginTransaction();
        $insertStmt = $pdo->prepare("
            INSERT INTO lecturer_assessments (
                module_id, created_by_lecturer_id, title, assessment_date, assessment_time, duration, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, 'published', ?)
        ");
        $insertStmt->execute([
            $moduleId,
            $lecturerId,
            $title,
            $assessmentDate,
            $assessmentTime,
            $duration,
            $notes !== '' ? $notes : null,
        ]);
        $assessmentId = (int) $pdo->lastInsertId();

        $queued = queueAssessmentNotifications(
            $pdo,
            $assessmentId,
            $moduleId,
            $title,
            $assessmentDate,
            $assessmentTime
        );
        $pdo->commit();

        $_SESSION['success_message'] = "Assessment published ({$risk} risk, {$conflictCount} nearby shared-course item(s)). {$queued} notification(s) queued.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError($e, 'Creating lecturer assessment');
        $_SESSION['error_message'] = getErrorMessage($e, 'Creating assessment');
    }
    $redirectId = isset($_POST['lecturer_id']) ? (int) $_POST['lecturer_id'] : 0;
    header('Location: lecturer_planner.php' . ($redirectId > 0 ? '?lecturer_id=' . $redirectId : ''));
    exit;
}

try {
    $lecturers = $pdo->query("
        SELECT lecturer_id, lecturer_name, email
        FROM lecturers
        ORDER BY lecturer_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $selectedLecturerId = isset($_GET['lecturer_id']) ? (int) $_GET['lecturer_id'] : 0;
    if ($selectedLecturerId <= 0 && !empty($lecturers)) {
        $selectedLecturerId = (int) $lecturers[0]['lecturer_id'];
    }

    $selectedLecturer = null;
    $timetableRows = [];
    $taughtModules = [];
    $upcomingModuleExams = [];
    $upcomingModuleAssessments = [];
    $sharedCalendarItems = [];
    $moduleExamIndex = [];
    $moduleAssessmentIndex = [];
    $examLoadByDate = [];
    $assessmentLoadByDate = [];
    $recommendations = [];
    $alerts = [];

    if ($selectedLecturerId > 0) {
        $lecturerStmt = $pdo->prepare("
            SELECT lecturer_id, lecturer_name, email
            FROM lecturers
            WHERE lecturer_id = ?
            LIMIT 1
        ");
        $lecturerStmt->execute([$selectedLecturerId]);
        $selectedLecturer = $lecturerStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $timetableStmt = $pdo->prepare("
            SELECT
                s.session_id,
                s.day_of_week,
                s.start_time,
                s.end_time,
                m.module_id,
                m.module_code,
                m.module_name,
                v.venue_name
            FROM sessions s
            JOIN modules m ON m.module_id = s.module_id
            LEFT JOIN venues v ON v.venue_id = s.venue_id
            WHERE s.lecturer_id = ?
            ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time
        ");
        $timetableStmt->execute([$selectedLecturerId]);
        $timetableRows = $timetableStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($timetableRows as $row) {
            $moduleId = (int) $row['module_id'];
            if (!isset($taughtModules[$moduleId])) {
                $taughtModules[$moduleId] = [
                    'module_id' => $moduleId,
                    'module_code' => $row['module_code'],
                    'module_name' => $row['module_name'],
                ];
            }
        }

        if (!empty($taughtModules)) {
            $moduleIds = array_keys($taughtModules);
            $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));

            $moduleExamsStmt = $pdo->prepare("
                SELECT
                    e.exam_id,
                    e.module_id,
                    e.exam_date,
                    e.exam_time,
                    e.duration,
                    m.module_code,
                    m.module_name
                FROM exams e
                JOIN modules m ON m.module_id = e.module_id
                WHERE e.module_id IN ($placeholders)
                  AND e.exam_date >= CURDATE()
                ORDER BY e.exam_date, e.exam_time
            ");
            $moduleExamsStmt->execute($moduleIds);
            $upcomingModuleExams = $moduleExamsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($upcomingModuleExams as $exam) {
                $moduleId = (int) $exam['module_id'];
                if (!isset($moduleExamIndex[$moduleId])) {
                    $moduleExamIndex[$moduleId] = [];
                }
                $moduleExamIndex[$moduleId][] = $exam;
            }

            $assessmentStmt = $pdo->prepare("
                SELECT
                    a.assessment_id,
                    a.module_id,
                    a.title,
                    a.assessment_date,
                    a.assessment_time,
                    a.duration,
                    m.module_code,
                    m.module_name
                FROM lecturer_assessments a
                JOIN modules m ON m.module_id = a.module_id
                WHERE a.module_id IN ($placeholders)
                  AND a.assessment_date >= CURDATE()
                ORDER BY a.assessment_date, a.assessment_time
            ");
            $assessmentStmt->execute($moduleIds);
            $upcomingModuleAssessments = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($upcomingModuleAssessments as $assessment) {
                $moduleId = (int) $assessment['module_id'];
                if (!isset($moduleAssessmentIndex[$moduleId])) {
                    $moduleAssessmentIndex[$moduleId] = [];
                }
                $moduleAssessmentIndex[$moduleId][] = $assessment;
            }

            $sharedModuleIds = [];
            foreach ($moduleIds as $moduleId) {
                $sharedModuleIds = array_merge($sharedModuleIds, getSharedCohortModuleIds($pdo, (int) $moduleId));
            }
            $sharedModuleIds = array_values(array_unique($sharedModuleIds));
            if (!empty($sharedModuleIds)) {
                $sharedPlaceholders = implode(',', array_fill(0, count($sharedModuleIds), '?'));
                $sharedCalendarSql = "
                    SELECT
                        e.exam_date AS item_date,
                        e.exam_time AS item_time,
                        'Exam' AS item_type,
                        m.module_code,
                        m.module_name,
                        NULL AS lecturer_name
                    FROM exams e
                    JOIN modules m ON m.module_id = e.module_id
                    WHERE e.module_id IN ($sharedPlaceholders)
                      AND e.exam_date >= CURDATE()
                    UNION ALL
                    SELECT
                        a.assessment_date AS item_date,
                        a.assessment_time AS item_time,
                        'Assessment' AS item_type,
                        m.module_code,
                        m.module_name,
                        l.lecturer_name
                    FROM lecturer_assessments a
                    JOIN modules m ON m.module_id = a.module_id
                    JOIN lecturers l ON l.lecturer_id = a.created_by_lecturer_id
                    WHERE a.module_id IN ($sharedPlaceholders)
                      AND a.assessment_date >= CURDATE()
                    ORDER BY item_date, item_time
                    LIMIT 80
                ";
                $sharedParams = array_merge($sharedModuleIds, $sharedModuleIds);
                $sharedStmt = $pdo->prepare($sharedCalendarSql);
                $sharedStmt->execute($sharedParams);
                $sharedCalendarItems = $sharedStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $loadStmt = $pdo->query("
            SELECT exam_date, COUNT(*) AS total_exams
            FROM exams
            WHERE exam_date >= CURDATE()
              AND exam_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            GROUP BY exam_date
        ");
        foreach ($loadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $examLoadByDate[$row['exam_date']] = (int) $row['total_exams'];
        }
        $assessmentLoadStmt = $pdo->query("
            SELECT assessment_date, COUNT(*) AS total_assessments
            FROM lecturer_assessments
            WHERE assessment_date >= CURDATE()
              AND assessment_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            GROUP BY assessment_date
        ");
        foreach ($assessmentLoadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $assessmentLoadByDate[$row['assessment_date']] = (int) $row['total_assessments'];
        }

        $teachingDays = [];
        foreach ($timetableRows as $row) {
            $teachingDays[$row['day_of_week']] = true;
        }

        for ($i = 3; $i <= 21; $i++) {
            $candidateTs = strtotime("+$i day");
            $candidateDay = date('l', $candidateTs);
            if (!isset($teachingDays[$candidateDay])) {
                continue;
            }

            $candidateDate = date('Y-m-d', $candidateTs);
            $load = ($examLoadByDate[$candidateDate] ?? 0) + ($assessmentLoadByDate[$candidateDate] ?? 0);
            $risk = $load >= 4 ? 'High' : ($load >= 2 ? 'Medium' : 'Low');
            $score = max(0, 10 - ($load * 2));

            $recommendations[] = [
                'date' => $candidateDate,
                'day' => $candidateDay,
                'exam_load' => $load,
                'risk' => $risk,
                'score' => $score,
            ];
        }

        usort($recommendations, function ($a, $b) {
            if ($a['exam_load'] === $b['exam_load']) {
                return strcmp($a['date'], $b['date']);
            }
            return $a['exam_load'] <=> $b['exam_load'];
        });
        $recommendations = array_slice($recommendations, 0, 3);

        foreach ($taughtModules as $module) {
            $moduleId = (int) $module['module_id'];
            $hasAssessmentSoon = false;
            if (isset($moduleExamIndex[$moduleId])) {
                foreach ($moduleExamIndex[$moduleId] as $exam) {
                    if (strtotime($exam['exam_date']) <= strtotime('+30 day')) {
                        $hasAssessmentSoon = true;
                        break;
                    }
                }
            }
            if (!$hasAssessmentSoon && isset($moduleAssessmentIndex[$moduleId])) {
                foreach ($moduleAssessmentIndex[$moduleId] as $assessment) {
                    if (strtotime($assessment['assessment_date']) <= strtotime('+30 day')) {
                        $hasAssessmentSoon = true;
                        break;
                    }
                }
            }

            if (!$hasAssessmentSoon) {
                $alerts[] = [
                    'type' => 'warning',
                    'text' => "No upcoming assessment found for {$module['module_code']}. Set a test window in the next 2-3 weeks.",
                ];
            }
        }

        $lecturerExamDensity = [];
        foreach ($upcomingModuleExams as $exam) {
            $examDate = $exam['exam_date'];
            $lecturerExamDensity[$examDate] = ($lecturerExamDensity[$examDate] ?? 0) + 1;
        }
        foreach ($lecturerExamDensity as $examDate => $count) {
            if ($count >= 2) {
                $alerts[] = [
                    'type' => 'info',
                    'text' => "You already have {$count} module exam(s) on {$examDate}. Avoid adding another test on this date.",
                ];
            }
        }

        if (empty($recommendations)) {
            $alerts[] = [
                'type' => 'info',
                'text' => 'No recommendation slots were generated. Add lecturer sessions first so the planner can match teaching days.',
            ];
        }
    }
} catch (Exception $e) {
    logError($e, 'Loading lecturer planner');
    $lecturers = [];
    $selectedLecturerId = 0;
    $selectedLecturer = null;
    $timetableRows = [];
    $upcomingModuleExams = [];
    $upcomingModuleAssessments = [];
    $sharedCalendarItems = [];
    $recommendations = [];
    $alerts = [];
    $_SESSION['error_message'] = getErrorMessage($e, 'Loading lecturer planner');
}
?>
<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => 'index.php'],
    ['label' => 'Lecturer Planner', 'href' => null],
];
$page_actions = [
    ['label' => 'Manage Exams', 'href' => 'exams.php'],
    ['label' => 'Lecturer Logins', 'href' => 'lecturer_credentials.php'],
];
include 'header_modern.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="content-card" style="margin-bottom: 20px;">
    <form method="GET" class="form" style="margin-bottom: 0;">
        <div class="form-row" style="margin-bottom: 0;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="lecturer_id">Lecturer</label>
                <select name="lecturer_id" id="lecturer_id" onchange="this.form.submit()">
                    <?php if (empty($lecturers)): ?>
                        <option value="">No lecturers available</option>
                    <?php else: ?>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?= (int) $lecturer['lecturer_id'] ?>" <?= (int) $lecturer['lecturer_id'] === $selectedLecturerId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lecturer['lecturer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<?php if ($selectedLecturer): ?>
    <div class="content-card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 8px; color: #e8edff; font-size: 18px; font-weight: 700;">Lecturer Snapshot</h3>
        <p style="margin: 0; color: rgba(220,230,255,0.75);">
            <?= htmlspecialchars($selectedLecturer['lecturer_name']) ?>
            <?php if (!empty($selectedLecturer['email'])): ?>
                - <?= htmlspecialchars($selectedLecturer['email']) ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="content-card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Publish New Test</h3>
        <form method="POST" class="form" style="margin-bottom: 0;">
            <input type="hidden" name="create_assessment" value="1">
            <input type="hidden" name="lecturer_id" value="<?= (int) $selectedLecturerId ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Module</label>
                    <select name="module_id" required>
                        <option value="">Select module</option>
                        <?php foreach ($taughtModules as $module): ?>
                            <option value="<?= (int) $module['module_id'] ?>">
                                <?= htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="e.g. Test 2" required>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="assessment_date" required>
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="assessment_time" required>
                </div>
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" min="30" max="240" value="60" required>
                </div>
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <input type="text" name="notes" placeholder="Any extra instructions">
                </div>
            </div>
            <button type="submit" class="btn btn-success">Publish Test + Queue Student Notifications</button>
        </form>
    </div>

    <?php if (!empty($alerts)): ?>
        <div class="content-card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Planning Alerts</h3>
            <?php foreach ($alerts as $alert): ?>
                <div class="pill <?= $alert['type'] === 'warning' ? 'pill--yellow' : 'pill--blue' ?>" style="display: block; margin-bottom: 10px; padding: 10px 12px;">
                    <?= htmlspecialchars($alert['text']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="content-card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Recommended Test Windows</h3>
        <?php if (empty($recommendations)): ?>
            <p style="margin: 0; color: rgba(220,230,255,0.75);">No recommendations available yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Other Assessments</th>
                            <th>Risk</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recommendations as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td><?= htmlspecialchars($row['day']) ?></td>
                                <td><?= (int) $row['exam_load'] ?></td>
                                <td>
                                    <?php
                                    $riskClass = $row['risk'] === 'Low' ? 'pill--blue' : ($row['risk'] === 'Medium' ? 'pill--yellow' : 'pill--purple');
                                    ?>
                                    <span class="pill <?= $riskClass ?>"><?= htmlspecialchars($row['risk']) ?></span>
                                </td>
                                <td><?= (int) $row['score'] ?>/10</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-card" style="margin-bottom: 20px;">
        <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Lecturer Timetable</h3>
        <?php if (empty($timetableRows)): ?>
            <p style="margin: 0; color: rgba(220,230,255,0.75);">No sessions found for this lecturer.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Venue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetableRows as $session): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($session['module_code']) ?></strong><br>
                                    <small style="color: rgba(220,230,255,0.65);"><?= htmlspecialchars($session['module_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($session['day_of_week']) ?></td>
                                <td><?= htmlspecialchars(substr($session['start_time'], 0, 5) . ' - ' . substr($session['end_time'], 0, 5)) ?></td>
                                <td><?= htmlspecialchars($session['venue_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-card">
        <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Upcoming Exams For Taught Modules</h3>
        <?php if (empty($upcomingModuleExams)): ?>
            <p style="margin: 0; color: rgba(220,230,255,0.75);">No upcoming exams are linked to this lecturer's modules.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingModuleExams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($exam['module_code']) ?></strong><br>
                                    <small style="color: rgba(220,230,255,0.65);"><?= htmlspecialchars($exam['module_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($exam['exam_date']) ?></td>
                                <td><?= htmlspecialchars(substr($exam['exam_time'], 0, 5)) ?></td>
                                <td><?= (int) $exam['duration'] ?> min</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-card" style="margin-top: 20px;">
        <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Published Tests (Lecturer Planner)</h3>
        <?php if (empty($upcomingModuleAssessments)): ?>
            <p style="margin: 0; color: rgba(220,230,255,0.75);">No lecturer tests published yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingModuleAssessments as $assessment): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($assessment['module_code']) ?></strong><br>
                                    <small style="color: rgba(220,230,255,0.65);"><?= htmlspecialchars($assessment['module_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($assessment['title']) ?></td>
                                <td><?= htmlspecialchars($assessment['assessment_date']) ?></td>
                                <td><?= htmlspecialchars(substr($assessment['assessment_time'], 0, 5)) ?></td>
                                <td><?= (int) $assessment['duration'] ?> min</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-card" style="margin-top: 20px;">
        <h3 style="margin-bottom: 14px; color: #e8edff; font-size: 18px; font-weight: 700;">Shared-Course Assessment Calendar</h3>
        <p style="margin: 0 0 12px 0; color: rgba(220,230,255,0.7);">Shows exams and lecturer tests from shared cohorts to avoid overloading students.</p>
        <?php if (empty($sharedCalendarItems)): ?>
            <p style="margin: 0; color: rgba(220,230,255,0.75);">No shared-course items found for the current horizon.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table compact">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Module</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Lecturer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sharedCalendarItems as $item): ?>
                            <tr>
                                <td><span class="pill <?= $item['item_type'] === 'Exam' ? 'pill--purple' : 'pill--blue' ?>"><?= htmlspecialchars($item['item_type']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['module_code']) ?></strong><br>
                                    <small style="color: rgba(220,230,255,0.65);"><?= htmlspecialchars($item['module_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($item['item_date']) ?></td>
                                <td><?= htmlspecialchars(substr((string) $item['item_time'], 0, 5)) ?></td>
                                <td><?= htmlspecialchars($item['lecturer_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="content-card">
        <p style="margin: 0; color: rgba(220,230,255,0.75);">No lecturer selected. Add lecturers from the Lecturers page first.</p>
    </div>
<?php endif; ?>

<?php include 'footer_modern.php'; ?>
