<?php

// AI Workspace quality gate: one line per rule (QualityGate).
return [
    'instructions_in_output' => 'Contains instructions aimed at the system:',
    'markup_removed' => 'Markup removed:',
    'client_out_of_scope' => 'This AI is not allowed to read this client:',
    'western_digits' => 'Digits changed to 0–9:',
    'platform_in_latin' => 'Platform name written in Latin:',
    'joined_wa' => 'Space added after «و» before a Latin word:',
    'joined_letter' => 'Letter joined to a Latin word; reword it:',
    'brand_feminine' => 'AdsRanger is feminine; use «هي» / «تقدّم»:',
    'guarantee_removed' => 'Guarantee wording removed:',
    'competitor_named' => 'Names another tool; remove it:',
    'overclaim' => 'Promise we can\'t keep; reword it:',
    'number_not_in_data' => 'This number isn\'t in this client\'s data:',
    'contract' => 'Output format:',
];
