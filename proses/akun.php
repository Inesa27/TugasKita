<?php
declare(strict_types=1);

if ($action === 'logout') {
    session_unset();
    session_destroy();
    session_name('tugaskita_session');
    session_start();
    flash('success', 'Anda sudah keluar.');
    redirect_to('/index.php?page=login');
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('danger', 'Username atau password tidak sesuai.');
        redirect_to('/index.php?page=login');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    redirect_to('/index.php?page=dashboard');
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $role = in_array($_POST['role'] ?? 'siswa', ['guru', 'siswa'], true) ? $_POST['role'] : 'siswa';
    $className = $role === 'siswa' ? trim($_POST['class_name'] ?? '') : null;
    $studentNumber = $role === 'siswa' ? trim($_POST['student_number'] ?? '') : null;
    $teacherNumber = $role === 'guru' ? trim($_POST['teacher_number'] ?? '') : null;
    $teacherAccess = $role === 'guru' ? ($_POST['teacher_access'] ?? '') : '';
    $teacherIsAdmin = $role === 'guru' && $teacherAccess === 'ADMIN' ? 1 : 0;
    $teacherGradeLevels = $role === 'guru' && $teacherIsAdmin === 0 ? clean_grade_levels($_POST['teacher_grade_levels'] ?? []) : [];
    $teacherSubjectNames = $role === 'guru' && $teacherIsAdmin === 0 ? clean_subject_names($_POST['teacher_subject_names'] ?? []) : [];
    $teacherGradeLevel = $teacherGradeLevels[0] ?? null;
    $teacherSubjectName = $teacherSubjectNames[0] ?? null;

    if ($name === '') {
        flash('danger', 'Nama lengkap wajib diisi.');
        redirect_to('/index.php?page=register');
    }

    if ($role === 'siswa' && ($className === '' || $studentNumber === '')) {
        flash('danger', 'Kelas dan nomor induk siswa wajib diisi.');
        redirect_to('/index.php?page=register');
    }

    if ($role === 'guru' && ($teacherNumber === '' || ($teacherIsAdmin === 0 && (!$teacherGradeLevels || !$teacherSubjectNames)))) {
        flash('danger', 'NIP, minimal satu jenjang, dan minimal satu mata pelajaran pengampu wajib diisi.');
        redirect_to('/index.php?page=register');
    }

    if ($username === '' || strlen($username) < 4) {
        flash('danger', 'Username minimal 4 karakter.');
        redirect_to('/index.php?page=register');
    }

    if (strlen($password) < 6) {
        flash('danger', 'Password minimal 6 karakter.');
        redirect_to('/index.php?page=register');
    }

    if ($password !== $confirmPassword) {
        flash('danger', 'Konfirmasi password belum sama.');
        redirect_to('/index.php?page=register');
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('danger', 'Username sudah dipakai. Pilih username lain.');
        redirect_to('/index.php?page=register');
    }

    if ($studentNumber) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE student_number = :student_number');
        $stmt->execute([':student_number' => $studentNumber]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('danger', 'Nomor induk siswa sudah terdaftar.');
            redirect_to('/index.php?page=register');
        }
    }

    if ($teacherNumber) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE teacher_number = :teacher_number');
        $stmt->execute([':teacher_number' => $teacherNumber]);
        if ((int) $stmt->fetchColumn() > 0) {
            flash('danger', 'NIP sudah terdaftar.');
            redirect_to('/index.php?page=register');
        }
    }

    $stmt = db()->prepare('
        INSERT INTO users (name, username, password_hash, role, class_name, student_number, teacher_number, teacher_is_admin, teacher_grade_level, teacher_grade_levels, teacher_subject_name, teacher_subject_names)
        VALUES (:name, :username, :password_hash, :role, :class_name, :student_number, :teacher_number, :teacher_is_admin, :teacher_grade_level, :teacher_grade_levels, :teacher_subject_name, :teacher_subject_names)
    ');
    $stmt->execute([
        ':name' => $name,
        ':username' => $username,
        ':password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]),
        ':role' => $role,
        ':class_name' => $className,
        ':student_number' => $studentNumber,
        ':teacher_number' => $teacherNumber,
        ':teacher_is_admin' => $teacherIsAdmin,
        ':teacher_grade_level' => $teacherGradeLevel,
        ':teacher_grade_levels' => encode_list($teacherGradeLevels),
        ':teacher_subject_name' => $teacherSubjectName,
        ':teacher_subject_names' => encode_list($teacherSubjectNames),
    ]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) db()->lastInsertId();
    $_SESSION['last_activity'] = time();
    flash('success', 'Akun berhasil dibuat.');
    redirect_to('/index.php?page=dashboard');
}
