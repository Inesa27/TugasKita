<?php
declare(strict_types=1);

function subjects_page(array $user): string
{
    $createForm = '';
    if ($user['role'] === 'guru' && is_teacher_admin($user)) {
        $createForm = '
        <section class="panel subject-create-panel">
            <div class="panel-head"><h2>Tambah Mata Pelajaran</h2></div>
            <form class="form spacious-form" method="post" action="' . url('/index.php?action=create_subject') . '">
                <div class="form-grid">
                    <label>Nama mata pelajaran <input name="name" required maxlength="100" placeholder="Isi nama mata pelajaran..."></label>
                    <label>Guru pengampu <input name="teacher_name" required maxlength="100" placeholder="Isi nama guru..."></label>
                    <label>Jadwal pelajaran <input name="schedule" required maxlength="120" placeholder="Isi jadwal pelajaran..."></label>
                    <label>Kelas
                        <select name="grade_level" required>
                            <option value="X">Kelas 10</option>
                            <option value="XI">Kelas 11</option>
                            <option value="XII">Kelas 12</option>
                        </select>
                    </label>
                    <label>Keterangan singkat <input name="description" required maxlength="240" placeholder="Isi keterangan..."></label>
                </div>
                <button class="button primary" type="submit">Tambah Mata Pelajaran</button>
            </form>
        </section>';
    }

    return '
    <section class="page-head">
        <div><span class="eyebrow">Mata Pelajaran</span><h1>Pilih mata pelajaran</h1></div>
    </section>
    ' . flash_html() . $createForm . '
    <section class="subject-grid">' . subject_cards_html() . '</section>';
}

function subject_cards_html(string $mode = 'subject'): string
{
    $gradeLevels = [];
    $current = null;
    if (!empty($_SESSION['user_id'])) {
        $current = current_user();
        if ($current && $current['role'] === 'siswa') {
            $studentGrade = class_level_from_name($current['class_name'] ?? null);
            $gradeLevels = $studentGrade ? [$studentGrade] : [];
        } elseif ($current && $current['role'] === 'guru' && !is_teacher_admin($current)) {
            $gradeLevels = teacher_grade_levels($current);
        }
    }

    $sql = '
        SELECT subj.*,
               COUNT(DISTINCT a.id) AS assignment_count,
               COUNT(DISTINCT m.id) AS material_count
        FROM subjects subj
        LEFT JOIN assignments a ON a.subject_id = subj.id
        LEFT JOIN materials m ON m.subject_id = subj.id
    ';
    $params = [];
    if ($gradeLevels) {
        $sql .= ' WHERE 1 = 1' . add_in_condition('subj.grade_level', $gradeLevels, 'card_grade', $params);
    }
    $sql .= ' GROUP BY subj.id ORDER BY subj.grade_level, subj.name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if ($current && $current['role'] === 'guru') {
        usort($rows, function (array $left, array $right) use ($current): int {
            $leftLocked = !can_manage_subject($current, $left);
            $rightLocked = !can_manage_subject($current, $right);
            if ($leftLocked !== $rightLocked) {
                return $leftLocked <=> $rightLocked;
            }
            return [$left['grade_level'], $left['name']] <=> [$right['grade_level'], $right['name']];
        });
    }

    if (!$rows) {
        return '<div class="empty-state panel"><strong>Belum ada mata pelajaran</strong></div>';
    }

    $cards = [];
    foreach ($rows as $row) {
        $locked = $current && $current['role'] === 'guru' && !can_manage_subject($current, $row);
        $href = $mode === 'upload'
            ? url('/index.php?page=upload&subject=' . $row['id'])
            : url('/index.php?page=subject&id=' . $row['id']);
        $cardInner = '
            <span class="subject-chip">' . e($locked ? 'Terkunci' : $row['name']) . '</span>
            <h2>' . e($row['name']) . '</h2>
            <dl>
                <div><dt>Kelas</dt><dd>' . e($row['grade_level']) . '</dd></div>
                <div><dt>Guru pengampu</dt><dd>' . e($row['teacher_name']) . '</dd></div>
                <div><dt>Jadwal</dt><dd>' . e($row['schedule']) . '</dd></div>
                <div><dt>Materi</dt><dd>' . e($row['material_count']) . '</dd></div>
                <div><dt>Tugas</dt><dd>' . e($row['assignment_count']) . '</dd></div>
            </dl>
            ' . ($locked ? '<p class="locked-note">Hanya guru pengampu mapel ini yang bisa membuka dan mengelolanya.</p>' : '');
        $cards[] = $locked
            ? '<article class="subject-card locked-card" aria-disabled="true">' . $cardInner . '</article>'
            : '<a class="subject-card" href="' . $href . '">' . $cardInner . '</a>';
    }
    return implode('', $cards);
}

function subject_detail_page(array $user): string
{
    $subjectId = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM subjects WHERE id = :id');
    $stmt->execute([':id' => $subjectId]);
    $subject = $stmt->fetch();
    if (!$subject || !can_access_subject($user, $subject)) {
        return '<section class="page-head compact-head"><a class="button secondary" href="' . url('/index.php?page=subjects') . '">Kembali</a></section><section class="panel"><div class="empty-state"><strong>Mata pelajaran tidak ditemukan</strong></div></section>';
    }

    $materialsStmt = db()->prepare('
        SELECT m.*, u.name AS author_name
        FROM materials m
        JOIN users u ON u.id = m.created_by
        WHERE m.subject_id = :subject_id
        ORDER BY m.created_at DESC
    ');
    $materialsStmt->execute([':subject_id' => $subjectId]);
    $materials = $materialsStmt->fetchAll();

    if ($user['role'] === 'guru') {
        $assignmentStmt = db()->prepare('
            SELECT a.*, COUNT(s.id) AS submissions_count
            FROM assignments a
            LEFT JOIN submissions s ON s.assignment_id = a.id
            WHERE a.subject_id = :subject_id
            GROUP BY a.id
            ORDER BY a.due_date ASC
        ');
        $assignmentStmt->execute([':subject_id' => $subjectId]);
    } else {
        $assignmentStmt = db()->prepare('
            SELECT a.*, s.id AS submission_id, s.original_filename, s.file_size, s.submitted_at, s.is_late, g.score
            FROM assignments a
            LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :student_id
            LEFT JOIN grades g ON g.submission_id = s.id
            WHERE a.subject_id = :subject_id
            ORDER BY a.due_date ASC
        ');
        $assignmentStmt->execute([':subject_id' => $subjectId, ':student_id' => $user['id']]);
    }
    $assignments = $assignmentStmt->fetchAll();

    $materialItems = [];
    foreach ($materials as $material) {
        $content = trim((string) $material['content']);
        $fileLink = !empty($material['stored_filename'])
            ? '<a class="material-file-link" href="' . url('/index.php?action=download_material&id=' . $material['id']) . '">' . e($material['original_filename']) . ' <small>' . file_size_label((int) $material['file_size']) . '</small></a>'
            : '';
        $materialItems[] = '
        <article class="material-card">
            <div>
                <strong>' . e($material['title']) . '</strong>
                <small>Oleh ' . e($material['author_name']) . ' - ' . format_date($material['created_at']) . '</small>
            </div>
            <p>' . e(material_excerpt($content)) . '</p>
            ' . $fileLink . '
            <a class="button secondary material-open" href="' . url('/index.php?page=material&id=' . $material['id']) . '">Buka Materi</a>
        </article>';
    }

    $assignmentItems = [];
    foreach ($assignments as $assignment) {
        $detail = $user['role'] === 'guru'
            ? '<div><dt>Terkumpul</dt><dd>' . e($assignment['submissions_count']) . ' file</dd></div>'
            : '<div><dt>Status</dt><dd>' . assignment_status($assignment['submission_id'] ? $assignment : null, $assignment['due_date'], $assignment['score'] !== null ? $assignment : null) . '</dd></div>';
        $action = $user['role'] === 'guru'
            ? '<a class="button secondary" href="' . url('/index.php?page=grading') . '">Buka Penilaian</a>'
            : '<a class="button primary" href="' . url('/index.php?page=upload&subject=' . $subjectId . '&assignment=' . $assignment['id']) . '">Upload / Ganti File</a>';
        $assignmentItems[] = '
        <article class="task-card">
            <div>
                <span class="subject-chip">Tugas</span>
                <h2>' . e($assignment['title']) . '</h2>
                <p>' . e($assignment['description']) . '</p>
            </div>
            <dl>
                <div><dt>Deadline</dt><dd>' . format_date($assignment['due_date']) . '</dd></div>
                <div><dt>Nilai maks.</dt><dd>' . e($assignment['max_score']) . '</dd></div>
                ' . $detail . '
            </dl>
            ' . $action . '
        </article>';
    }

    $materialForm = '';
    if ($user['role'] === 'guru') {
        $materialForm = '
        <article class="panel">
            <div class="panel-head"><h2>Tambah Materi</h2></div>
            <form class="form" method="post" action="' . url('/index.php?action=create_material') . '" enctype="multipart/form-data">
                <input type="hidden" name="subject_id" value="' . e($subjectId) . '">
                <label>Judul materi <input name="title" required maxlength="120" placeholder="Isi judul materi..."></label>
                <label>Isi materi <textarea name="content" rows="5" maxlength="8000" placeholder="Isi materi..."></textarea></label>
                <label>File materi <input type="file" name="material_file"></label>
                <button class="button primary" type="submit">Simpan Materi</button>
            </form>
        </article>';
    }

    return '
    <section class="page-head compact-head">
        <a class="button secondary" href="' . url('/index.php?page=subjects') . '">Kembali</a>
    </section>
    <section class="subject-hero">
        <span class="eyebrow">Detail Mata Pelajaran</span>
        <h1>' . e($subject['name']) . '</h1>
        <p>' . e($subject['description']) . '</p>
        <dl>
            <div><dt>Kelas</dt><dd>' . e($subject['grade_level']) . '</dd></div>
            <div><dt>Guru pengampu</dt><dd>' . e($subject['teacher_name']) . '</dd></div>
            <div><dt>Jadwal</dt><dd>' . e($subject['schedule']) . '</dd></div>
        </dl>
    </section>
    ' . flash_html() . '
    <section class="grid two-col subject-layout">
        <div>
            <section class="panel">
                <div class="panel-head"><h2>Materi</h2></div>
                <div class="material-list">' . ($materialItems ? implode('', $materialItems) : '<div class="empty-state"><strong>Belum ada materi</strong></div>') . '</div>
            </section>
            ' . $materialForm . '
        </div>
        <section class="task-list">' . ($assignmentItems ? implode('', $assignmentItems) : '<div class="empty-state panel"><strong>Belum ada tugas</strong></div>') . '</section>
    </section>';
}

function material_detail_page(array $user): string
{
    $materialId = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('
        SELECT m.*, u.name AS author_name, subj.id AS subject_id, subj.name AS name, subj.name AS subject_name, subj.grade_level, subj.teacher_name
        FROM materials m
        JOIN users u ON u.id = m.created_by
        JOIN subjects subj ON subj.id = m.subject_id
        WHERE m.id = :id
    ');
    $stmt->execute([':id' => $materialId]);
    $material = $stmt->fetch();

    if (!$material || !can_access_subject($user, $material)) {
        return '<section class="page-head compact-head"><a class="button secondary" href="' . url('/index.php?page=subjects') . '">Kembali</a></section><section class="panel"><div class="empty-state"><strong>Materi tidak ditemukan</strong></div></section>';
    }

    $content = trim((string) $material['content']);
    $fileLink = !empty($material['stored_filename'])
        ? '<a class="material-file-link" href="' . url('/index.php?action=download_material&id=' . $material['id']) . '">' . e($material['original_filename']) . ' <small>' . file_size_label((int) $material['file_size']) . '</small></a>'
        : '';
    $body = $content !== ''
        ? '<div class="material-body">' . e($content) . '</div>'
        : '<div class="empty-state"><strong>Materi ini berupa file lampiran</strong></div>';

    return '
    <section class="page-head">
        <div><span class="eyebrow">Materi</span><h1>' . e($material['title']) . '</h1><p class="page-subtitle">' . e($material['subject_name']) . ' - ' . e(grade_level_label($material['grade_level'])) . '</p></div>
        <a class="button secondary" href="' . url('/index.php?page=subject&id=' . $material['subject_id']) . '">Kembali</a>
    </section>
    ' . flash_html() . '
    <section class="panel material-detail-panel">
        <div class="panel-head"><h2>Isi Materi</h2><small>Oleh ' . e($material['author_name']) . ' - ' . format_date($material['created_at']) . '</small></div>
        <article class="material-detail-content">
            ' . $body . '
            ' . $fileLink . '
        </article>
    </section>';
}

function create_assignment_page(array $user): string
{
    $allSubjects = db()->query('SELECT id, grade_level, name FROM subjects ORDER BY grade_level, name')->fetchAll();
    $subjects = [];
    foreach ($allSubjects as $subject) {
        if (can_manage_subject($user, $subject)) {
            $subjects[] = $subject;
        }
    }
    $options = '';
    foreach ($subjects as $subject) {
        $options .= '<option value="' . e($subject['id']) . '">Kelas ' . e($subject['grade_level']) . ' - ' . e($subject['name']) . '</option>';
    }
    if ($options === '') {
        $options = '<option value="">Belum ada mata pelajaran</option>';
    }

    return '
    <section class="page-head">
        <div><span class="eyebrow">Buat Tugas</span><h1>Tugas baru untuk siswa</h1><p class="page-subtitle">' . e(teacher_scope_label($user)) . '</p></div>
        <a class="button secondary" href="' . url('/index.php?page=tasks') . '">Lihat Daftar Tugas</a>
    </section>
    ' . flash_html() . '
    <section class="form-page">
        <article class="panel feature-panel">
            <div class="panel-head"><h2>Detail Tugas</h2></div>
            <form class="form spacious-form" method="post" action="' . url('/index.php?action=create_assignment') . '">
                <div class="form-grid">
                    <label>Judul tugas <input name="title" required maxlength="120" placeholder="Isi judul tugas..."></label>
                    <label>Mata pelajaran <select name="subject_id" required>' . $options . '</select></label>
                    <label>Deadline <input type="datetime-local" name="due_date" required></label>
                    <label>Nilai maksimal <input type="number" name="max_score" min="1" max="1000" value="100" required></label>
                </div>
                <label>Deskripsi tugas <textarea name="description" required rows="6" maxlength="800" placeholder="Isi deskripsi tugas..."></textarea></label>
                <button class="button primary" type="submit">Simpan Tugas</button>
            </form>
        </article>
    </section>';
}
