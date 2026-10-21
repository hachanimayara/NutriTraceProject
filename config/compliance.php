<?php

return [
    // Certifications that require an uploaded document, matched against scheme or title.
    // Use ['*'] only if every certificate in this installation must have an attachment.
    'required_certification_schemes' => [],
    'renewal_warning_days' => (int) env('COMPLIANCE_RENEWAL_WARNING_DAYS', 30),
    // Map product category (or '*') to required certification scheme names.
    'required_certifications_by_category' => [],
    // Map indicator stage (or '*') to required metric codes.
    'required_indicator_codes_by_stage' => [],
    // Optional per-metric unit and range rules, e.g. ['water_liters' => ['units' => ['L'], 'min' => 0]].
    'indicator_rules' => [],
];
