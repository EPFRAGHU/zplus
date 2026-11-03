<?php

/*
 * Version history (newest first). Versions are MAJOR.MINOR.PATCH:
 *   MAJOR - big milestones (1.0.0 = first live release)
 *   MINOR - new features
 *   PATCH - fixes and small improvements
 * Add an entry at the top for every change that is deployed, with its date and time (IST).
 */
return [
    'started' => '2026-11-01 16:01',   // first commit
    'timezone' => 'Asia/Kolkata',      // release times are Indian Standard Time

    'releases' => [
        ['version' => '2.2.2', 'date' => '2026-11-04 00:45', 'title' => 'Agency filled in on forms', 'type' => 'fix', 'notes' => [
            'For a Z Plus or SWACHHA login, the Agency field on Add client, Add GeM contract, Add employee and Add new post is filled in and locked to their agency',
            'The Super Admin and both-agency logins still choose the agency',
        ]],
        ['version' => '2.2.1', 'date' => '2026-11-04 00:15', 'title' => 'Agency name on screen', 'type' => 'fix', 'notes' => [
            'Dashboard shows "You are working for" with the agency\'s name, logo and GSTIN (or "Both agencies")',
            'Every page shows the agency badge next to the page title',
            'Single-agency logins no longer see the Agency filter on the dashboard',
        ]],
        ['version' => '2.2.0', 'date' => '2026-11-03 23:30', 'title' => 'Separate logins for each agency', 'type' => 'feature', 'notes' => [
            'Each login works for one agency (Z Plus or SWACHHA) or, set by the Super Admin, for both',
            'A single-agency login sees and enters only its own agency\'s clients, contracts, projects, posts, staff, attendance, sub-bills and tax invoices; the other agency\'s records are "not found"',
            'Agency Admins create logins (operators, accounts, PIs) for their own agency only; only the Super Admin creates both-agency logins',
            'Data Upload rejects rows for the other agency, its contract numbers and its employee codes; employee codes stay unique across both agencies',
            'Adding agencies and the GeM workbook import are for both-agency logins only; the sidebar shows which agency a login works for',
        ]],
        ['version' => '2.1.0', 'date' => '2026-11-03 21:00', 'title' => 'Activity log for the Super Admin', 'type' => 'feature', 'notes' => [
            'Every add, edit, delete and status change is logged automatically: who, when, from which IP, and each field\'s old → new value',
            'Views of employee details and downloads of salary sheets, bills, letters, certificates, documents and data exports are logged',
            'Sign-ins, failed sign-ins, sign-outs, password changes and data uploads are logged; attendance saves list every code changed',
            'Bank account and Aadhaar numbers appear only as "••••" plus the last 4 digits',
            'Activity log page (Super Admin only) with filters by user, action, section, dates and search, and Excel download; entries cannot be changed or deleted',
            'Super Admin: the owner\'s account; other Admins cannot change it',
        ]],
        ['version' => '2.0.0', 'date' => '2026-11-02 22:15', 'title' => 'PI portal and user management', 'type' => 'major', 'notes' => [
            'PI (project in-charge) logins: a PI sees only their own projects and staff (no bank, Aadhaar, PAN, phone or salary)',
            'PI marks and saves attendance, then submits it: the certificate PDF is made automatically and the month is frozen for the PI',
            'PI downloads the PDF, signs it and uploads the signed copy in the app (or e-mails it), and can view and download the project\'s sub-bills',
            'Operator checks the submitted month, corrects any day the PI changed by hand (each change recorded and printed on the certificate), then approves',
            'Approving (locking) a month makes its sub-bill automatically as a draft; tax invoices stay one click',
            'Users screen (Admin): add staff and PI logins, temporary passwords that must be changed at first sign-in, switch accounts off, recent sign-ins',
            'Change password for every user; switched-off accounts are signed out',
        ]],
        ['version' => '1.2.0', 'date' => '2026-11-02 11:45', 'title' => 'Data upload from Excel templates', 'type' => 'feature', 'notes' => [
            'Excel templates for Agencies, Clients, GeM contracts, Projects, Posts and Employees, with instructions, help on every heading and dropdowns from the live data',
            'Download a blank template or one filled with the data already in the app',
            'Upload checks every row and shows New / Update / No change / Error before anything is saved; rows with errors are skipped',
            'Records are matched by code or number and updated; blank cells keep stored values; bank account and Aadhaar numbers are never downloaded',
        ]],
        ['version' => '1.1.1', 'date' => '2026-11-02 10:15', 'title' => 'Release times', 'type' => 'fix', 'notes' => [
            'Each release shows its date and time (IST) in the sidebar and on the Release notes page',
        ]],
        ['version' => '1.1.0', 'date' => '2026-11-02 09:56', 'title' => 'Version and release notes', 'type' => 'feature', 'notes' => [
            'Version number and release date shown in the sidebar',
            'Release notes page listing every version since the project started',
        ]],
        ['version' => '1.0.0', 'date' => '2026-11-02 09:46', 'title' => 'Live release', 'type' => 'major', 'notes' => [
            'Running at zplus.epf-dashboard.online on the Hostinger VPS (Coolify, Docker, MySQL 8, HTTPS)',
            'Database updates applied automatically on every deploy',
            'Deployment guides for Coolify and for a plain Ubuntu server',
        ]],
        ['version' => '0.10.0', 'date' => '2026-11-02 08:31', 'title' => 'EPF and ESIC switches', 'type' => 'feature', 'notes' => [
            'EPF and ESIC switched on or off per employee from the Employees list or form',
            'Employer EPF (13% of wages up to ₹15,000) and ESIC (3.25%) added on sub-bills, tax invoices and bill screens',
            'Employee EPF 12% and ESIC 0.75% deducted on the salary payout sheet',
        ]],
        ['version' => '0.9.0', 'date' => '2026-11-02 08:22', 'title' => 'Salary payout sheet', 'type' => 'feature', 'notes' => [
            'Bank transfer sheet per locked month on the agency letterhead: earned salary, deductions, net pay and bank details',
            'Available to Admin and Accounts only',
        ]],
        ['version' => '0.8.3', 'date' => '2026-11-02 08:16', 'title' => 'Appointment letter on one page', 'type' => 'fix', 'notes' => [
            'Appointment letter fits on one page under the letterhead',
        ]],
        ['version' => '0.8.2', 'date' => '2026-11-02 01:15', 'title' => 'Letterhead on attendance certificate', 'type' => 'fix', 'notes' => [
            'Attendance certificate printed on the agency letterhead',
        ]],
        ['version' => '0.8.1', 'date' => '2026-11-02 00:29', 'title' => 'SWACHHA tax invoices', 'type' => 'fix', 'notes' => [
            'SWACHHA tax invoices always use the SWACHHA letterhead',
        ]],
        ['version' => '0.8.0', 'date' => '2026-11-02 00:15', 'title' => 'SWACHHA letterhead', 'type' => 'feature', 'notes' => [
            'SWACHHA banner letterhead (logo, registrations, address box) on its sub-bills, covering letters, tax invoices and appointment letters',
            'Letterhead layout chosen per agency',
        ]],
        ['version' => '0.7.0', 'date' => '2026-11-02 00:07', 'title' => 'Sub-bills and tax invoices', 'type' => 'feature', 'notes' => [
            'Sub-bill per project per month',
            'Tax invoice combining a client\'s sub-bills, with HSN/SAC and bank details',
        ]],
        ['version' => '0.6.0', 'date' => '2026-11-01 23:54', 'title' => 'Z Plus letterhead', 'type' => 'feature', 'notes' => [
            'Z Plus letterhead with seal, registrations and footer on bills and letters',
            'Agency logo upload and letterhead details on the Agencies page',
        ]],
        ['version' => '0.5.0', 'date' => '2026-11-01 23:46', 'title' => 'Agency and client blocks', 'type' => 'feature', 'notes' => [
            'Every list grouped into agency blocks with client blocks inside',
        ]],
        ['version' => '0.4.0', 'date' => '2026-11-01 21:41', 'title' => 'Contracts and projects everywhere', 'type' => 'feature', 'notes' => [
            'GeM contract and project columns and filters on employees, attendance, bills, posts and the dashboard',
            'Agencies page lists clients; Clients page lists contracts and employees',
        ]],
        ['version' => '0.3.0', 'date' => '2026-11-01 21:01', 'title' => 'Posts', 'type' => 'feature', 'notes' => [
            'Posts page and an "Add new post" window on the employee form',
        ]],
        ['version' => '0.2.2', 'date' => '2026-11-01 20:35', 'title' => 'Agency and client on the dashboard and PDFs', 'type' => 'fix', 'notes' => [
            'Agency and client named on the dashboard, employees list, bill PDF, covering letter, appointment letter and attendance certificate',
            'Employee form explains why the GeM post list can be empty',
        ]],
        ['version' => '0.2.1', 'date' => '2026-11-01 19:33', 'title' => 'Attendance fix', 'type' => 'fix', 'notes' => [
            'Fixed an error when starting the same month\'s attendance again',
        ]],
        ['version' => '0.2.0', 'date' => '2026-11-01 19:30', 'title' => 'Agencies and clients', 'type' => 'feature', 'notes' => [
            'Each client belongs to one agency, chosen from a dropdown',
            'Agency and client shown on the employee and contract forms, attendance and bills',
        ]],
        ['version' => '0.1.1', 'date' => '2026-11-01 16:35', 'title' => 'Bills page help', 'type' => 'fix', 'notes' => [
            'Bills page explains how a bill is made and lists months not yet locked',
        ]],
        ['version' => '0.1.0', 'date' => '2026-11-01 16:18', 'title' => 'Project started', 'type' => 'major', 'notes' => [
            'Masters: agencies, clients, GeM contracts, projects, posts',
            'Employees with encrypted bank and Aadhaar numbers',
            'Attendance with PI certificate, GeM bill calculator (service charge 3.85% before GST)',
            'Covering letter, appointment letter and Excel import of the GeM workbook',
        ]],
    ],
];
