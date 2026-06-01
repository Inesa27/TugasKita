<?php
declare(strict_types=1);

function class_options_html(?string $selectedClass = null): string
{
    $levels = ['X', 'XI', 'XII'];
    $options = '<option value="">Pilih kelas</option>';
    foreach ($levels as $level) {
        for ($number = 1; $number <= 8; $number++) {
            $value = $level . ' ' . $number;
            $selected = $selectedClass === $value ? ' selected' : '';
            $options .= '<option value="' . e($value) . '"' . $selected . '>' . e($value) . '</option>';
        }
    }
    return $options;
}

function grade_level_label(?string $gradeLevel): string
{
    return [
        'X' => 'Kelas 10',
        'XI' => 'Kelas 11',
        'XII' => 'Kelas 12',
    ][$gradeLevel ?? ''] ?? 'Kelas belum diatur';
}

function grade_level_options_html(?string $selectedGrade = null): string
{
    $options = '<option value="">Pilih kelas pengampu</option>';
    $options .= '<option value="ADMIN"' . ($selectedGrade === 'ADMIN' ? ' selected' : '') . '>Guru Admin</option>';
    foreach (['X', 'XI', 'XII'] as $gradeLevel) {
        $selected = $selectedGrade === $gradeLevel ? ' selected' : '';
        $options .= '<option value="' . e($gradeLevel) . '"' . $selected . '>' . e(grade_level_label($gradeLevel)) . '</option>';
    }
    return $options;
}

function grade_level_checks_html(array $selectedGrades = []): string
{
    $html = '';
    foreach (['X', 'XI', 'XII'] as $gradeLevel) {
        $checked = in_array($gradeLevel, $selectedGrades, true) ? ' checked' : '';
        $html .= '<label class="check-option"><input type="checkbox" name="teacher_grade_levels[]" value="' . e($gradeLevel) . '"' . $checked . '> <span>' . e(grade_level_label($gradeLevel)) . '</span></label>';
    }
    return $html;
}

function subject_name_options_html(?string $selectedSubject = null): string
{
    $options = '<option value="">Pilih mata pelajaran</option>';
    $stmt = db()->query('SELECT DISTINCT name FROM subjects ORDER BY name');
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $selected = $selectedSubject === $name ? ' selected' : '';
        $options .= '<option value="' . e($name) . '"' . $selected . '>' . e($name) . '</option>';
    }
    return $options;
}

function subject_name_checks_html(array $selectedSubjects = []): string
{
    $html = '';
    $stmt = db()->query('SELECT DISTINCT name FROM subjects ORDER BY name');
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) $row['name'];
        $checked = in_array(strtolower($name), array_map('strtolower', $selectedSubjects), true) ? ' checked' : '';
        $html .= '<label class="check-option"><input type="checkbox" name="teacher_subject_names[]" value="' . e($name) . '"' . $checked . '> <span>' . e($name) . '</span></label>';
    }
    return $html;
}

function class_level_from_name(?string $className): ?string
{
    $className = trim((string) $className);
    if (preg_match('/^(XII|XI|X)\b/', $className, $matches)) {
        return $matches[1];
    }
    return null;
}

function normalize_list_input(mixed $values): array
{
    if (!is_array($values)) {
        $values = [$values];
    }
    $clean = [];
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '' && !in_array($value, $clean, true)) {
            $clean[] = $value;
        }
    }
    return $clean;
}

function clean_grade_levels(mixed $values): array
{
    return array_values(array_filter(normalize_list_input($values), fn(string $value): bool => in_array($value, ['X', 'XI', 'XII'], true)));
}

function clean_subject_names(mixed $values): array
{
    return normalize_list_input($values);
}

function encode_list(array $values): ?string
{
    $values = normalize_list_input($values);
    return $values ? json_encode(array_values($values), JSON_UNESCAPED_UNICODE) : null;
}

function decode_list(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return normalize_list_input($decoded);
    }
    return normalize_list_input(preg_split('/[|,]/', $value) ?: []);
}

function is_teacher_admin(array $user): bool
{
    return $user['role'] === 'guru' && (int) ($user['teacher_is_admin'] ?? 0) === 1;
}

function teacher_grade_levels(array $user): array
{
    $levels = clean_grade_levels(decode_list($user['teacher_grade_levels'] ?? null));
    if ($levels) {
        return $levels;
    }
    $gradeLevel = $user['teacher_grade_level'] ?? null;
    return in_array($gradeLevel, ['X', 'XI', 'XII'], true) ? [$gradeLevel] : [];
}

function teacher_grade_level(array $user): ?string
{
    return teacher_grade_levels($user)[0] ?? null;
}

function teacher_subject_names(array $user): array
{
    $subjects = clean_subject_names(decode_list($user['teacher_subject_names'] ?? null));
    if ($subjects) {
        return $subjects;
    }
    $subjectName = trim((string) ($user['teacher_subject_name'] ?? ''));
    return $subjectName !== '' ? [$subjectName] : [];
}

function teacher_subject_name(array $user): ?string
{
    return teacher_subject_names($user)[0] ?? null;
}

function grade_levels_label(array $gradeLevels): string
{
    if (!$gradeLevels) {
        return 'Jenjang belum diatur';
    }
    return implode(', ', array_map(fn(string $gradeLevel): string => grade_level_label($gradeLevel), $gradeLevels));
}

function list_label(array $values, string $emptyLabel): string
{
    return $values ? implode(', ', $values) : $emptyLabel;
}

function teacher_scope_label(array $user): string
{
    if (is_teacher_admin($user)) {
        return 'Guru Admin';
    }
    return grade_levels_label(teacher_grade_levels($user)) . ' - ' . list_label(teacher_subject_names($user), 'Mapel belum diatur');
}

function user_grade_level(array $user): ?string
{
    if ($user['role'] === 'guru') {
        return is_teacher_admin($user) ? null : teacher_grade_level($user);
    }
    return class_level_from_name($user['class_name'] ?? null);
}

function same_subject_name(?string $left, ?string $right): bool
{
    return strtolower(trim((string) $left)) === strtolower(trim((string) $right));
}

function can_manage_subject(array $user, array $row): bool
{
    if ($user['role'] !== 'guru') {
        return false;
    }
    if (is_teacher_admin($user)) {
        return true;
    }
    $gradeLevels = teacher_grade_levels($user);
    $subjectNames = teacher_subject_names($user);
    return in_array((string) ($row['grade_level'] ?? ''), $gradeLevels, true)
        && in_subject_list($row['name'] ?? null, $subjectNames);
}

function can_access_subject(array $user, array $row): bool
{
    if ($user['role'] === 'guru') {
        return can_manage_subject($user, $row);
    }
    $gradeLevel = class_level_from_name($user['class_name'] ?? null);
    return $gradeLevel === null || ($row['grade_level'] ?? null) === $gradeLevel;
}

function in_subject_list(?string $subjectName, array $subjects): bool
{
    foreach ($subjects as $subject) {
        if (same_subject_name($subjectName, $subject)) {
            return true;
        }
    }
    return false;
}

function add_in_condition(string $column, array $values, string $prefix, array &$params): string
{
    $values = normalize_list_input($values);
    if (!$values) {
        return ' AND 1 = 0';
    }
    $placeholders = [];
    foreach ($values as $index => $value) {
        $placeholder = ':' . $prefix . '_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $value;
    }
    return ' AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')';
}

function teacher_scope_condition(array $user, string $alias, string $prefix, array &$params): string
{
    if (is_teacher_admin($user)) {
        return '';
    }
    return add_in_condition($alias . '.grade_level', teacher_grade_levels($user), $prefix . '_grade', $params)
        . add_in_condition($alias . '.name', teacher_subject_names($user), $prefix . '_subject', $params);
}

function material_excerpt(string $content, int $limit = 180): string
{
    $content = trim((string) preg_replace('/\s+/', ' ', $content));
    if ($content === '') {
        return 'Materi ini berupa file lampiran.';
    }
    if (strlen($content) <= $limit) {
        return $content;
    }
    return rtrim(substr($content, 0, $limit - 3)) . '...';
}

function feedback_text(?string $feedback): string
{
    $feedback = trim((string) $feedback);
    return $feedback !== '' ? $feedback : '-';
}
