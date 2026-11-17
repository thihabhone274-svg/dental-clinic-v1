<?php
require __DIR__.'/../includes/bootstrap.php';
$u=require_can('admin.accounts'); $v=[]; $err=[];
if(is_post()) {
 require_post(); $v=$_POST;
 $name=v_text($v,'display_name',$err,'Display name',['required'=>true,'max'=>160]);
 $username=v_text($v,'username',$err,'Username',['required'=>true,'min'=>3,'max'=>80,'pattern'=>'/^[A-Za-z0-9._-]+$/']);
 $role=(string)($v['role']??''); $pw=(string)($v['password']??'');
 if(!in_array($role,['STAFF','DOCTOR'],true)) $err['role']='Choose Staff or Doctor.';
 if($e=password_error($pw,$username)) $err['password']=$e;
 if($pw!==($v['password_confirm']??'')) $err['password_confirm']='Passwords do not match.';
 if(!$err) try {
  tx(function()use($name,$username,$role,$pw,$u){
   if(q_val('SELECT 1 FROM user_account WHERE username_key=?',[mb_strtolower($username)])) throw new DomainException('Username is already in use.');
   q('INSERT INTO user_account(username,username_key,display_name,password_hash) VALUES (?,?,?,?)',[$username,mb_strtolower($username),$name,password_hash($pw,PASSWORD_DEFAULT)]);
   $id=(int)db()->lastInsertId(); q('INSERT INTO user_role(user_id,role_code,assigned_by) VALUES (?,?,?)',[$id,$role,$u['user_id']]);
   if($role==='DOCTOR') doctor_ensure_profile($id,$name);
   audit('ACCOUNT_CREATED','user_account',$id,'SUCCESS',['role'=>$role]);
  }); flash('success','Account created. They can now sign in.'); redirect('admin/accounts.php');
 } catch(DomainException $e) { $err['username']=$e->getMessage(); }
}
page_start('Create an account',['nav'=>'accounts','subtitle'=>'Create a reception or doctor login for this clinic.']);
?>
<form method="post" class="card"><?= csrf_field() ?>
<?= f_text('display_name','Display name',$v,$err,['required'=>true]) ?>
<?= f_text('username','Username',$v,$err,['required'=>true]) ?>
<?= f_select('role','Role',['STAFF'=>'Staff / reception','DOCTOR'=>'Doctor'],$v,$err,['required'=>true]) ?>
<?= f_text('password','Password',[],$err,['type'=>'password','required'=>true,'autocomplete'=>'new-password']) ?>
<?= f_text('password_confirm','Repeat password',[],$err,['type'=>'password','required'=>true,'autocomplete'=>'new-password']) ?>
<button class="btn btn-primary">Create account</button>
<a class="btn btn-secondary" href="<?= e(url('admin/accounts.php')) ?>">Cancel</a></form>
<?php page_end();
