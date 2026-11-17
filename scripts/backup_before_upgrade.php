<?php
// Required by the CLI upgrader only. Store outside htdocs, never in the application.
function backup_before_upgrade(): string {
 $dir=dirname(APP_ROOT,2).'/clinic_backups';
 if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create backup directory: '.$dir);
 $path=$dir.'/before_v2_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.sql';
 $h=fopen($path,'xb'); if(!$h) throw new RuntimeException('Cannot write database backup.');
 $write=function($s)use($h){if(fwrite($h,$s)!==strlen($s))throw new RuntimeException('Database backup write failed.');};
 try {
  db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); db()->beginTransaction();
  $write("-- Before V2 Core upgrade. Import into an EMPTY database.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
  foreach(q_all('SHOW FULL TABLES') as $row){
   $vals=array_values($row); if($vals[1]!=='BASE TABLE') continue;
   $t='`'.str_replace('`','``',$vals[0]).'`';
   $create=q_one('SHOW CREATE TABLE '.$t); $write(array_values($create)[1].";\n");
   foreach(q_all('SELECT * FROM '.$t) as $data){
    $values=array_map(fn($v)=>$v===null?'NULL':db()->quote((string)$v),array_values($data));
    $write('INSERT INTO '.$t.' VALUES ('.implode(',',$values).");\n");
   }
  }
  $write("SET FOREIGN_KEY_CHECKS=1;\n"); db()->commit(); fclose($h); return $path;
 } catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fclose($h);throw $e;}
}
