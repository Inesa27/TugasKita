<?php
declare(strict_types=1);

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role('siswa');
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($assignmentId <= 0 || empty($_FILES['task_file']['name'])) {
        flash('danger', 'File dan tugas wajib dipilih.');
        redirect_to('/index.php?page=upload');
    }

    if ((int) $_FILES['task_file']['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Upload gagal. Coba pilih file lagi.');
        redirect_to('/index.php?page=upload');
    }

    if ((int) $_FILES['task_file']['size'] > MAX_UPLOAD_SIZE) {
        flash('danger', 'Ukuran file melebihi 10 MB.');
        redirect_to('/index.php?page=upload');
    }

    $originalName = safe_filename((string) $_FILES['task_file']['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        flash('danger', 'Format file belum didukung.');
        redirect_to('/index.php?page=upload');
    }

    $stmt = db()->prepare('
        SELECT a.*, subj.grade_level, subj.name
        FROM assignments a
        JOIN subjects subj ON subj.id = a.subject_id
        WHERE a.id = :id
    ');
    $stmt->execute([':id' => $assignmentId]);
    $assignment = $stmt->fetch();
    if (!$assignment || !can_access_subject($user, $assignment)) {
        flash('danger', 'Tugas tidak ditemukan.');
        redirect_to('/index.php?page=upload');
    }

    $storedName = 'a' . $assignmentId . '_s' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $originalName;
    $target = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string) $_FILES['task_file']['tmp_name'], $target)) {
        flash('danger', 'File tidak bisa disimpan.');
        redirect_to('/index.php?page=upload');
    }

    $submittedAt = date('Y-m-d H:i:s');
    $isLate = strtotime($submittedAt) > strtotime($assignment['due_date']) ? 1 : 0;

    $stmt = db()->prepare('SELECT * FROM submissions WHERE assignment_id = :assignment_id AND student_id = :student_id');
    $stmt->execute([':assignment_id' => $assignmentId, ':student_id' => $user['id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $oldPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $existing['stored_filename'];
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
        $stmt = db()->prepare('
            UPDATE submissions
            SET original_filename = :original_filename,
                stored_filename = :stored_filename,
                file_size = :file_size,
                notes = :notes,
                submitted_at = :submitted_at,
                is_late = :is_late
            WHERE id = :id
        ');
        $stmt->execute([
            ':original_filename' => $originalName,
            ':stored_filename' => $storedName,
            ':file_size' => (int) $_FILES['task_file']['size'],
            ':notes' => $notes,
            ':submitted_at' => $submittedAt,
            ':is_late' => $isLate,
            ':id' => $existing['id'],
        ]);
        db()->prepare('DELETE FROM grades WHERE submission_id = :id')->execute([':id' => $existing['id']]);
    } else {
        $stmt = db()->prepare('
            INSERT INTO submissions (assignment_id, student_id, original_filename, stored_filename, file_size, notes, submitted_at, is_late)
            VALUES (:assignment_id, :student_id, :original_filename, :stored_filename, :file_size, :notes, :submitted_at, :is_late)
        ');
        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':student_id' => $user['id'],
            ':original_filename' => $originalName,
            ':stored_filename' => $storedName,
            ':file_size' => (int) $_FILES['task_file']['size'],
            ':notes' => $notes,
            ':submitted_at' => $submittedAt,
            ':is_late' => $isLate,
        ]);
    }

    flash('success', 'Tugas berhasil diunggah.');
    redirect_to('/index.php?page=subject&id=' . (int) $assignment['subject_id']);
}
