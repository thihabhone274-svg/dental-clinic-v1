<?php
require __DIR__.'/../includes/bootstrap.php';$u=require_login();require_post();v2_require();
try {
    if(($_POST['action']??'')==='claim') {
        $id=arrival_claim(post_int('arrival_id'),patient_scalar($_POST,'mode'),(int)$u['user_id']);flash('success','Visit started. You are the responsible doctor.');redirect('visits/view.php?id='.$id);
    } elseif(($_POST['action']??'')==='cancel') { arrival_cancel(post_int('arrival_id'),patient_scalar($_POST,'reason'),(int)$u['user_id']);flash('success','Arrival cancelled. The original booking is retained.'); }
    else { throw new DomainException('Unknown action.'); }
} catch(DomainException $e){flash('error',$e->getMessage());}
redirect('reception/index.php');
