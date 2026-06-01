<?php
declare(strict_types=1);

const APP_NAME = 'TugasKita';
const SESSION_TIMEOUT = 1800;
const MAX_UPLOAD_SIZE = 10485760;
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
define('BASE_URL', $basePath === '' ? '' : $basePath);
define('APP_ROOT', dirname(__DIR__));
define('DB_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'tugaskita.sqlite');
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'unggahan');

session_name('tugaskita_session');
session_start();

if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_name('tugaskita_session');
    session_start();
    $_SESSION['flash'] = ['type' => 'info', 'message' => 'Sesi habis. Silakan masuk kembali.'];
}

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        exit('Ekstensi pdo_sqlite belum aktif di PHP/XAMPP. Aktifkan extension=pdo_sqlite di php.ini lalu restart Apache.');
    }

    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function initialize_database(): void
{
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $schema = file_get_contents(APP_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    db()->exec($schema);

    migrate_database();
    seed_default_subjects();
}

function column_exists(string $table, string $column): bool
{
    $stmt = db()->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function migrate_database(): void
{
    if (!column_exists('users', 'student_number')) {
        db()->exec('ALTER TABLE users ADD COLUMN student_number TEXT');
    }

    if (!column_exists('users', 'teacher_number')) {
        db()->exec('ALTER TABLE users ADD COLUMN teacher_number TEXT');
    }

    if (!column_exists('users', 'teacher_is_admin')) {
        db()->exec('ALTER TABLE users ADD COLUMN teacher_is_admin INTEGER NOT NULL DEFAULT 0');
    }

    if (!column_exists('users', 'teacher_grade_level')) {
        db()->exec('ALTER TABLE users ADD COLUMN teacher_grade_level TEXT');
    }

    if (!column_exists('users', 'teacher_grade_levels')) {
        db()->exec('ALTER TABLE users ADD COLUMN teacher_grade_levels TEXT');
    }

    if (!column_exists('users', 'teacher_subject_name')) {
        db()->exec('ALTER TABLE users ADD COLUMN teacher_subject_name TEXT');
    }

    if (!column_exists('users', 'teacher_subject_names')) {
        db()->exec('ALTER TABLE users ADD COLUMN teacher_subject_names TEXT');
    }

    db()->exec("UPDATE users SET teacher_is_admin = 1, teacher_grade_level = NULL, teacher_grade_levels = NULL, teacher_subject_name = NULL, teacher_subject_names = NULL WHERE role = 'guru' AND username = 'guru'");
    db()->exec("UPDATE users SET teacher_grade_level = 'XI' WHERE role = 'guru' AND teacher_is_admin = 0 AND (teacher_grade_level IS NULL OR teacher_grade_level = '')");
    db()->exec("UPDATE users SET teacher_grade_levels = '[\"' || teacher_grade_level || '\"]' WHERE role = 'guru' AND teacher_is_admin = 0 AND teacher_grade_level IS NOT NULL AND teacher_grade_level != '' AND (teacher_grade_levels IS NULL OR teacher_grade_levels = '')");
    db()->exec("
        UPDATE users
        SET teacher_subject_name = COALESCE(
            (SELECT subjects.name FROM subjects WHERE subjects.teacher_name = users.name LIMIT 1),
            'Bahasa Indonesia'
        )
        WHERE role = 'guru'
          AND teacher_is_admin = 0
          AND (teacher_subject_name IS NULL OR teacher_subject_name = '')
    ");
    db()->exec("UPDATE users SET teacher_subject_names = '[\"' || teacher_subject_name || '\"]' WHERE role = 'guru' AND teacher_is_admin = 0 AND teacher_subject_name IS NOT NULL AND teacher_subject_name != '' AND (teacher_subject_names IS NULL OR teacher_subject_names = '')");

    if (!column_exists('subjects', 'grade_level')) {
        db()->exec("ALTER TABLE subjects ADD COLUMN grade_level TEXT NOT NULL DEFAULT 'X'");
    }

    if (!column_exists('materials', 'original_filename')) {
        db()->exec('ALTER TABLE materials ADD COLUMN original_filename TEXT');
    }

    if (!column_exists('materials', 'stored_filename')) {
        db()->exec('ALTER TABLE materials ADD COLUMN stored_filename TEXT');
    }

    if (!column_exists('materials', 'file_size')) {
        db()->exec('ALTER TABLE materials ADD COLUMN file_size INTEGER');
    }

    if (!column_exists('assignments', 'subject_id')) {
        db()->exec('ALTER TABLE assignments ADD COLUMN subject_id INTEGER');
    }
}

function seed_default_subjects(): void
{
    $count = (int) db()->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
    if ($count > 0) {
        map_legacy_assignments_to_subjects();
        return;
    }

    $teacherId = (int) (db()->query("SELECT id FROM users WHERE role = 'guru' LIMIT 1")->fetchColumn() ?: 0);
    $subjects = [
        ['X', 'Bahasa Indonesia', 'Rani Pratiwi, S.Pd.', 'Senin, 08.00-09.30', 'Literasi, menulis, dan apresiasi teks.'],
        ['X', 'Matematika', 'Dimas Prakoso, S.Pd.', 'Senin, 10.00-11.30', 'Bilangan, aljabar, geometri, dan data.'],
        ['X', 'Bahasa Inggris', 'Sarah Amelia, S.Pd.', 'Selasa, 08.00-09.30', 'Reading, speaking, writing, dan grammar.'],
        ['X', 'Pendidikan Pancasila', 'Sinta Rahmawati, S.Pd.', 'Selasa, 10.00-11.30', 'Pancasila, konstitusi, dan kewargaan.'],
        ['X', 'Sejarah', 'Hendra Wijaya, S.Pd.', 'Rabu, 08.00-09.30', 'Sejarah Indonesia dan dunia.'],
        ['X', 'Informatika', 'Reno Saputra, S.Kom.', 'Rabu, 10.00-11.30', 'Data, algoritma, dan literasi digital.'],
        ['XI', 'Bahasa Indonesia', 'Rani Pratiwi, S.Pd.', 'Senin, 08.00-09.30', 'Literasi, menulis, dan apresiasi teks.'],
        ['XI', 'Matematika', 'Dimas Prakoso, S.Pd.', 'Senin, 10.00-11.30', 'Fungsi, statistika, peluang, dan pemodelan.'],
        ['XI', 'Bahasa Inggris', 'Sarah Amelia, S.Pd.', 'Selasa, 08.00-09.30', 'Reading, speaking, writing, dan grammar.'],
        ['XI', 'Pendidikan Pancasila', 'Sinta Rahmawati, S.Pd.', 'Selasa, 10.00-11.30', 'Pancasila, konstitusi, dan kewargaan.'],
        ['XI', 'Sejarah', 'Hendra Wijaya, S.Pd.', 'Rabu, 08.00-09.30', 'Sejarah Indonesia dan dunia.'],
        ['XI', 'Informatika', 'Reno Saputra, S.Kom.', 'Rabu, 10.00-11.30', 'Data, algoritma, dan literasi digital.'],
        ['XII', 'Bahasa Indonesia', 'Rani Pratiwi, S.Pd.', 'Senin, 08.00-09.30', 'Literasi, menulis, dan apresiasi teks.'],
        ['XII', 'Matematika', 'Dimas Prakoso, S.Pd.', 'Senin, 10.00-11.30', 'Statistika lanjutan, fungsi, dan pemodelan.'],
        ['XII', 'Bahasa Inggris', 'Sarah Amelia, S.Pd.', 'Selasa, 08.00-09.30', 'Reading, speaking, writing, dan grammar.'],
        ['XII', 'Pendidikan Pancasila', 'Sinta Rahmawati, S.Pd.', 'Selasa, 10.00-11.30', 'Pancasila, konstitusi, dan kewargaan.'],
        ['XII', 'Sejarah', 'Hendra Wijaya, S.Pd.', 'Rabu, 08.00-09.30', 'Sejarah Indonesia dan dunia.'],
        ['XII', 'Informatika', 'Reno Saputra, S.Kom.', 'Rabu, 10.00-11.30', 'Data, algoritma, dan literasi digital.'],
    ];

    $stmt = db()->prepare('
        INSERT INTO subjects (grade_level, name, teacher_name, schedule, description, created_by)
        VALUES (:grade_level, :name, :teacher_name, :schedule, :description, :created_by)
    ');

    foreach ($subjects as $subject) {
        $stmt->execute([
            ':grade_level' => $subject[0],
            ':name' => $subject[1],
            ':teacher_name' => $subject[2],
            ':schedule' => $subject[3],
            ':description' => $subject[4],
            ':created_by' => $teacherId ?: null,
        ]);
    }

    map_legacy_assignments_to_subjects();
}

function subject_id_by_name(string $name, ?string $gradeLevel = null): ?int
{
    if ($gradeLevel) {
        $stmt = db()->prepare('SELECT id FROM subjects WHERE name = :name AND grade_level = :grade_level LIMIT 1');
        $stmt->execute([':name' => $name, ':grade_level' => $gradeLevel]);
    } else {
        $stmt = db()->prepare('SELECT id FROM subjects WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
    }
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function map_legacy_assignments_to_subjects(): void
{
    if (!column_exists('assignments', 'subject_id')) {
        return;
    }

    $fallbackId = (int) (db()->query('SELECT id FROM subjects ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($fallbackId === 0) {
        return;
    }

    if (column_exists('assignments', 'subject')) {
        $rows = db()->query('SELECT id, subject FROM assignments WHERE subject_id IS NULL OR subject_id = 0')->fetchAll();
        $stmt = db()->prepare('UPDATE assignments SET subject_id = :subject_id WHERE id = :id');
        foreach ($rows as $row) {
            $subjectId = subject_id_by_name((string) $row['subject']) ?: $fallbackId;
            $stmt->execute([':subject_id' => $subjectId, ':id' => $row['id']]);
        }
    } else {
        db()->prepare('UPDATE assignments SET subject_id = :subject_id WHERE subject_id IS NULL OR subject_id = 0')->execute([':subject_id' => $fallbackId]);
    }

    normalize_assignments_table();
}

function normalize_assignments_table(): void
{
    if (!column_exists('assignments', 'subject')) {
        return;
    }

    $missingSubjectId = (int) db()->query('SELECT COUNT(*) FROM assignments WHERE subject_id IS NULL OR subject_id = 0')->fetchColumn();
    if ($missingSubjectId > 0) {
        return;
    }

    db()->exec('PRAGMA foreign_keys = OFF');
    db()->exec('DROP TABLE IF EXISTS assignments_new');
    db()->exec('
        CREATE TABLE assignments_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            description TEXT NOT NULL,
            due_date TEXT NOT NULL,
            max_score INTEGER NOT NULL DEFAULT 100,
            teacher_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ');
    db()->exec('
        INSERT INTO assignments_new (id, title, subject_id, description, due_date, max_score, teacher_id, created_at)
        SELECT id, title, subject_id, description, due_date, max_score, teacher_id, created_at
        FROM assignments
    ');
    db()->exec('DROP TABLE assignments');
    db()->exec('ALTER TABLE assignments_new RENAME TO assignments');
    db()->exec('PRAGMA foreign_keys = ON');
}
