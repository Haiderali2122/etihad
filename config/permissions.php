<?php

/*
|--------------------------------------------------------------------------
| Permissions
|--------------------------------------------------------------------------
|
| Every action the app checks, grouped for the role editor. Each key is
| registered as a Gate, so routes use `can:<key>` and views use @can.
| Roles marked "full access" (the owner) pass every check.
|
*/

return [

    'Paper' => [
        'paper.view' => 'View paper stock and slips',
        'paper.receive' => 'Receive trucks (paper receipt slips)',
        'paper.issue' => 'Issue paper',
        'paper.types' => 'Manage paper types',
        'godowns.manage' => 'Manage godowns (unloading locations)',
    ],

    'Inventory' => [
        'purchases.view' => 'View inventory, purchases and items moved out',
        'purchases.create' => 'Enter purchases (stock in)',
        'inventory.issue' => 'Move items out of inventory',
        'inventory.manage' => 'Manage inventory categories and items',
        'parties.manage' => 'Manage parties and suppliers',
    ],

    'Cash' => [
        'expenses.view' => 'View cash-out and roznamcha',
        'expenses.create' => 'Enter expenses',
        'give-cash' => 'Give cash to staff',
        'expense-heads.manage' => 'Manage expense heads',
    ],

    'Machines' => [
        'machines.view' => 'View machines and repairs',
        'machines.manage' => 'Add and edit machines',
        'machines.repair' => 'Log repairs and part bills',
    ],

    'Production' => [
        'books.view' => 'View books and formats',
        'books.manage' => 'Add, edit and delete books',
        'orders.view' => 'View work orders, contractors, plates, dispatch, buyers and waste',
        'orders.manage' => 'Create work orders and move them through stages',
        'contractors.manage' => 'Manage folding and binding contractors, jobs and payments',
        'plates.manage' => 'Record plate processing and clear its bills',
        'dispatch.manage' => 'Record loading and dispatch',
        'buyers.manage' => 'Record buyer advances and adjustments',
        'waste.manage' => 'Record waste',
    ],

    'Staff & payroll' => [
        'staff.view' => 'View staff, attendance, advances and payroll',
        'staff.manage' => 'Add and edit employees',
        'attendance.manage' => 'Mark and import attendance',
        'advances.create' => 'Give advances, loans and foreman sheets',
        'payroll.prepare' => 'Prepare payroll',
        'payroll.finalize' => 'Finalize and reopen payroll',
    ],

    'Owner controls' => [
        'approve' => 'Approve or send back entries',
        'reports.view' => 'View and export reports',
        'manage-settings' => 'Change settings and daily limit',
        'users.manage' => 'Manage users, roles and permissions',
    ],

];
