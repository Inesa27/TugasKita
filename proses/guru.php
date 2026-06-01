<?php
declare(strict_types=1);

if ($action === 'create_subject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role('guru');
    $name = trim($_POST['name'] ?? '');
    if (!is_teacher_admin($user)) {
        flash('danger', 'Hanya guru admin yang dapat menambahkan mata pelajaran.');
        redirect_to('/index.php?page=subjects');
    }
    $gradeLevel = in_array($_POST['grade_level'] ?? '', ['X', 'XI', 'XII'], true) ? $_POST['grade_level'] : '';
    $teacherName = trim($_POST['teacher_name'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $gradeLevel === '' || $teacherName === '' || $schedule === '' || $description === '') {
        flash('danger', 'Data mata pelajaran belum lengkap.');
        redirect_to('/index.php?page=subjects');
    }

    $stmt = db()->prepare('
        INSERT INTO subjects (name, grade_level, teacher_name, schedule, description, created_by)
        VALUES (:name, :grade_level, :teacher_name, :schedule, :description, :created_by)
    ');
    $stmt->execute([
        ':name' => $name,
        ':grade_level' => $gradeLevel,
        ':teacher_name' => $teacherName,
        ':schedule' => $schedule,
        ':description' => $description,
        ':created_by' => $user['id'],
    ]);

    flash('success', 'Mata pelajaran berhasil ditambahkan.');
    redirect_to('/index.php?page=subjects');
}

if ($action === 'create_material' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role('guru');
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $hasMaterialFile = !empty($_FILES['material_file']['name']);

    if ($subjectId <= 0 || $title === '' || ($content === '' && !$hasMaterialFile)) {
        flash('danger', 'Judul serta isi materi atau file materi wajib diisi.');
        redirect_to('/index.php?page=subjects');
    }

    $stmt = db()->prepare('SELECT * FROM subjects WHERE id = :id');
    $stmt->execute([':id' => $subjectId]);
    $subject = $stmt->fetch();
    if (!$subject || !can_manage_subject($user, $subject)) {
        flash('danger', 'Mata pelajaran tidak ditemukan.');
        redirect_to('/index.php?page=subjects');
    }

    $originalName = null;
    $storedName = null;
    $fileSize = null;
    if ($hasMaterialFile) {
        if ((int) $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
            flash('danger', 'Upload file materi gagal. Coba pilih file lagi.');
            redirect_to('/index.php?page=subject&id=' . $subjectId);
        }

        if ((int) $_FILES['material_file']['size'] > MAX_UPLOAD_SIZE) {
            flash('danger', 'Ukuran file materi melebihi 10 MB.');
            redirect_to('/index.php?page=subject&id=' . $subjectId);
        }

        $originalName = safe_filename((string) $_FILES['material_file']['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
            flash('danger', 'Format file materi belum didukung.');
            redirect_to('/index.php?page=subject&id=' . $subjectId);
        }

        $storedName = 'm' . $subjectId . '_t' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $originalName;
        $target = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file((string) $_FILES['material_file']['tmp_name'], $target)) {
            flash('danger', 'File materi tidak bisa disimpan.');
            redirect_to('/index.php?page=subject&id=' . $subjectId);
        }
        $fileSize = (int) $_FILES['material_file']['size'];
    }

    $stmt = db()->prepare('
        INSERT INTO materials (subject_id, title, content, original_filename, stored_filename, file_size, created_by)
        VALUES (:subject_id, :title, :content, :original_filename, :stored_filename, :file_size, :created_by)
    ');
    $stmt->execute([
        ':subject_id' => $subjectId,
        ':title' => $title,
        ':content' => $content,
        ':original_filename' => $originalName,
        ':stored_filename' => $storedName,
        ':file_size' => $fileSize,
        ':created_by' => $user['id'],
    ]);

    flash('success', 'Materi berhasil ditambahkan.');
    redirect_to('/index.php?page=subject&id=' . $subjectId);
}

if ($action === 'create_assignment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role('guru');
    $title = trim($_POST['title'] ?? '');
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $maxScore = max(1, min(1000, (int) ($_POST['max_score'] ?? 100)));

    if ($title === '' || $subjectId <= 0 || $description === '' || strtotime($dueDate) === false) {
        flash('danger', 'Data tugas belum lengkap.');
        redirect_to('/index.php?page=create-assignment');
    }

    $subjectStmt = db()->prepare('SELECT * FROM subjects WHERE id = :id');
    $subjectStmt->execute([':id' => $subjectId]);
    $subject = $subjectStmt->fetch();
    if (!$subject || !can_manage_subject($user, $subject)) {
        flash('danger', 'Mata pelajaran tidak ditemukan.');
        redirect_to('/index.php?page=create-assignment');
    }

    $columns = ['title', 'subject_id', 'description', 'due_date', 'max_score', 'teacher_id'];
    $placeholders = [':title', ':subject_id', ':description', ':due_date', ':max_score', ':teacher_id'];
    $params = [
        ':title' => $title,
        ':subject_id' => $subjectId,
        ':description' => $description,
        ':due_date' => date('Y-m-d H:i:s', strtotime($dueDate)),
        ':max_score' => $maxScore,
        ':teacher_id' => $user['id'],
    ];

    if (column_exists('assignments', 'subject')) {
        $columns[] = 'subject';
        $placeholders[] = ':subject';
        $params[':subject'] = $subject['name'];
    }

    $stmt = db()->prepare('INSERT INTO assignments (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
    $stmt->execute($params);

    flash('success', 'Tugas berhasil dibuat.');
    redirect_to('/index.php?page=subject&id=' . $subjectId);
}
