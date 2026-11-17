<?php
require __DIR__.'/../includes/bootstrap.php';$u=require_login();require_post();v2_require();$id=post_int('id');
try {
    $action=patient_scalar($_POST,'action');
    if($action==='item'){clinical_item_add($id,$_POST,(int)$u['user_id']);}
    elseif($action==='void_item'){
        $item=q_one('SELECT visit_id FROM clinical_item WHERE item_id=?',[post_int('item_id')]);
        if(!$item || (int)$item['visit_id']!==$id)throw new DomainException('Record does not belong to this visit.');
        clinical_item_void(post_int('item_id'),patient_scalar($_POST,'reason'),(int)$u['user_id']);
    }else{workflow_action($id,$action,$_POST,(int)$u['user_id']);}
    flash('success','Visit updated.');
} catch(DomainException $e){flash('error',$e->getMessage());$_SESSION['visit_input']=['visit_id'=>$id,'data'=>$_POST];}
redirect('visits/view.php?id='.$id);
