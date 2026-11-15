<?php
require __DIR__ . '/../includes/bootstrap.php';
require_can('admin.audit');
$f = ['from' => (string) ($_GET['from'] ?? ''), 'to' => (string) ($_GET['to'] ?? ''), 'action' => (string) ($_GET['action'] ?? ''), 'outcome' => (string) ($_GET['outcome'] ?? ''), 'actor' => get_int('actor')];
$w = ['1=1']; $p = [];
$e = [];
if ($f['from'] !== '' && v_date($f, 'from', $e, 'From')) { $w[] = 'a.occurred_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
if ($f['to'] !== '' && v_date($f, 'to', $e, 'To')) { $w[] = 'a.occurred_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
if ($f['action'] !== '') { $w[] = 'a.action_code = ?'; $p[] = $f['action']; }
if (in_array($f['outcome'], ['SUCCESS', 'DENIED', 'FAILED'], true)) { $w[] = 'a.outcome = ?'; $p[] = $f['outcome']; }
if ($f['actor'] > 0) { $w[] = 'a.actor_user_id = ?'; $p[] = $f['actor']; }
$where = implode(' AND ', $w); $per = 50;
$total = (int) q_val("SELECT COUNT(*) FROM audit_event a WHERE $where", $p);
[$page, $pages, $off] = paginate($total, $per);
$rows = q_all("SELECT a.*, u.display_name FROM audit_event a LEFT JOIN user_account u ON u.user_id = a.actor_user_id WHERE $where ORDER BY a.event_id DESC LIMIT $per OFFSET " . (int) $off, $p);
$actions = array_column(q_all('SELECT DISTINCT action_code FROM audit_event ORDER BY action_code'), 'action_code');
$users = q_all('SELECT user_id, display_name FROM user_account ORDER BY display_name');
page_start('Audit log', ['nav' => 'audit', 'subtitle' => 'Read-only. Entries hold ids, field names, status changes and codes, never clinical text. ' . $total . ' matching event(s).']);
?>
<form method="get" class="filters">
  <?= f_text('from', 'From', $f, $e, ['type' => 'date']) ?><?= f_text('to', 'To', $f, $e, ['type' => 'date']) ?>
  <?= f_select('action', 'Action', array_combine($actions, $actions) ?: [], $f, [], ['placeholder' => 'All actions']) ?>
  <?= f_select('actor', 'User', array_column($users, 'display_name', 'user_id'), $f, [], ['placeholder' => 'All users']) ?>
  <?= f_select('outcome', 'Outcome', ['SUCCESS' => 'Success', 'DENIED' => 'Denied', 'FAILED' => 'Failed'], $f, [], ['placeholder' => 'All']) ?>
  <div class="field"><label>&nbsp;</label><button class="btn btn-primary">Filter</button> <a class="btn btn-secondary" href="?">Clear</a></div>
</form>
<?php if (!$rows): ?><div class="empty-state"><h2>No audit events match</h2><p>Try widening the filters.</p></div><?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Entity</th><th>Outcome</th><th>Details</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="nowrap"><?= e(fmt_dt($r['occurred_at'])) ?></td><td><?= e($r['display_name'] ?? 'System / not signed in') ?></td><td><?= e($r['actor_role'] ?? '') ?></td><td><code><?= e($r['action_code']) ?></code></td>
<td><?= e($r['entity_type'] ?? '') ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></td><td><?= status_badge($r['outcome']) ?></td><td class="muted"><?= e($r['summary'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($page, $pages, array_filter($f)) ?><?php endif; ?>
<?php page_end();
