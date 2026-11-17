<?php
declare(strict_types=1);
/**
 * Audit content rule: only entity ids, changed FIELD NAMES, status from/to, version numbers and short codes.
 * Never free text, clinical text, personal-data values or secrets. Only allow-listed keys are stored, and each
 * value must match a strict pattern; anything else is replaced by "(redacted)".
 */
const AUDIT_META_KEYS = ['fields', 'status_from', 'status_to', 'version_no', 'role', 'roles', 'reason_code', 'count', 'target_user', 'mode', 'patient', 'visit', 'appointment', 'exam', 'request'];
function audit(string $action, ?string $entityType = null, ?int $entityId = null, string $outcome = 'SUCCESS', array $meta = [], ?int $actorId = null): void {
    try {
        $u = function_exists('current_user') ? current_user() : null;
        $actor = $actorId ?? ($u['user_id'] ?? null);
        $role = $u ? primary_role($u) : null;
        $parts = [];
        foreach ($meta as $k => $v) {
            if (!in_array($k, AUDIT_META_KEYS, true)) { continue; }
            $s = is_array($v) ? implode(',', array_map('strval', $v)) : (string) $v;
            if (!preg_match('/^[A-Za-z0-9_.:,\- ]{0,120}$/', $s)) { $s = '(redacted)'; }
            $parts[] = $k . '=' . $s;
        }
        q('INSERT INTO audit_event (actor_user_id, actor_role, action_code, entity_type, entity_id, outcome, request_id, ip_address, summary)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$actor, $role, $action, $entityType, $entityId, $outcome, request_id(), client_ip() ?: null, $parts ? substr(implode('; ', $parts), 0, 500) : null]);
    } catch (Throwable $t) {
        if ($outcome === 'SUCCESS') { throw $t; }   // a success event that cannot be recorded must roll the change back
        error_log('audit failure: ' . $t->getMessage());
    }
}
