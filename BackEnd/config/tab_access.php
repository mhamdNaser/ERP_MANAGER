<?php

/*
|--------------------------------------------------------------------------
| جمهور تبويبات الشريط الجانبي
|--------------------------------------------------------------------------
|
| كل تبويب في FrontEnd/json/navigation.json له هنا مدخل بمعرّفه نفسه:
|
|   view    الصلاحيات التي يفتحها الربط بمستوى «اطلاع» (قد تكون فارغة لتبويب
|           متاح أصلاً للجميع مثل الرئيسية).
|   manage  ما يضيفه مستوى «إدارة» فوقها، إن كان للتبويب إدارة.
|   role    true لتبويب يحكمه الدور نفسه (مركز قاعدة البيانات، الموظفون...):
|           الربط يحصر من يراه بين أصحاب الدور، ولا يفتحه لغيرهم.
|
| تبويب بلا روابط يعمل كما هو حسب الأدوار. متى رُبط بجمهور (الجميع، فرع، قسم،
| موظف — معاً أو منفردة) صار مقصوراً عليه، ومدير قواعد البيانات يراه دائماً.
| تبويب «صلاحيات الأدوار» ليس هنا عمداً: فتحه بربط يسلّم إدارة الصلاحيات كلها.
|
*/

return [
    // يرى كل التبويبات دائماً مهما كانت الروابط، كي لا يُقفَل النظام على أحد.
    'always_visible_roles' => ['database_manager'],

    'tabs' => [
        'dashboard' => ['view' => []],
        'tasks' => ['view' => [], 'manage' => ['tasks.create', 'tasks.update']],
        'task-stats' => ['view' => ['tasks.statistics.view']],
        'drive' => ['view' => []],
        'reports' => ['view' => ['reports.view'], 'manage' => ['reports.create', 'reports.update.returned', 'reports.transition']],
        'new' => ['view' => [], 'role' => true],
        'communications' => ['view' => ['messages.view'], 'manage' => ['messages.create', 'messages.reply']],
        'formal-correspondences' => ['view' => ['formal_correspondences.view'], 'manage' => ['formal_correspondences.create', 'formal_correspondences.route']],
        'circulars' => ['view' => ['circulars.view'], 'manage' => ['circulars.create']],
        'forms' => ['view' => []],
        'organization' => ['view' => ['organization.view']],
        'offices' => ['view' => ['offices.manage']],
        'database' => ['view' => [], 'role' => true],
        'document-templates' => ['view' => ['templates.manage']],
        'org-tree' => ['view' => [], 'role' => true],
        'hr' => ['view' => ['hr.view'], 'manage' => ['hr.manage', 'hr.approve']],
        'fleet' => ['view' => ['fleet.view', 'fleet.request'], 'manage' => ['fleet.manage', 'fleet.approve']],
        'maintenance' => ['view' => ['maintenance.view'], 'manage' => ['maintenance.manage']],
        'employees' => ['view' => [], 'role' => true],
        'hr-requests' => ['view' => []],
        'profile' => ['view' => []],
        'guide' => ['view' => []],
    ],
];
