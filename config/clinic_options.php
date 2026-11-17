<?php
// Clinic-specific options. Edit this file to revise labels, code sets and provisional fields; no schema change needed
// for the 'extra_fields'. Codes below come from the Stage 1 specification (a transcription of the clinic form);
// they have NOT been checked against the original form image. Their meanings are deliberately not expanded.
return [
    // Documented examination code fields: column => [label, allowed codes]. Stored exactly as typed here.
    'code_fields' => [
        'general_teeth_condition' => ['Teeth condition', ['G', 'F', 'P']],
        'oral_hygiene'            => ['Oral hygiene', ['G', 'F', 'P']],
        'gingival_inflammation'   => ['Gingival inflammation', ['G', 'L']],
        'gingival_recession'      => ['Gingival recession', ['G', 'L']],
        'occlusion_class'         => ['Occlusion', ['1', '2', '3']],
        'overbite_code'           => ['Overbite (OB)', ['+', 'N', '-']],
        'overjet_code'            => ['Overjet (OJ)', ['+', 'N', '-']],
        'lipline_code'            => ['Lipline', ['H', 'N', 'L']],
        'midline_code'            => ['Midline', ['R', 'N', 'L']],
    ],
    // PROVISIONAL examination additions (not confirmed clinic requirements). key => [label, type, section, hint]
    // types: text | textarea | yesno.  Sections are only headings in the form.
    'extra_fields' => [
        'chief_complaint'     => ['Chief complaint', 'textarea', 'Presenting complaint', ''],
        'presenting_symptoms' => ['Presenting symptoms', 'textarea', 'Presenting complaint', ''],
        'symptom_duration'    => ['Duration of symptoms', 'text', 'Presenting complaint', 'For example: 3 days'],
        'tmj_pain'            => ['TMJ pain', 'yesno', 'Jaw, TMJ and function', ''],
        'tmj_clicking'        => ['TMJ clicking', 'yesno', 'Jaw, TMJ and function', ''],
        'extraoral_findings'  => ['Extraoral findings', 'textarea', 'Clinical findings', ''],
        'intraoral_findings'  => ['Intraoral findings', 'textarea', 'Clinical findings', ''],
        'tooth_findings'      => ['Tooth-specific findings', 'textarea', 'Clinical findings', 'One line per tooth: tooth number, finding'],
        'clinical_impression' => ['Diagnosis / clinical impression', 'textarea', 'Assessment and plan', ''],
        'treatment_plan'      => ['Treatment plan', 'textarea', 'Assessment and plan', ''],
        'follow_up'           => ['Recommended follow-up', 'textarea', 'Assessment and plan', ''],
        'additional_notes'    => ['Additional examination notes', 'textarea', 'Assessment and plan', ''],
    ],
    // Dental history questionnaire (one column each). Wording provisional until checked against the clinic form.
    'dental_yesno' => [
        'extraction_complications' => 'History of extraction complications',
        'flosses'                  => 'Flossing',
        'bleeding_gums'            => 'Bleeding gums',
        'bad_breath'               => 'Bad breath (halitosis)',
        'sensitive_sweet'          => 'Tooth sensitivity to sweet',
        'sensitive_hot'            => 'Tooth sensitivity to hot',
        'sensitive_cold'           => 'Tooth sensitivity to cold',
        'tender_chewing'           => 'Tenderness when chewing',
        'bruxism'                  => 'Bruxism (grinding or clenching)',
        'orthodontic_history'      => 'Orthodontic history',
        'tmj_pain'                 => 'TMJ pain',
        'tmj_clicking'             => 'TMJ clicking',
        'smokes_tobacco'           => 'Smokes tobacco',
        'chews_betel_quid'         => 'Chews betel quid',
    ],
    // Brushing frequency choices: PROVISIONAL (the form wording is unknown).
    'brushing_options' => ['Once a day', 'Twice a day', 'More than twice a day', 'Less than once a day', 'Rarely or never'],
    'visit_cancel_reasons' => ['CREATED_IN_ERROR' => 'Created in error', 'PATIENT_LEFT' => 'Patient left', 'OTHER' => 'Other'],
    'discard_reasons'      => ['ENTERED_IN_ERROR' => 'Entered in error', 'WRONG_PATIENT' => 'Wrong patient or visit', 'OTHER' => 'Other'],
];
