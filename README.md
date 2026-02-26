<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/json2activity:use' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ]
];
