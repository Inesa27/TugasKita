<?php
declare(strict_types=1);

if ($action === 'grade' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role('guru');
    $submissionId = (int) ($_POST['submission_id'] ?? 0);
    $score = (int) ($_POST['score'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    $stmt = db()->prepare('
        SELECT s.*, a.max_score, a.teacher_id, subj.grade_level, subj.name
        FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        JOIN subjects subj ON subj.id = a.subject_id
        WHERE s.id = :id
    ');
    $stmt->execute([':id' => $submissionId]);
    $submission = $stmt->fetch();

    if (!$submission || !can_manage_subject($user, $submission)) {
        http_response_code(403);
        exit('Pengumpulan tidak dapat dinilai oleh akun ini.');
    }

    $score = max(0, min($score, (int) $submission['max_score']));
    $stmt = db()->prepare('
        INSERT INTO grades (submission_id, teacher_id, score, feedback, graded_at)
        VALUES (:submission_id, :teacher_id, :score, :feedback, :graded_at)
        ON CONFLICT(submission_id) DO UPDATE SET
            teacher_id = excluded.teacher_id,
            score = excluded.score,
            feedback = excluded.feedback,
            graded_at = excluded.graded_at
    ');
    $stmt->execute([
        ':submission_id' => $submissionId,
        ':teacher_id' => $user['id'],
        ':score' => $score,
        ':feedback' => $feedback,
        ':graded_at' => date('Y-m-d H:i:s'),
    ]);

    flash('success', 'Nilai berhasil disimpan.');
    redirect_to('/index.php?page=grading#submission-' . $submissionId);
}
