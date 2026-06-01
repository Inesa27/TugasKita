<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . $path;
}

function redirect_to(string $target): never
{
    header('Location: ' . url($target));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = in_array($flash['type'], ['success', 'danger', 'info'], true) ? $flash['type'] : 'info';
    return '<div class="notice ' . e($type) . '">' . e($flash['message']) . '</div>';
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('info', 'Silakan masuk terlebih dahulu.');
        redirect_to('/index.php?page=login');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('Akses tidak sesuai role pengguna.');
    }
    return $user;
}

function format_date(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $time = strtotime($value);
    if ($time === false) {
        return '-';
    }
    return date('j', $time) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . date('Y, H:i', $time);
}

function file_size_label(int $size): string
{
    if ($size < 1024) {
        return $size . ' B';
    }
    if ($size < 1024 * 1024) {
        return number_format($size / 1024, 1) . ' KB';
    }
    return number_format($size / 1024 / 1024, 1) . ' MB';
}

function badge(string $label, string $kind = 'neutral'): string
{
    return '<span class="badge ' . e($kind) . '">' . e($label) . '</span>';
}

function assignment_status(?array $submission, string $dueDate, ?array $grade = null): string
{
    if ($grade && isset($grade['score'])) {
        return badge('Dinilai', 'success');
    }
    if ($submission) {
        return badge((int) $submission['is_late'] === 1 ? 'Terlambat' : 'Terkumpul', (int) $submission['is_late'] === 1 ? 'warning' : 'info');
    }
    if (strtotime($dueDate) < time()) {
        return badge('Lewat deadline', 'danger');
    }
    return badge('Belum kumpul', 'neutral');
}

function table_or_empty(array $headers, array $rows, string $title, string $text): string
{
    if (!$rows) {
        $detail = $text !== '' ? '<p>' . e($text) . '</p>' : '';
        return '<div class="empty-state"><strong>' . e($title) . '</strong>' . $detail . '</div>';
    }
    $html = '<div class="table-wrap"><table><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . e($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>' . implode('', $rows) . '</tbody></table></div>';
    return $html;
}

function stat_card(string $label, mixed $value, string $accent): string
{
    return '<article class="stat ' . e($accent) . '"><span>' . e($label) . '</span><strong>' . e($value) . '</strong></article>';
}

function safe_filename(string $filename): string
{
    $name = basename($filename);
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
    $name = str_replace(' ', '_', trim((string) $name));
    return $name !== '' ? $name : 'tugas';
}

function nav_link(string $page, string $label, string $active): string
{
    $class = $active === $page ? 'active' : '';
    return '<a class="nav-link ' . $class . '" href="' . url('/index.php?page=' . $page) . '">' . e($label) . '</a>';
}

function render_layout(string $title, ?array $user, string $active, string $content): void
{
    $asset = url('/aset/style.css?v=20260525d');
    if ($user) {
        $roleLabel = $user['role'] === 'guru' ? 'Guru' : 'Siswa';
        $nav = nav_link('dashboard', 'Dashboard', $active);
        if ($user['role'] === 'guru') {
            $nav .= nav_link('subjects', 'Mata Pelajaran', $active);
            $nav .= nav_link('create-assignment', 'Buat Tugas', $active);
            $nav .= nav_link('tasks', 'Daftar Tugas', $active);
            $nav .= nav_link('grading', 'Penilaian', $active);
        } else {
            $nav .= nav_link('subjects', 'Mata Pelajaran', $active);
            $nav .= nav_link('upload', 'Upload Tugas', $active);
            $nav .= nav_link('tasks', 'Daftar Tugas', $active);
        }
        $nav .= nav_link('results', 'Hasil', $active);
        if ($user['role'] === 'siswa') {
            $userMeta = trim(($user['class_name'] ?: 'Siswa') . (!empty($user['student_number']) ? ' | ' . $user['student_number'] : ''));
        } else {
            $gradeLabels = ['X' => 'Kelas 10', 'XI' => 'Kelas 11', 'XII' => 'Kelas 12'];
            if (function_exists('teacher_scope_label')) {
                $teacherGrade = teacher_scope_label($user);
                $teacherSubject = '';
            } else {
                $teacherGrade = (int) ($user['teacher_is_admin'] ?? 0) === 1
                    ? 'Guru Admin'
                    : ($gradeLabels[$user['teacher_grade_level'] ?? ''] ?? 'Kelas pengampu belum diatur');
                $teacherSubject = !empty($user['teacher_subject_name']) ? ' | ' . $user['teacher_subject_name'] : '';
            }
            $teacherNumber = !empty($user['teacher_number']) ? 'NIP: ' . $user['teacher_number'] : 'Guru';
            $userMeta = $teacherNumber . ' | ' . $teacherGrade . $teacherSubject;
        }
        $shell = '
        <div class="app-shell">
            <aside class="sidebar">
                <a class="brand" href="' . url('/index.php?page=dashboard') . '">
                    <span class="brand-mark">TK</span>
                    <span><strong>' . APP_NAME . '</strong><small>SMA Digital</small></span>
                </a>
                <nav class="nav">' . $nav . '</nav>
                <div class="user-card">
                    <span class="role-pill">' . e($roleLabel) . '</span>
                    <strong>' . e($user['name']) . '</strong>
                    <small>' . e($userMeta) . '</small>
                    <a class="logout" href="' . url('/index.php?action=logout') . '">Keluar</a>
                </div>
            </aside>
            <main class="main">' . $content . '</main>
        </div>';
    } else {
        $shell = $content;
    }

    echo '<!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . e($title) . ' - ' . APP_NAME . '</title>
        <link rel="stylesheet" href="' . e($asset) . '">
    </head>
    <body>' . $shell . '</body>
    </html>';
}
