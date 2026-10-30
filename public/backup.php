<?php
require_once __DIR__ . '/../app/core/phase2.php';
require_once __DIR__ . '/../app/core/saas.php';
require_roles(['owner','admin']);
require_feature('backup');
$pdo = db();
$action = get_value('action','list');
$backupTables = ['agents','users','buses','routes','drivers','trips','bookings','parcels','expenses','cash_closings','maintenance_records','fuel_logs','notification_logs','audit_logs','company_settings','package_tiers','subscription_license'];

function sql_value($value): string {
    if ($value === null) return 'NULL';
    return db()->quote((string)$value);
}
function build_sql_backup(array $tables): string {
    $sql = "-- Bus Owner Control System backup\n-- Generated: ".date('Y-m-d H:i:s')."\nCREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `" . DB_NAME . "`;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach($tables as $table){ if(!table_exists($table)) continue; $sql .= "\n-- Data for {$table}\n"; $rows=db()->query("SELECT * FROM {$table}")->fetchAll(); foreach($rows as $row){ $cols=array_keys($row); $values=array_map('sql_value', array_values($row)); $sql .= 'INSERT INTO '.$table.' (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',$values).") ON DUPLICATE KEY UPDATE `".$cols[0]."`=`".$cols[0]."`;\n"; } }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n"; return $sql;
}
if ($action === 'download') {
    $filename='bus_owner_backup_'.date('Ymd_His').'.sql';
    $sql=build_sql_backup($backupTables);
    $size=strlen($sql);
    try{ $stmt=$pdo->prepare('INSERT INTO backup_logs (file_name,file_size,status,notes,created_by) VALUES (?,?,?,?,?)'); $stmt->execute([$filename,$size,'created','Downloaded manual SQL backup',current_user()['id']??null]); $backupId=(int)$pdo->lastInsertId(); audit_log('create','backup_logs',$backupId,'Manual SQL backup downloaded',null,audit_record_snapshot('backup_logs',$backupId)); } catch(Throwable $e) { audit_log('backup_failed','backup_logs',null,'Backup log failed: '.$e->getMessage()); }
    header('Content-Type: application/sql'); header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Content-Length: '.$size); echo $sql; exit;
}
$logs=$pdo->query('SELECT bl.*,u.name user_name FROM backup_logs bl LEFT JOIN users u ON bl.created_by=u.id ORDER BY bl.created_at DESC LIMIT 100')->fetchAll();
?>
<?php include __DIR__ . '/../app/views/header.php'; ?>
<div class="page-title"><h1>Backup System</h1><a class="btn success" href="<?= e(url('backup.php?action=download')) ?>">Download SQL Backup</a></div>
<div class="alert alert-warning">This downloads a portable SQL data backup for important business tables. Store backups outside the computer too, for example on OneDrive or external drive.</div>
<div class="card"><h2>Backup Logs</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>File</th><th>Size</th><th>Status</th><th>User</th><th>Notes</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['file_name']) ?></td><td><?= e(number_format((float)$l['file_size']/1024,2)) ?> KB</td><td><span class="badge"><?= e($l['status']) ?></span></td><td><?= e($l['user_name']??'') ?></td><td><?= e($l['notes']) ?></td></tr><?php endforeach; ?><?php if(!$logs): ?><tr><td colspan="6">No backups downloaded yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../app/views/footer.php'; ?>
