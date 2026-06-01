<?php
declare(strict_types=1);

if ($action === 'download') {
    $user = require_login();
    $submissionId = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('
        SELECT s.*, a.teacher_id, subj.grade_level, subj.name
        FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        JOIN subjects subj ON subj.id = a.subject_id
        WHERE s.id = :id
    ');
    $stmt->execute([':id' => $submissionId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }

    $allowed = ($user['role'] === 'guru' && can_manage_subject($user, $submission))
        || ($user['role'] === 'siswa' && (int) $submission['student_id'] === (int) $user['id'] && can_access_subject($user, $submission));
    if (!$allowed) {
        http_response_code(403);
        exit('Akses file tidak diizinkan.');
    }

    $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $submission['stored_filename'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('File tidak tersedia di server.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($submission['original_filename']) . '"');
    readfile($path);
    exit;
}

if ($action === 'download_material') {
    $user = require_login();
    $materialId = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('
        SELECT m.*, subj.grade_level, subj.name
        FROM materials m
        JOIN subjects subj ON subj.id = m.subject_id
        WHERE m.id = :id
    ');
    $stmt->execute([':id' => $materialId]);
    $material = $stmt->fetch();

    if (!$material || empty($material['stored_filename']) || empty($material['original_filename'])) {
        http_response_code(404);
        exit('File materi tidak ditemukan.');
    }

    if (!can_access_subject($user, $material)) {
        http_response_code(403);
        exit('Akses file materi tidak diizinkan.');
    }

    $path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $material['stored_filename'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('File materi tidak tersedia di server.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($material['original_filename']) . '"');
    readfile($path);
    exit;
}
