<?php
declare(strict_types=1);

$user = require_login();

if ($page === 'dashboard') {
    render_layout('Dashboard', $user, 'dashboard', $user['role'] === 'guru' ? teacher_dashboard($user) : student_dashboard($user));
    exit;
}

if ($page === 'subjects') {
    render_layout('Mata Pelajaran', $user, 'subjects', subjects_page($user));
    exit;
}

if ($page === 'subject') {
    render_layout('Detail Mata Pelajaran', $user, 'subjects', subject_detail_page($user));
    exit;
}

if ($page === 'material') {
    render_layout('Detail Materi', $user, 'subjects', material_detail_page($user));
    exit;
}

if ($page === 'create-assignment') {
    $user = require_role('guru');
    render_layout('Buat Tugas', $user, 'create-assignment', create_assignment_page($user));
    exit;
}

if ($page === 'tasks') {
    render_layout('Daftar Tugas', $user, 'tasks', tasks_page($user));
    exit;
}

if ($page === 'upload') {
    $user = require_role('siswa');
    render_layout('Upload Tugas', $user, 'upload', upload_page($user));
    exit;
}

if ($page === 'grading') {
    $user = require_role('guru');
    render_layout('Penilaian', $user, 'grading', grading_page($user));
    exit;
}

if ($page === 'results') {
    render_layout('Hasil', $user, 'results', results_page($user));
    exit;
}

http_response_code(404);
render_layout('Tidak ditemukan', $user, '', '<section class="panel"><div class="empty-state"><strong>Halaman tidak ditemukan</strong></div></section>');
