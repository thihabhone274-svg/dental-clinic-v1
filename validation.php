<?php
declare(strict_types=1);
// Server-side validators. Each returns the cleaned value (or null) and records a message in $err[$key].
function v_text(array $in, string $key, array &$err, string $label, array $o = []): ?string {
    $raw = $in[$key] ?? '';
    $s = is_array($raw) ? '' : (string) $raw;
    $s = !empty($o['multiline']) ? trim(str_replace("\r\n", "\n", $s)) : trim((string) preg_replace('/\s+/u', ' ', $s));
    if ($s === '') {
        if (!empty($o['required'])) { $err[$key] = "$label is required."; }
        return null;
    }
    if (isset($o['max']) && mb_strlen($s) > $o['max']) { $err[$key] = "$label must be at most {$o['max']} characters."; return null; }
    if (isset($o['min']) && mb_strlen($s) < $o['min']) { $err[$key] = "$label must be at least {$o['min']} characters."; return null; }
    if (isset($o['pattern']) && !preg_match($o['pattern'], $s)) { $err[$key] = $o['pattern_msg'] ?? "$label has an invalid format."; return null; }
    return $s;
}
function v_enum(array $in, string $key, array &$err, string $label, array $allowed, array $o = []): ?string {
    $s = trim((string) (is_array($in[$key] ?? '') ? '' : ($in[$key] ?? '')));
    if ($s === '') { if (!empty($o['required'])) { $err[$key] = "$label is required."; } return null; }
    if (!in_array($s, $allowed, true)) { $err[$key] = "Choose a valid option for $label."; return null; }
    return $s;
}
function v_date(array $in, string $key, array &$err, string $label, array $o = []): ?string {
    $s = trim((string) ($in[$key] ?? ''));
    if ($s === '') { if (!empty($o['required'])) { $err[$key] = "$label is required."; } return null; }
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) { $err[$key] = "$label must be a valid date (YYYY-MM-DD)."; return null; }
    if (isset($o['max']) && $s > $o['max']) { $err[$key] = "$label cannot be after {$o['max']}."; return null; }
    if (isset($o['min']) && $s < $o['min']) { $err[$key] = "$label cannot be before {$o['min']}."; return null; }
    return $s;
}
function v_datetime(array $in, string $key, array &$err, string $label, array $o = []): ?string {
    $s = trim((string) ($in[$key] ?? ''));
    if ($s === '') { if (!empty($o['required'])) { $err[$key] = "$label is required."; } return null; }
    foreach (['!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s'] as $f) {
        $d = DateTime::createFromFormat($f, $s);
        if ($d) { return $d->format('Y-m-d H:i:00'); }
    }
    $err[$key] = "$label must be a valid date and time.";
    return null;
}
function v_decimal(array $in, string $key, array &$err, string $label, array $o = []): ?string {
    $s = trim((string) ($in[$key] ?? ''));
    if ($s === '') { if (!empty($o['required'])) { $err[$key] = "$label is required."; } return null; }
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $s)) { $err[$key] = "$label must be a number (up to 2 decimals)."; return null; }
    if (isset($o['max']) && (float) $s > $o['max']) { $err[$key] = "$label must be at most {$o['max']}."; return null; }
    return $s;
}
function v_phone(array $in, string $key, array &$err, string $label): ?string {
    return v_text($in, $key, $err, $label, ['max' => 40, 'pattern' => '/^[0-9+\-() ]+$/', 'pattern_msg' => "$label may contain digits, spaces and + - ( ) only."]);
}
function normalize_name(string $s): string { return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s))); }
function digits_only(?string $s): ?string { $d = preg_replace('/\D/', '', (string) $s); return $d === '' ? null : substr($d, 0, 40); }
