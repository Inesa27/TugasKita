<?php
declare(strict_types=1);

function grading_page(array $user): string
{
    $params = [];
    $scopeSql = teacher_scope_condition($user, 'subj', 'grading', $params);
    $selectedSubmissionId = (int) ($_GET['submission'] ?? 0);

    if ($selectedSubmissionId > 0) {
        $detailParams = $params;
        $detailParams[':submission_id'] = $selectedSubmissionId;
        $stmt = db()->prepare('
            SELECT s.*, u.name AS student_name, u.class_name, a.title, subj.name AS subject_name, subj.grade_level, a.max_score, g.score, g.feedback
            FROM submissions s
            JOIN users u ON u.id = s.student_id
            JOIN assignments a ON a.id = s.assignment_id
            JOIN subjects subj ON subj.id = a.subject_id
            LEFT JOIN grades g ON g.submission_id = s.id
            WHERE s.id = :submission_id
            ' . $scopeSql . '
            LIMIT 1
        ');
        $stmt->execute($detailParams);
        $row = $stmt->fetch();

        if (!$row) {
            return '
            <section class="page-head">
                <div><span class="eyebrow">Penilaian</span><h1>Review Tugas Siswa</h1></div>
                <a class="button secondary" href="' . url('/index.php?page=grading') . '">Kembali</a>
            </section>
            ' . flash_html() . '
            <section class="panel"><div class="empty-state"><strong>Pengumpulan tidak ditemukan</strong></div></section>';
        }

        $late = (int) $row['is_late'] === 1 ? badge('Terlambat', 'danger') : '';
        $notes = trim((string) ($row['notes'] ?? ''));

        return '
        <section class="page-head">
            <div>
                <span class="eyebrow">Penilaian</span>
                <h1>Review Tugas Siswa</h1>
                <p class="page-subtitle">' . e($row['student_name']) . ' | ' . e($row['subject_name']) . ' Kelas ' . e($row['grade_level']) . '</p>
            </div>
            <a class="button secondary" href="' . url('/index.php?page=grading') . '">Kembali</a>
        </section>
        ' . flash_html() . '
        <section class="panel">
            <div class="panel-head"><h2>Detail Pengumpulan</h2></div>
            <article class="grading-item grading-detail">
                <div class="grading-main">
                    <div><strong>' . e($row['student_name']) . '</strong><small>' . e($row['class_name']) . ' | ' . e($row['subject_name']) . ' - ' . e($row['title']) . '</small></div>
                    <div class="badge-row">' . ($row['score'] === null ? badge('Menunggu', 'warning') : badge('Dinilai', 'success')) . $late . '</div>
                    <dl class="meta-list">
                        <div><dt>File</dt><dd><a class="text-link" href="' . url('/index.php?action=download&id=' . $row['id']) . '">' . e($row['original_filename']) . '</a></dd></div>
                        <div><dt>Ukuran</dt><dd>' . file_size_label((int) $row['file_size']) . '</dd></div>
                        <div><dt>Dikumpulkan</dt><dd>' . format_date($row['submitted_at']) . '</dd></div>
                    </dl>
                    ' . ($notes !== '' ? '<div class="submission-notes"><strong>Catatan siswa</strong><p>' . nl2br(e($notes)) . '</p></div>' : '') . '
                </div>
                <form class="form compact-form" method="post" action="' . url('/index.php?action=grade') . '">
                    <input type="hidden" name="submission_id" value="' . e($row['id']) . '">
                    <label>Nilai <input type="number" name="score" min="0" max="' . e($row['max_score']) . '" value="' . e($row['score'] ?? '') . '" required></label>
                    <label>Komentar (opsional) <textarea name="feedback" rows="3" maxlength="700" placeholder="Isi komentar...">' . e($row['feedback'] ?? '') . '</textarea></label>
                    <div class="form-actions">
                        <button class="button primary" type="submit">Simpan Nilai</button>
                        <a class="button secondary" href="' . url('/index.php?page=grading') . '">Kembali ke Daftar</a>
                    </div>
                </form>
            </article>
        </section>';
    }

    $stmt = db()->prepare('
        SELECT s.*, u.name AS student_name, u.class_name, a.title, subj.name AS subject_name, subj.grade_level, a.max_score, g.score, g.feedback, g.graded_at
        FROM submissions s
        JOIN users u ON u.id = s.student_id
        JOIN assignments a ON a.id = s.assignment_id
        JOIN subjects subj ON subj.id = a.subject_id
        LEFT JOIN grades g ON g.submission_id = s.id
        WHERE 1 = 1
        ' . $scopeSql . '
        ORDER BY CASE WHEN g.id IS NULL THEN 0 ELSE 1 END, s.submitted_at DESC
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $late = (int) $row['is_late'] === 1 ? badge('Terlambat', 'danger') : '';
        $items[] = '<article class="grading-row" id="submission-' . e($row['id']) . '">
            <div class="grading-row-main">
                <strong>' . e($row['student_name']) . '</strong>
                <small>' . e($row['class_name']) . ' | ' . e($row['subject_name']) . ' Kelas ' . e($row['grade_level']) . ' - ' . e($row['title']) . '</small>
            </div>
            <div class="grading-row-meta">
                <span>' . format_date($row['submitted_at']) . '</span>
                <span>' . e($row['original_filename']) . '</span>
            </div>
            <div class="badge-row">' . ($row['score'] === null ? badge('Menunggu', 'warning') : badge('Dinilai', 'success')) . $late . '</div>
            <a class="button secondary" href="' . url('/index.php?page=grading&submission=' . $row['id']) . '">Review</a>
        </article>';
    }

    return '
    <section class="page-head"><div><span class="eyebrow">Penilaian</span><h1>Review Tugas Siswa</h1></div></section>
    ' . flash_html() . '
    <section class="panel"><div class="panel-head"><h2>Daftar Pengumpulan</h2></div>' . ($items ? implode('', $items) : '<div class="empty-state"><strong>Belum ada pengumpulan</strong></div>') . '</section>';
}

function results_page(array $user): string
{
    if ($user['role'] === 'guru') {
        $params = [];
        $scopeSql = teacher_scope_condition($user, 'subj', 'results', $params);
        $stmt = db()->prepare('
            SELECT g.*, s.original_filename, u.name AS student_name, u.class_name, a.title, subj.name AS subject_name, a.max_score
            FROM grades g
            JOIN submissions s ON s.id = g.submission_id
            JOIN users u ON u.id = s.student_id
            JOIN assignments a ON a.id = s.assignment_id
            JOIN subjects subj ON subj.id = a.subject_id
            WHERE 1 = 1
            ' . $scopeSql . '
            ORDER BY g.graded_at DESC
        ');
        $stmt->execute($params);
    } else {
        $stmt = db()->prepare('
            SELECT g.*, s.original_filename, a.title, subj.name AS subject_name, a.max_score
            FROM grades g
            JOIN submissions s ON s.id = g.submission_id
            JOIN assignments a ON a.id = s.assignment_id
            JOIN subjects subj ON subj.id = a.subject_id
            WHERE s.student_id = :student_id
            ORDER BY g.graded_at DESC
        ');
        $stmt->execute([':student_id' => $user['id']]);
    }

    $rows = $stmt->fetchAll();
    $resultRows = [];
    foreach ($rows as $row) {
        $firstCol = $user['role'] === 'guru'
            ? '<strong>' . e($row['student_name']) . '</strong><small>' . e($row['class_name']) . '</small>'
            : '<strong>' . e($row['title']) . '</strong><small>' . e($row['subject_name']) . '</small>';
        $secondCol = $user['role'] === 'guru'
            ? '<strong>' . e($row['title']) . '</strong><small>' . e($row['subject_name']) . '</small>'
            : e($row['original_filename']);
        $resultRows[] = '<tr>
            <td>' . $firstCol . '</td>
            <td>' . $secondCol . '</td>
            <td><strong>' . e($row['score']) . '/' . e($row['max_score']) . '</strong></td>
            <td>' . e(feedback_text($row['feedback'] ?? '')) . '</td>
            <td>' . format_date($row['graded_at']) . '</td>
        </tr>';
    }

    $headers = $user['role'] === 'guru'
        ? ['Siswa', 'Tugas', 'Nilai', 'Komentar', 'Tanggal']
        : ['Tugas', 'File', 'Nilai', 'Komentar', 'Tanggal'];

    return '
    <section class="page-head"><div><span class="eyebrow">Hasil</span><h1>Rekap Penilaian</h1></div></section>
    ' . flash_html() . '
    <section class="panel"><div class="panel-head"><h2>Daftar Hasil</h2></div>' . table_or_empty($headers, $resultRows, 'Belum ada hasil', '') . '</section>';
}
