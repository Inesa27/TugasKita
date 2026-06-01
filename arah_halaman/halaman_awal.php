<?php
declare(strict_types=1);

if ($page === '' || $page === 'home') {
    $page = current_user() ? 'dashboard' : 'login';
}

if ($page === 'login') {
    $content = '
    <main class="login-screen">
        <section class="login-card">
            <div class="login-copy">
                <a class="brand login-brand" href="' . url('/index.php?page=login') . '">
                    <span class="brand-mark">TK</span>
                    <span><strong>' . APP_NAME . '</strong><small>SMA Digital</small></span>
                </a>
                <h1>Masuk ke ruang tugas</h1>
            </div>
            <form class="form login-form" method="post" action="' . url('/index.php?action=login') . '">
                ' . flash_html() . '
                <div class="form-title">
                    <span>Selamat datang</span>
                    <strong>Login akun</strong>
                </div>
                <label>Username <input name="username" autocomplete="username" required autofocus></label>
                <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button primary" type="submit">Masuk</button>
                <p class="auth-switch">Belum punya akun? <a href="' . url('/index.php?page=register') . '">Daftar akun baru</a></p>
            </form>
        </section>
    </main>';
    render_layout('Login', null, '', $content);
    exit;
}

if ($page === 'register') {
    $content = '
    <main class="login-screen">
        <section class="login-card">
            <div class="login-copy">
                <a class="brand login-brand" href="' . url('/index.php?page=login') . '">
                    <span class="brand-mark">TK</span>
                    <span><strong>' . APP_NAME . '</strong><small>SMA Digital</small></span>
                </a>
                <h1>Daftar akun baru</h1>
            </div>
            <form class="form login-form" method="post" action="' . url('/index.php?action=register') . '">
                ' . flash_html() . '
                <div class="form-title">
                    <span>Daftar akun</span>
                    <strong>Data pengguna</strong>
                </div>
                <label>Nama lengkap <input name="name" autocomplete="name" required placeholder="Isi nama lengkap..."></label>
                <label>Role
                    <select name="role" id="roleSelect" required>
                        <option value="siswa">Siswa</option>
                        <option value="guru">Guru</option>
                    </select>
                </label>
                <div class="role-fields" data-role-fields="siswa">
                    <label>Nomor induk siswa <input name="student_number" maxlength="40" placeholder="Isi nomor induk siswa..." data-role-required></label>
                    <label>Kelas
                        <select name="class_name" data-role-required>
                            ' . class_options_html() . '
                        </select>
                    </label>
                </div>
                <div class="role-fields hidden" data-role-fields="guru">
                    <label>NIP <input name="teacher_number" maxlength="40" placeholder="Isi NIP..." data-role-required></label>
                    <label>Akses guru
                        <select name="teacher_access" id="teacherAccessSelect" data-role-required>
                            <option value="">Pilih akses guru</option>
                            <option value="ADMIN">Guru Admin</option>
                            <option value="PENGAMPU">Guru Pengampu</option>
                        </select>
                    </label>
                    <div class="teacher-scope hidden" data-teacher-scope>
                        <label>Jenjang pengampu</label>
                        <div class="check-grid">' . grade_level_checks_html() . '</div>
                        <label>Mata pelajaran pengampu</label>
                        <div class="check-grid">' . subject_name_checks_html() . '</div>
                    </div>
                </div>
                <label>Username <input name="username" autocomplete="username" required minlength="4" placeholder="Isi username..."></label>
                <label>Password <input type="password" name="password" autocomplete="new-password" required minlength="6" placeholder="Minimal 6 karakter"></label>
                <label>Konfirmasi password <input type="password" name="confirm_password" autocomplete="new-password" required minlength="6"></label>
                <button class="button primary" type="submit">Daftar Akun</button>
                <p class="auth-switch">Sudah punya akun? <a href="' . url('/index.php?page=login') . '">Masuk di sini</a></p>
            </form>
            <script>
                const roleSelect = document.getElementById("roleSelect");
                const teacherAccessSelect = document.getElementById("teacherAccessSelect");
                const teacherScope = document.querySelector("[data-teacher-scope]");
                const syncTeacherScope = () => {
                    const isGuru = roleSelect.value === "guru";
                    const needsScope = isGuru && teacherAccessSelect && teacherAccessSelect.value === "PENGAMPU";
                    if (teacherScope) {
                        teacherScope.classList.toggle("hidden", !needsScope);
                        teacherScope.querySelectorAll("input").forEach((field) => {
                            field.disabled = !needsScope;
                        });
                    }
                };
                const syncRoleFields = () => {
                    document.querySelectorAll("[data-role-fields]").forEach((group) => {
                        const active = group.dataset.roleFields === roleSelect.value;
                        group.classList.toggle("hidden", !active);
                        group.querySelectorAll("input, select").forEach((field) => {
                            field.disabled = !active;
                            field.required = active && field.hasAttribute("data-role-required");
                        });
                    });
                    syncTeacherScope();
                };
                roleSelect.addEventListener("change", syncRoleFields);
                teacherAccessSelect.addEventListener("change", syncTeacherScope);
                syncRoleFields();
            </script>
        </section>
    </main>';
    render_layout('Daftar Akun', null, '', $content);
    exit;
}
