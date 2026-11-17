<?php
require __DIR__.'/../includes/bootstrap.php';$u=require_can('admin.accounts');v2_require();$error='';
$gaps=[15,20,30,45,60,90,120];$graces=[15,30,45,60,90,120,180];
if(is_post()){require_post();$gap=post_int('appointment_gap_minutes');$grace=post_int('overdue_grace_minutes');
 if(!in_array($gap,$gaps,true)||!in_array($grace,$graces,true)){$error='Choose one of the listed durations.';}
 else{tx(function()use($gap,$grace,$u){foreach(['appointment_gap_minutes'=>$gap,'overdue_grace_minutes'=>$grace] as $key=>$value){q('INSERT INTO clinic_setting(setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)',[$key,(string)$value,$u['user_id']]);}audit('CLINIC_SETTINGS_UPDATED','settings',null);});flash('success','Scheduling rules updated. Historical appointment times are unchanged.');redirect('admin/settings.php');}
}
$v=['appointment_gap_minutes'=>workflow_setting('appointment_gap_minutes',30),'overdue_grace_minutes'=>workflow_setting('overdue_grace_minutes',60)];
page_start('Clinic settings',['nav'=>'settings','subtitle'=>'Practical rules for reception and scheduling.']);if($error):?><div class="alert alert-error"><?= e($error) ?></div><?php endif;?>
<form method="post" class="panel narrow"><?= csrf_field() ?><h2>Appointment rules</h2>
<?= f_select('appointment_gap_minutes','Minimum gap between start times',array_combine($gaps,array_map(fn($n)=>$n.' minutes',$gaps)),$v,[]) ?>
<p class="hint">Doctor conflicts block booking. Patient conflicts show a warning and require an explicit override. Existing stored appointment times are retained.</p>
<?= f_select('overdue_grace_minutes','Overdue warning after',array_combine($graces,array_map(fn($n)=>$n.' minutes',$graces)),$v,[]) ?>
<p class="hint">Checked-in patients, bookings with a visit, cancellations and no-shows are excluded.</p><button class="btn btn-primary">Save clinic rules</button></form>
<section class="panel narrow"><h2>Local workspace</h2><p>Timezone: <?= e(cfg('timezone')) ?></p><p>Version: V2 Core</p><p class="muted">Billing, payment collection, inventory and packages are not included in this milestone.</p></section><?php page_end();
