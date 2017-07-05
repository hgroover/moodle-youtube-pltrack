<?php
$capabilities = array(
 'mod/ytpltrack:addinstance' => array(
    'riskbitmask' => RISK_XSS,
    'captype' => 'write',
    'contextlevel' => CONTEXT_COURSE,
    'archetypes' => array(
      'editingteacher' => CAP_ALLOW,
      'manager' => CAP_ALLOW
    ),
    'clonepermissionsfrom' => 'moodle/course:manageactivities'
  ),
  'mod/ytpltrack:view' => array(
    'captype' => 'read',
    'contextlevel' => CONTEXT_MODULE,
    'archetypes' => array(
        'guest' => CAP_ALLOW,
        'student' => CAP_ALLOW,
        'teacher' => CAP_ALLOW,
        'editingteacher' => CAP_ALLOW,
        'manager' => CAP_ALLOW
    )
  )
);
