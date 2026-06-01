<?php
declare(strict_types=1);

function teacher_dashboard(array $user): string
{
    $gradeLevels = teacher_grade_levels($user);
    $subjectNames = teacher_subject_names($user);
    $statsParams = [];
    $subjectFilter = teacher_scope_condition($user, 'subjects', 'stats_subjects', $statsParams);
    $materialFilter = teacher_scope_condition($user, 'subj', 'stats_materials', $statsParams);
    $assignmentFilter = teacher_scope_condition($user, 'subj', 'stats_assignments', $statsParams);
    $submissionFilter = teacher_scope_condition($user, 'subj', 'stats_submissions', $statsParams);
    $gradedFilter = teacher_scope_condition($user, 'subj', 'stats_graded', $statsParams);
    $stats = db()->prepare("
        SELECT
            (SELECT COUNT(*) FROM subjects WHERE 1 = 1" . $subjectFilter . ") AS subjects_count,
            (SELECT COUNT(*) FROM materials m JOIN subjects subj ON subj.id = m.subject_id WHERE 1 = 1" . $materialFilter . ") AS materials_count,
            (SELECT COUNT(*) FROM assignments a JOIN subjects subj ON subj.id = a.subject_id WHERE 1 = 1" . $assignmentFilter . ") AS assignments_count,
            (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN subjects subj ON subj.id = a.subject_id WHERE 1 = 1" . $submissionFilter . ") AS submissions_count,
            (SELECT COUNT(*) FROM grades g JOIN submissions s ON s.id = g.submission_id JOIN assignments a ON a.id = s.assignment_id JOIN subjects subj ON subj.id = a.subject_id WHERE 1 = 1" . $gradedFilter . ") AS graded_count
    ");
    $stats->execute($statsParams);
    $stat = $stats->fetch();

    $queryParams = [];
    $recentFilter = teacher_scope_condition($user, 'subj', 'recent', $queryParams);
    $stmt = db()->prepare('
        SELECT s.*, u.name AS student_name, u.class_name, a.title, subj.name AS subject_name, g.score
        FROM submissions s
        JOIN users u ON u.id = s.student_id
        JOIN assignments a ON a.id = s.assignment_id
        JOIN subjects subj ON subj.id = a.subject_id
        LEFT JOIN grades g ON g.submission_id = s.id
        WHERE 1 = 1
        ' . $recentFilter . '
        ORDER BY s.submitted_at DESC
        LIMIT 6
    ');
    $stmt->execute($queryParams);
    $recent = $stmt->fetchAll();

    $materialParams = [];
    $materialScope = teacher_scope_condition($user, 'subj', 'latest_materials', $materialParams);
    $materialsStmt = db()->prepare('
        SELECT m.*, subj.name AS subject_name
        FROM materials m
        JOIN subjects subj ON subj.id = m.subject_id
        WHERE 1 = 1
        ' . $materialScope . '
        ORDER BY m.created_at DESC
        LIMIT 5
    ');
    $materialsStmt->execute($materialParams);
    $materials = $materialsStmt->fetchAll();

    $recentRows = [];
    foreach ($recent as $row) {
        $recentRows[] = '<tr>
            <td><strong>' . e($row['student_name']) . '</strong><small>' . e($row['class_name']) . '</small></td>
            <td><strong>' . e($row['title']) . '</strong><small>' . e($row['subject_name']) . '</small></td>
            <td>' . format_date($row['submitted_at']) . '</td>
            <td>' . ($row['score'] === null ? badge('Menunggu', 'warning') : badge('Dinilai', 'success')) . '</td>
        </tr>';
    }

    $materialRows = [];
    foreach ($materials as $material) {
        $file = !empty($material['stored_filename'])
            ? '<a class="text-link" href="' . url('/index.php?action=download_material&id=' . $material['id']) . '">' . e($material['original_filename']) . '</a><small>' . file_size_label((int) $material['file_size']) . '</small>'
            : '<small>Teks saja</small>';
        $materialRows[] = '<tr>
            <td><strong>' . e($material['title']) . '</strong><small>' . e($material['subject_name']) . '</small></td>
            <td>' . $file . '</td>
            <td>' . format_date($material['created_at']) . '</td>
        </tr>';
    }

    $pending = (int) $stat['submissions_count'] - (int) $stat['graded_count'];
    $focusLabel = is_teacher_admin($user)
        ? 'Semua jenjang dan mata pelajaran'
        : grade_levels_label($gradeLevels) . ' - ' . list_label($subjectNames, 'Mapel belum diatur');
    return '
    <section class="page-head">
        <div><span class="eyebrow">Dashboard Guru</span><h1>Selamat datang, ' . e($user['name']) . '</h1><p class="page-subtitle">Fokus pengampu: ' . e($focusLabel) . '</p></div>
        <a class="button primary" href="' . url('/index.php?page=create-assignment') . '">Buat Tugas Baru</a>
    </section>
    ' . flash_html() . '
    <section class="stats-grid">
        ' . stat_card('Mata pelajaran', $stat['subjects_count'], 'blue') . '
        ' . stat_card('Tugas dibuat', $stat['assignments_count'], 'teal') . '
        ' . stat_card('File terkumpul', $stat['submissions_count'], 'green') . '
        ' . stat_card('Menunggu nilai', $pending, 'orange') . '
    </section>
    <section class="dashboard-focus">
        <div>
            <span class="eyebrow">Ruang Kelas</span>
            <h2>' . e($focusLabel) . '</h2>
            <p>' . e($stat['subjects_count']) . ' mata pelajaran, ' . e($stat['materials_count']) . ' materi, dan ' . e($pending) . ' pengumpulan menunggu nilai.</p>
        </div>
        <a class="button secondary" href="' . url('/index.php?page=subjects') . '">Kelola Materi</a>
    </section>
    <section class="action-grid">
        <a class="action-card" href="' . url('/index.php?page=create-assignment') . '">
            <span>Buat</span>
            <strong>Tugas baru</strong>
            <small>Pilih mata pelajaran yang tersedia untuk akun guru ini.</small>
        </a>
        <a class="action-card" href="' . url('/index.php?page=tasks') . '">
            <span>Kelola</span>
            <strong>Daftar tugas</strong>
            <small>Pantau tugas yang sudah dibuka untuk siswa.</small>
        </a>
        <a class="action-card" href="' . url('/index.php?page=grading') . '">
            <span>Nilai</span>
            <strong>Pengumpulan siswa</strong>
            <small>Lihat file masuk dan simpan nilai terbaru.</small>
        </a>
    </section>
    <section class="grid two-col dashboard-panels">
        <section class="panel">
            <div class="panel-head"><h2>Pengumpulan Terbaru</h2></div>
            ' . table_or_empty(['Siswa', 'Tugas', 'Waktu', 'Status'], $recentRows, 'Belum ada file masuk', '') . '
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Materi Terbaru</h2></div>
            ' . table_or_empty(['Materi', 'File', 'Tanggal'], $materialRows, 'Belum ada materi', '') . '
        </section>
    </section>';
}

function student_dashboard(array $user): string
{
    $gradeLevel = class_level_from_name($user['class_name'] ?? null);
    $sql = '
        SELECT a.*, subj.name AS subject_name, s.id AS submission_id, s.submitted_at, s.is_late, g.score, g.feedback, g.graded_at
        FROM assignments a
        JOIN subjects subj ON subj.id = a.subject_id
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :student_id
        LEFT JOIN grades g ON g.submission_id = s.id
    ';
    $params = [':student_id' => $user['id']];
    if ($gradeLevel) {
        $sql .= ' WHERE subj.grade_level = :grade_level';
        $params[':grade_level'] = $gradeLevel;
    }
    $sql .= '
        ORDER BY a.due_date ASC
    ';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $resultRows = [];
    $upcomingRows = [];
    $submitted = 0;
    $graded = 0;
    $needsWork = 0;

    foreach ($rows as $row) {
        if ($row['submission_id']) {
            $submitted++;
        } else {
            $needsWork++;
            if (count($upcomingRows) < 6) {
                $upcomingRows[] = '<tr>
                    <td><strong>' . e($row['title']) . '</strong><small>' . e($row['subject_name']) . '</small></td>
                    <td>' . format_date($row['due_date']) . '</td>
                    <td>' . assignment_status(null, $row['due_date']) . '</td>
                </tr>';
            }
        }
        if ($row['score'] !== null) {
            $graded++;
            if (count($resultRows) < 6) {
                $resultRows[] = '<tr>
                    <td><strong>' . e($row['title']) . '</strong><small>' . e($row['subject_name']) . '</small></td>
                    <td><strong>' . e($row['score']) . '/' . e($row['max_score']) . '</strong></td>
                    <td>' . e(feedback_text($row['feedback'] ?? '')) . '</td>
                    <td>' . format_date($row['graded_at']) . '</td>
                </tr>';
            }
        }
    }

    return '
    <section class="page-head">
        <div><span class="eyebrow">Dashboard Siswa</span><h1>Halo, ' . e($user['name']) . '</h1><p class="page-subtitle">' . e($user['class_name'] ?? 'Kelas belum diatur') . '</p></div>
        <a class="button primary" href="' . url('/index.php?page=upload') . '">Upload Tugas</a>
    </section>
    ' . flash_html() . '
    <section class="stats-grid">
        ' . stat_card('Total tugas', count($rows), 'blue') . '
        ' . stat_card('Sudah dikumpulkan', $submitted, 'teal') . '
        ' . stat_card('Sudah dinilai', $graded, 'green') . '
        ' . stat_card('Perlu dikerjakan', $needsWork, 'orange') . '
    </section>
    <section class="dashboard-focus">
        <div>
            <span class="eyebrow">Prioritas Belajar</span>
            <h2>' . e($needsWork) . ' tugas belum dikumpulkan</h2>
            <p>Cek tugas terdekat, siapkan file, lalu pantau nilai terbaru dari guru.</p>
        </div>
        <a class="button secondary" href="' . url('/index.php?page=subjects') . '">Buka Materi</a>
    </section>
    <section class="action-grid">
        <a class="action-card" href="' . url('/index.php?page=upload') . '">
            <span>Kirim</span>
            <strong>Upload tugas</strong>
            <small>Pilih tugas, unggah file, lalu cek statusnya.</small>
        </a>
        <a class="action-card" href="' . url('/index.php?page=tasks') . '">
            <span>Pantau</span>
            <strong>Daftar tugas</strong>
            <small>Lihat tugas aktif per mata pelajaran.</small>
        </a>
        <a class="action-card" href="' . url('/index.php?page=results') . '">
            <span>Cek</span>
            <strong>Hasil nilai</strong>
            <small>Buka nilai terbaru dari guru pengampu.</small>
        </a>
    </section>
    <section class="grid two-col dashboard-panels">
        <section class="panel"><div class="panel-head"><h2>Tugas Terdekat</h2></div>' . table_or_empty(['Tugas', 'Deadline', 'Status'], $upcomingRows, 'Tidak ada tugas menunggu', '') . '</section>
        <section class="panel"><div class="panel-head"><h2>Hasil Terbaru</h2></div>' . table_or_empty(['Tugas', 'Nilai', 'Komentar', 'Tanggal'], $resultRows, 'Belum ada hasil', '') . '</section>
    </section>';
}
