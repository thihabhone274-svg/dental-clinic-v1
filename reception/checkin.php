<?php
require __DIR__.'/../includes/bootstrap.php';$u=require_can('appointment.write');v2_require();
$aid=is_post()?post_int('appointment_id'):get_int('appointment_id');$a=$aid?appt_find($aid):null;
if($aid&&!$a)abort(404,'Not found','Appointment not found.');
$pid=$a?(int)$a['patient_id']:(is_post()?post_int('patient_id'):get_int('patient_id'));$p=$pid?patient_find($pid):null;
$err=[];$v=$_POST;
if(is_post()) {
    require_post();
    try { $id=arrival_checkin($pid,$aid?:null,patient_scalar($_POST,'origin'),post_int('doctor_id')?:null,patient_scalar($_POST,'note'),!empty($_POST['other_date']),(int)$u['user_id']);flash('success','Patient checked in. The doctor can now start the visit.');redirect('reception/index.php'); }
    catch(DomainException $e){$err['checkin']=$e->getMessage();}
}
page_start('Check in patient',['nav'=>'reception','subtitle'=>'Record arrival. The doctor chooses the clinical workflow.','crumbs'=>[['Reception','reception/index.php']]]);
if(!$p){
    $query=patient_scalar($_GET,'q');[$rows]=patient_search(['q'=>$query,'status'=>'active'],20,0);
    ?><section class="panel"><form class="filters" method="get"><?= f_text('q','Find a patient',['q'=>$query],[],['placeholder'=>'Name, phone, NRC or registration number']) ?><button class="btn btn-primary">Search patients</button><a class="btn btn-secondary" href="<?= e(url('patients/create.php')) ?>">Register new patient</a></form>
    <div class="table-wrap"><table class="table"><thead><tr><th>Patient</th><th>Registration</th><th>Telephone</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['patient_code']) ?></td><td><?= e($r['phone']) ?></td><td><a class="btn btn-secondary btn-sm" href="?patient_id=<?= (int)$r['patient_id'] ?>">Select patient</a></td></tr><?php endforeach;?></tbody></table></div></section><?php page_end();exit;
}
if($err):?><div class="alert alert-error"><?= e($err['checkin']) ?></div><?php endif;?>
<div class="patient-head"><div class="avatar"><?= e(mb_substr($p['full_name'],0,1)) ?></div><div><h2><?= e($p['full_name']) ?></h2><span class="muted"><?= e($p['patient_code']) ?></span></div></div>
<?php if(!$a):$bookings=q_all("SELECT a.*,d.full_name AS doctor_name FROM appointment a JOIN doctor d ON d.doctor_id=a.doctor_id WHERE a.patient_id=? AND a.status IN ('SCHEDULED','CONFIRMED') AND NOT EXISTS(SELECT 1 FROM clinical_visit v WHERE v.appointment_id=a.appointment_id) ORDER BY a.start_at LIMIT 10",[$pid]);if($bookings):?>
<section class="panel"><h2>Existing appointments</h2><p class="muted">Use the booking if this arrival is for an appointment.</p><?php foreach($bookings as $b):?><p><a class="btn btn-secondary" href="?appointment_id=<?= (int)$b['appointment_id'] ?>"><?= e(fmt_dt($b['start_at']).' · '.$b['doctor_name']) ?></a></p><?php endforeach;?></section><?php endif;endif;?>
<form method="post" class="panel narrow"><?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= $pid ?>"><input type="hidden" name="appointment_id" value="<?= $aid ?>">
<?php if($a):?><h2>Appointment arrival</h2><p><?= e($a['appointment_no'].' · '.fmt_dt($a['start_at']).' · '.$a['doctor_name']) ?></p><?php if(substr($a['start_at'],0,10)!==date('Y-m-d')):?><?= f_check('other_date','This patient is arriving on a different day from the booking.',!empty($v['other_date'])) ?><?php endif;?>
<?php else:?><?= f_radio('origin','Arrival type',['WALK_IN'=>'Walk-in','EMERGENCY'=>'Emergency'],$v+['origin'=>'WALK_IN'],[]) ?><?= f_select('doctor_id','Preferred doctor',doctor_options(),$v,[],['placeholder'=>'Any available doctor']) ?><?php endif;?>
<?= f_textarea('note','Reception note',$v,[],['rows'=>3,'hint'=>'Optional arrival details. Clinical notes belong to the doctor.']) ?>
<div class="form-actions"><button class="btn btn-primary">Add to waiting queue</button><a class="btn btn-secondary" href="<?= e(url('reception/index.php')) ?>">Cancel</a></div></form>
<?php page_end();
