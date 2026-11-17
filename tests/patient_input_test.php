<?php
// No database required. Run: php tests/patient_input_test.php
require __DIR__ . '/../includes/patient_fields.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/patients.php';
$n=0;
function check(bool $ok, string $label): void { global $n; if (!$ok) { fwrite(STDERR,"FAIL: $label\n"); exit(1); } $n++; }
$base=['full_name'=>'Test Patient','registration_date'=>date('Y-m-d'),'gender'=>'Female','nrc_mode'=>'structured','nrc_region'=>'14','nrc_township'=>'patana','nrc_type'=>'N','nrc_serial'=>'၀၀၁၂၃၄','phone'=>'၀၉ ၁၂၃၄၅၆၇၈၉'];
$e=[];$d=patient_validate($base,$e,true);
check(!$e && $d['nrc_number']==='14/PATANA(N)001234','Pantanaw, Myanmar digits, leading zeros');
check($d['phone']==='09 123456789','Myanmar telephone digits');
foreach ([['nrc_region'=>'15'],['nrc_region'=>'1'],['nrc_type'=>'INVALID'],['nrc_serial'=>'12345'],['nrc_serial'=>'1234567'],['nrc_serial'=>'12A456'],['nrc_region'=>['14']]] as $change) {
 $e=[];patient_validate(array_replace($base,$change),$e,true);check((bool)$e,'Reject invalid structured NRC '.json_encode($change));
}
$e=[];$d=patient_validate(array_replace($base,['nrc_mode'=>'none']),$e,true);check(!$e && $d['nrc_number']===null,'Registration without NRC');
$e=[];$d=patient_validate(array_replace($base,['nrc_mode'=>'manual','nrc_manual'=>'Older card 123']),$e,true);check(!$e && $d['nrc_number']==='Older card 123','Explicit manual fallback');
$e=[];$d=patient_validate(array_replace($base,['nrc_mode'=>'keep','nrc_number'=>'forged']),$e,false,['nrc_number'=>'Original NRC']);check(!$e && $d['nrc_number']==='Original NRC','Keep uses server original');
$e=[];patient_validate(array_replace($base,['nrc_mode'=>'keep']),$e,true);check(isset($e['nrc_mode']),'Cannot keep an absent original');
$e=[];patient_validate(array_replace($base,['gender'=>'Invalid']),$e,true);check(isset($e['gender']),'Controlled gender');
$e=[];patient_validate(array_replace($base,['gender'=>'F']),$e,false,['gender'=>'F']);check(!$e,'Preserve existing gender');
$e=[];$d=patient_validate(array_replace($base,['nationality_choice'=>'OTHER','nationality_other'=>'Nepali']),$e,true);check(!$e && $d['nationality']==='Nepali','Other nationality');
$e=[];patient_validate(array_replace($base,['nationality_choice'=>'OTHER','nationality_other'=>'']),$e,true);check(isset($e['nationality_other']),'Other nationality required');
$e=[];patient_validate(array_replace($base,['date_of_birth'=>'2999-01-01']),$e,true);check(isset($e['date_of_birth']),'Future birth date rejected');
$e=[];patient_validate(array_replace($base,['nrc_mode'=>'manual','nrc_manual'=>'']),$e,true);check(isset($e['nrc_manual']),'Empty manual entry rejected');
$ref=nrc_reference();check(count($ref['regions'])===14,'Fourteen NRC regions');
foreach($ref['townships'] as $region=>$towns) { foreach($towns as $code=>$label) {
 $e=[];patient_validate(array_replace($base,['nrc_region'=>(string)$region,'nrc_township'=>$code]),$e,true);check(!$e,"Reference accepted $region/$code");
} }
echo "$n checks passed.\n";
