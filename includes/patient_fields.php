<?php
declare(strict_types=1);
/** Local reference data: format assistance, not identity verification. */
function nrc_reference(): array {
    static $data;
    return $data ??= json_decode(file_get_contents(__DIR__ . '/../assets/data/nrc.json'), true, 512, JSON_THROW_ON_ERROR);
}
function patient_scalar(array $in, string $key): string { return is_scalar($in[$key] ?? '') ? trim((string) ($in[$key] ?? '')) : ''; }
function myanmar_digits(string $s): string { return strtr($s, array_combine(preg_split('//u', '၀၁၂၃၄၅၆၇၈၉', -1, PREG_SPLIT_NO_EMPTY), str_split('0123456789'))); }
function patient_genders(): array { return ['Male'=>'Male','Female'=>'Female','Other'=>'Other','Not stated'=>'Not stated']; }
function patient_nationalities(): array {
    return ['Myanmar'=>'Myanmar','Thai'=>'Thai','Chinese'=>'Chinese','Indian'=>'Indian','Bangladeshi'=>'Bangladeshi','Japanese'=>'Japanese','Korean'=>'Korean','Singaporean'=>'Singaporean','Malaysian'=>'Malaysian','British'=>'British','American'=>'American','OTHER'=>'Other — specify'];
}
/** Original values come from the database, never from a hidden browser field. */
function patient_input_normalize(array $in, array &$err, array $original): array {
    $gender = patient_scalar($in, 'gender');
    if ($gender !== '' && !isset(patient_genders()[$gender]) && $gender !== (string) ($original['gender'] ?? '')) { $err['gender'] = 'Choose a gender from the list.'; }
    if (isset($in['nationality_choice'])) {
        $choice = patient_scalar($in, 'nationality_choice');
        if ($choice !== '' && !isset(patient_nationalities()[$choice])) { $err['nationality_choice'] = 'Choose a nationality from the list.'; }
        $in['nationality'] = $choice === 'OTHER' ? patient_scalar($in, 'nationality_other') : $choice;
        if ($choice === 'OTHER' && $in['nationality'] === '') { $err['nationality_other'] = 'Enter the nationality, or select Not recorded.'; }
        if (mb_strlen($in['nationality']) > 80) { $err['nationality_other'] = 'Nationality must be at most 80 characters.'; }
    }
    $in['phone'] = myanmar_digits(patient_scalar($in, 'phone'));
    // Legacy callers remain compatible; browser forms always submit the explicit NRC mode.
    if (!isset($in['nrc_mode'])) { return $in; }
    $mode = patient_scalar($in, 'nrc_mode');
    $in['nrc_number'] = '';
    if ($mode === 'keep' && array_key_exists('nrc_number', $original)) {
        $in['nrc_number'] = $original['nrc_number']; return $in;
    }
    if ($mode === 'none') { return $in; }
    if ($mode === 'manual') {
        $in['nrc_number'] = patient_scalar($in, 'nrc_manual');
        if ($in['nrc_number'] === '' || mb_strlen($in['nrc_number']) > 80) { $err['nrc_manual'] = 'Enter the NRC as printed (up to 80 characters), or choose Not available.'; }
        return $in;
    }
    if ($mode !== 'structured') { $err['nrc_mode'] = 'Choose how to record the NRC.'; return $in; }
    $ref = nrc_reference();
    $region = patient_scalar($in, 'nrc_region'); $township = strtoupper(patient_scalar($in, 'nrc_township'));
    $type = patient_scalar($in, 'nrc_type'); $serial = myanmar_digits(patient_scalar($in, 'nrc_serial'));
    if (!isset($ref['regions'][$region])) { $err['nrc_region'] = 'Choose the state/region number printed on the card.'; }
    if (!isset($ref['townships'][$region][$township])) { $err['nrc_township'] = 'Choose an NRC township belonging to that region.'; }
    if (!isset($ref['types'][$type])) { $err['nrc_type'] = 'Choose the card type printed on the card.'; }
    if (!preg_match('/^[0-9]{6}$/D', $serial)) { $err['nrc_serial'] = 'Enter all six digits, including leading zeros. For a different card format, use Enter as printed.'; }
    if (!array_intersect(['nrc_region','nrc_township','nrc_type','nrc_serial'], array_keys($err))) { $in['nrc_number'] = "$region/$township($type)$serial"; }
    return $in;
}
function patient_fields(array $v, array $err, bool $creating): void {
    $ref = nrc_reference(); $genders = patient_genders(); $gender = patient_scalar($v, 'gender');
    if ($gender !== '' && !isset($genders[$gender])) { $genders[$gender] = $gender . ' (existing value)'; }
    if (!isset($v['nationality_choice'])) {
        $n = patient_scalar($v, 'nationality');
        $v['nationality_choice'] = $n === '' ? '' : (isset(patient_nationalities()[$n]) && $n !== 'OTHER' ? $n : 'OTHER');
        $v['nationality_other'] = $v['nationality_choice'] === 'OTHER' ? $n : '';
    }
    $v['nrc_mode'] = $v['nrc_mode'] ?? ($creating ? 'structured' : 'keep');
    $region = patient_scalar($v, 'nrc_region');
    $townships = [];
    // Without JS, all options remain available and the server checks the pairing.
    foreach ($ref['townships'] as $s => $items) { foreach ($items as $code => $caption) { if (!isset($townships[$code]) || (string)$s === $region) { $townships[$code] = $caption; } } }
    ?>
    <p class="hint">Only the patient name<?= $creating ? ' and registration date are' : ' is' ?> required. Leave unknown information unrecorded.</p>
    <fieldset class="form-section"><legend>Personal details</legend><div class="grid-2">
    <?= f_text('full_name', 'Patient name', $v, $err, ['required'=>true,'maxlength'=>160,'autocomplete'=>'name']) ?>
    <?= f_text('date_of_birth', 'Date of birth', $v, $err, ['type'=>'date','max'=>date('Y-m-d'),'autocomplete'=>'bday','hint'=>'Leave blank if unknown; do not invent a date.']) ?>
    <?= f_select('gender', 'Gender', $genders, $v, $err, ['placeholder'=>'Not recorded']) ?>
    <?= f_text('occupation', 'Occupation', $v, $err, ['maxlength'=>120]) ?>
    <?= f_select('nationality_choice', 'Nationality', patient_nationalities(), $v, $err, ['placeholder'=>'Not recorded','hint'=>'Nationality, not ethnicity. Choose Other if it is not listed.']) ?>
    <div data-nationality-other><?= f_text('nationality_other', 'Other nationality', $v, $err, ['maxlength'=>80]) ?></div>
    </div></fieldset>
    <fieldset class="form-section"><legend>Contact details</legend>
    <?= f_text('phone', 'Telephone number', $v, $err, ['type'=>'tel','inputmode'=>'tel','autocomplete'=>'tel','maxlength'=>40,'hint'=>'Myanmar or international number. English and Myanmar digits are accepted.']) ?>
    <?= f_textarea('address', 'Current address', $v, $err, ['rows'=>3,'hint'=>'House/street, ward/village, township and state/region. This may differ from the NRC issuing township.']) ?>
    </fieldset>
    <fieldset class="form-section nrc-picker" data-nrc-url="<?= e(url('assets/data/nrc.json')) ?>"><legend>Myanmar NRC</legend>
    <p class="hint">Copy the issuing code from the card. An NRC is optional for registration, including children and patients without a card.</p>
    <?php $modes = ['structured'=>'Choose NRC details','manual'=>'Enter as printed / code not listed','none'=>'Not available / not provided'];
    if (!$creating) { $modes = ['keep'=>'Keep existing NRC unchanged'] + $modes; } ?>
    <?= f_select('nrc_mode','How to record NRC',$modes,$v,$err) ?>
    <?php if (!$creating && !is_post()): ?><p class="hint">Existing NRC: <strong><?= e($v['nrc_number'] ?: 'Not recorded') ?></strong></p><?php endif; ?>
    <div data-nrc-fields="structured"><div class="grid-2">
    <?= f_select('nrc_region','State / region code',$ref['regions'],$v,$err,['placeholder'=>'Choose 1–14']) ?>
    <div><label for="nrc-search" class="hint">Find township by code or name</label><input class="input" id="nrc-search" type="search" placeholder="e.g. PATANA, Pantanaw, ပတန" autocomplete="off">
    <?= f_select('nrc_township','NRC township code',$townships,$v,$err,['placeholder'=>'Choose township','hint'=>'Choose the code printed on the NRC, not the current address.']) ?></div>
    <?= f_select('nrc_type','Card type',$ref['types'],$v,$err,['placeholder'=>'Choose card type']) ?>
    <?= f_text('nrc_serial','Serial number',$v,$err,['maxlength'=>6,'inputmode'=>'numeric','hint'=>'Six digits. Keep leading zeros; Myanmar digits also work.']) ?>
    </div><p class="nrc-preview">NRC preview: <output id="nrc-preview" aria-live="polite">Complete the four fields</output></p></div>
    <div data-nrc-fields="manual"><?= f_text('nrc_manual','NRC exactly as printed',$v,$err,['maxlength'=>80,'hint'=>'For older cards or missing reference codes. Saved as entered; not checked against the township list.']) ?></div>
    <noscript><p class="hint">Live filtering needs JavaScript. All township choices are available; only fields for your selected NRC mode are saved.</p></noscript>
    </fieldset>
    <?php if ($creating): ?><fieldset class="form-section"><legend>Registration</legend><?= f_text('registration_date','Registration date',$v,$err,['type'=>'date','required'=>true,'max'=>date('Y-m-d')]) ?></fieldset><?php endif;
}
