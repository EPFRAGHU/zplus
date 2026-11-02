<?php

/*
 * Version history (newest first). Versions are MAJOR.MINOR.PATCH:
 *   MAJOR - big milestones (1.0.0 = first live release)
 *   MINOR - new features
 *   PATCH - fixes and small improvements
 * Add an entry at the top for every change that is deployed.
 */
return [
    'started' => '2026-11-01',

    'releases' => [
        ['version' => '1.1.0', 'date' => '2026-11-02', 'title' => 'Version and release notes', 'type' => 'feature', 'notes' => [
            'Version number and release date shown in the sidebar',
            'Release notes page listing every version since the project started',
        ]],
        ['version' => '1.0.0', 'date' => '2026-11-02', 'title' => 'Live release', 'type' => 'major', 'notes' => [
            'Running at zplus.epf-dashboard.online on the Hostinger VPS (Coolify, Docker, MySQL 8, HTTPS)',
            'Database updates applied automatically on every deploy',
            'Deployment guides for Coolify and for a plain Ubuntu server',
        ]],
        ['version' => '0.10.0', 'date' => '2026-11-02', 'title' => 'EPF and ESIC switches', 'type' => 'feature', 'notes' => [
            'EPF and ESIC switched on or off per employee from the Employees list or form',
            'Employer EPF (13% of wages up to ₹15,000) and ESIC (3.25%) added on sub-bills, tax invoices and bill screens',
            'Employee EPF 12% and ESIC 0.75% deducted on the salary payout sheet',
        ]],
        ['version' => '0.9.0', 'date' => '2026-11-02', 'title' => 'Salary payout sheet', 'type' => 'feature', 'notes' => [
            'Bank transfer sheet per locked month on the agency letterhead: earned salary, deductions, net pay and bank details',
            'Available to Admin and Accounts only',
        ]],
        ['version' => '0.8.3', 'date' => '2026-11-02', 'title' => 'Appointment letter on one page', 'type' => 'fix', 'notes' => [
            'Appointment letter fits on one page under the letterhead',
        ]],
        ['version' => '0.8.2', 'date' => '2026-11-01', 'title' => 'Letterhead on attendance certificate', 'type' => 'fix', 'notes' => [
            'Attendance certificate printed on the agency letterhead',
        ]],
        ['version' => '0.8.1', 'date' => '2026-11-01', 'title' => 'SWACHHA tax invoices', 'type' => 'fix', 'notes' => [
            'SWACHHA tax invoices always use the SWACHHA letterhead',
        ]],
        ['version' => '0.8.0', 'date' => '2026-11-01', 'title' => 'SWACHHA letterhead', 'type' => 'feature', 'notes' => [
            'SWACHHA banner letterhead (logo, registrations, address box) on its sub-bills, covering letters, tax invoices and appointment letters',
            'Letterhead layout chosen per agency',
        ]],
        ['version' => '0.7.0', 'date' => '2026-11-01', 'title' => 'Sub-bills and tax invoices', 'type' => 'feature', 'notes' => [
            'Sub-bill per project per month',
            'Tax invoice combining a client\'s sub-bills, with HSN/SAC and bank details',
        ]],
        ['version' => '0.6.0', 'date' => '2026-11-01', 'title' => 'Z Plus letterhead', 'type' => 'feature', 'notes' => [
            'Z Plus letterhead with seal, registrations and footer on bills and letters',
            'Agency logo upload and letterhead details on the Agencies page',
        ]],
        ['version' => '0.5.0', 'date' => '2026-11-01', 'title' => 'Agency and client blocks', 'type' => 'feature', 'notes' => [
            'Every list grouped into agency blocks with client blocks inside',
        ]],
        ['version' => '0.4.0', 'date' => '2026-11-01', 'title' => 'Contracts and projects everywhere', 'type' => 'feature', 'notes' => [
            'GeM contract and project columns and filters on employees, attendance, bills, posts and the dashboard',
            'Agencies page lists clients; Clients page lists contracts and employees',
        ]],
        ['version' => '0.3.0', 'date' => '2026-11-01', 'title' => 'Posts', 'type' => 'feature', 'notes' => [
            'Posts page and an "Add new post" window on the employee form',
        ]],
        ['version' => '0.2.2', 'date' => '2026-11-01', 'title' => 'Employee form help', 'type' => 'fix', 'notes' => [
            'Explains why the GeM post list can be empty',
        ]],
        ['version' => '0.2.1', 'date' => '2026-11-01', 'title' => 'Attendance fix', 'type' => 'fix', 'notes' => [
            'Fixed an error when starting the same month\'s attendance again',
        ]],
        ['version' => '0.2.0', 'date' => '2026-11-01', 'title' => 'Agencies and clients', 'type' => 'feature', 'notes' => [
            'Each client belongs to one agency, chosen from a dropdown',
            'Agency and client shown on forms, attendance, bills, the dashboard and every PDF',
        ]],
        ['version' => '0.1.1', 'date' => '2026-11-01', 'title' => 'Bills page help', 'type' => 'fix', 'notes' => [
            'Bills page explains how a bill is made and lists months not yet locked',
        ]],
        ['version' => '0.1.0', 'date' => '2026-11-01', 'title' => 'Project started', 'type' => 'major', 'notes' => [
            'Masters: agencies, clients, GeM contracts, projects, posts',
            'Employees with encrypted bank and Aadhaar numbers',
            'Attendance with PI certificate, GeM bill calculator (service charge 3.85% before GST)',
            'Covering letter, appointment letter and Excel import of the GeM workbook',
        ]],
    ],
];
