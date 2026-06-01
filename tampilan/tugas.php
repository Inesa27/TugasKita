<?php
declare(strict_types=1);

function tasks_page(array $user): string
{
    if ($user['role'] === 'siswa') {
        return student_task_list_page($user);
    }

    return '
    <section class="page-head">
        <div><span class="eyebrow">Daftar Tugas</span><h1>Tugas per mata pelajaran</h1></div>
        <a class="button primary" href="' . url('/index.php?page=create-assignment') . '">Buat Tugas Baru</a>
    </section>
    ' . flash_html() . '
    <section class="subject-grid">' . subject_cards_html() . '</section>';
}

function student_task_list_page(array $user): string
{
    $params = [':student_id' => $user['id']];
    $gradeLevel = class_level_from_name($user['class_name'] ?? null);
    $sql = '
        SELECT a.*, subj.name AS subject_name, subj.grade_level, s.id AS submission_id,
               s.original_filename, s.file_size, s.submitted_at, s.is_late,
               g.score, g.feedback, g.graded_at
        FROM assignments a
        JOIN subjects subj ON subj.id = a.subject_id
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :student_id
        LEFT JOIN grades g ON g.submission_id = s.id
    ';

    if ($gradeLevel) {
        $sql .= ' WHERE subj.grade_level = :grade_level';
        $params[':grade_level'] = $gradeLevel;
    }

    $sql .= ' ORDER BY a.due_date ASC, subj.name ASC, a.title ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();

    $items = [];
    foreach ($assignments as $assignment) {
        $status = assignment_status(
            $assignment['submission_id'] ? $assignment : null,
            $assignment['due_date'],
            $assignment['score'] !== null ? $assignment : null
        );
        $fileInfo = $assignment['submission_id']
            ? '<div><dt>File</dt><dd>' . e($assignment['original_filename']) . '</dd></div>'
            : '<div><dt>File</dt><dd>Belum upload</dd></div>';
        $uploadLabel = $assignment['submission_id'] ? 'Upload / Ganti File' : 'Upload Tugas';

        $items[] = '
        <article class="task-card">
            <div>
                <span class="subject-chip">' . e($assignment['subject_name']) . ' - Kelas ' . e($assignment['grade_level']) . '</span>
                <h2>' . e($assignment['title']) . '</h2>
                <p>' . e(material_excerpt($assignment['description'], 150)) . '</p>
            </div>
            <dl>
                <div><dt>Deadline</dt><dd>' . format_date($assignment['due_date']) . '</dd></div>
                <div><dt>Nilai maks.</dt><dd>' . e($assignment['max_score']) . '</dd></div>
                <div><dt>Status</dt><dd>' . $status . '</dd></div>
                ' . $fileInfo . '
            </dl>
            <a class="button primary" href="' . url('/index.php?page=upload&subject=' . $assignment['subject_id'] . '&assignment=' . $assignment['id']) . '">' . $uploadLabel . '</a>
        </article>';
    }

    return '
    <section class="page-head">
        <div><span class="eyebrow">Daftar Tugas</span><h1>Semua tugas</h1><p class="page-subtitle">' . e($user['class_name'] ?? 'Kelas belum diatur') . '</p></div>
    </section>
    ' . flash_html() . '
    <section class="task-list">' . ($items ? implode('', $items) : '<div class="empty-state panel"><strong>Belum ada tugas</strong></div>') . '</section>';
}

function upload_page(array $user): string
{
    $subjectId = (int) ($_GET['subject'] ?? 0);
    if ($subjectId <= 0) {
        return '
        <section class="page-head">
            <div><span class="eyebrow">Upload Tugas</span><h1>Pilih mata pelajaran</h1></div>
            <a class="button secondary" href="' . url('/index.php?page=tasks') . '">Lihat Daftar Tugas</a>
        </section>
        ' . flash_html() . '
        <section class="subject-grid">' . subject_cards_html('upload') . '</section>';
    }

    $selectedId = (string) ($_GET['assignment'] ?? '');
    $subjectStmt = db()->prepare('SELECT * FROM subjects WHERE id = :id');
    $subjectStmt->execute([':id' => $subjectId]);
    $subject = $subjectStmt->fetch();
    if (!$subject || !can_access_subject($user, $subject)) {
        return '<section class="panel"><div class="empty-state"><strong>Mata pelajaran tidak ditemukan</strong></div></section>';
    }

    $stmt = db()->prepare('
        SELECT a.*, s.id AS submission_id, s.original_filename, s.file_size, s.submitted_at, s.is_late, g.score
        FROM assignments a
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :student_id
        LEFT JOIN grades g ON g.submission_id = s.id
        WHERE a.subject_id = :subject_id
        ORDER BY a.due_date ASC
    ');
    $stmt->execute([':student_id' => $user['id'], ':subject_id' => $subjectId]);
    $rows = $stmt->fetchAll();

    $options = [];
    foreach ($rows as $row) {
        $selected = (string) $row['id'] === $selectedId ? ' selected' : '';
        $options[] = '<option value="' . e($row['id']) . '"' . $selected . '>' . e($row['title']) . '</option>';
    }

    $form = $rows ? '
        <form class="form upload-form" method="post" action="' . url('/index.php?action=upload') . '" enctype="multipart/form-data">
            <label>Pilih tugas <select name="assignment_id" required>' . implode('', $options) . '</select></label>
            <label>File tugas <input type="file" name="task_file" required></label>
            <label>Catatan <textarea name="notes" rows="4" maxlength="500" placeholder="Isi catatan..."></textarea></label>
            <button class="button primary" type="submit">Kirim Tugas</button>
        </form>' : '<div class="empty-state"><strong>Belum ada tugas</strong></div>';

    return '
    <section class="page-head">
        <div><span class="eyebrow">Upload Tugas</span><h1>' . e($subject['name']) . '</h1></div>
        <a class="button secondary" href="' . url('/index.php?page=tasks') . '">Lihat Daftar Tugas</a>
    </section>
    ' . flash_html() . '
    <section class="form-page">
        <article class="panel feature-panel">
            <div class="panel-head"><h2>Form Upload</h2></div>
            ' . $form . '
        </article>
    </section>';
}
