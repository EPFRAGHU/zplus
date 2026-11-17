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
        ['version' => '2.13.0', 'date' => '2026-11-17 13:28', 'title' => 'GeM contract tools: import, papers, bill pack, reminders', 'type' => 'feature', 'notes' => [
            'Work orders: service start and end date and total contract value; "Ends in N days" warning from 30 days before the end date (work order list, work order page and dashboard)',
            'Import posts from the GeM contract PDF: upload it on the work order page, check the posts (designation, number, basic pay), service period and value, then add the ticked posts to a project. The app checks its reading against the contract\'s printed total',
            'Monthly papers on each work order: salary payment proof, EPF challan, ESIC challan, logbook, service feedback – checklist per month (EPF/ESIC only when someone has it switched on)',
            '"Download bill pack (ZIP)": tax invoice, sub-bills, covering letters (signed copies where uploaded), PI attendance certificates and the month\'s papers, for uploading on GeM',
            'Dashboard reminders: last month\'s wages due by the 7th and EPF/ESIC by the 15th per work order, work orders ending soon, posts with more staff than sanctioned',
            'Admin / Accounts only (removing a paper: Admin); salary proofs only for those who may see bank details; every upload, download and removal is in the Activity log. No bill amount or number changes',
        ]],
        ['version' => '2.12.1', 'date' => '2026-11-17 08:29', 'title' => 'Search box for employees', 'type' => 'fix', 'notes' => [
            'Employee Management: type part of the name, Emp ID, post, project or agency in the Employee box; matching employees drop down as you type',
            'Click one (or use the arrow keys and Enter) to open all their details, as before',
        ]],
        ['version' => '2.12.0', 'date' => '2026-11-17 08:21', 'title' => 'E-mail to client', 'type' => 'feature', 'notes' => [
            '"E-mail to client" box on every sub-bill (sends the sub-bill + covering letter) and tax invoice (sends the tax invoice + all its sub-bills), from the agency\'s own Gmail',
            'The DSC-signed copy is attached when it was uploaded after the last change; otherwise the PDF is made fresh and marked "not signed"',
            'To (client\'s e-mail from the Clients page), CC, subject and message are filled in and can be changed; every e-mail (sent or failed) is listed on the page and in the Activity log',
            'Switched off until each agency\'s Gmail address and Gmail app password are added on the server (Coolify → Environment Variables: AGENCY_MAIL_SWH_USERNAME / _PASSWORD, AGENCY_MAIL_ZPS_USERNAME / _PASSWORD); the password is never stored in the app or shown',
            'Admin / Accounts only; no change to any bill, amount or number',
        ]],
        ['version' => '2.11.0', 'date' => '2026-11-17 07:01', 'title' => 'Month ZIP and DSC-signed copies', 'type' => 'feature', 'notes' => [
            'Download all PDFs of a month as one ZIP per agency (sub-bills, covering letters, tax invoices and the bank letter) from the Sub-bills page (choose month and agency) or the Bank Letter page – to sign them together with the DSC token (Adobe Reader / JSignPdf)',
            '"Signed copy (DSC)" box on every sub-bill, tax invoice and monthly bank letter: upload the signed PDF; the app keeps it, shows "Signed ✓" and warns if the bill changed after signing',
            'Admin / Accounts only (bank letter: those who may see bank details); every upload and download is in the Activity log',
        ]],
        ['version' => '2.10.0', 'date' => '2026-11-17 05:59', 'title' => 'Super Admin delete options', 'type' => 'feature', 'notes' => [
            'Super Admin only: a "Show delete options" switch in the sidebar; Delete buttons appear only while it is on',
            'Delete agencies, clients, work orders, Project In-charges, projects and employees – only when nothing depends on them; otherwise the button is disabled and says what to remove or move first',
            'Employees with attendance, bills or letters are kept (set them Inactive); only records entered by mistake can be deleted',
            'Sub-bills and tax invoices: the Super Admin can delete any that is not paid (a sub-bill leaves its tax invoice, which is worked out again or deleted when empty)',
            'Every delete is recorded in the Activity log; other Admins, Accounts and Operators are unchanged',
        ]],
        ['version' => '2.9.4', 'date' => '2026-11-17 05:45', 'title' => 'Only the Super Admin can delete a project', 'type' => 'fix', 'notes' => [
            '"Delete project" and "Delete this empty project" are shown to and allowed for the Super Admin only; other Admins, Accounts and Operators no longer see them',
        ]],
        ['version' => '2.9.3', 'date' => '2026-11-17 05:33', 'title' => 'Delete an empty example project', 'type' => 'fix', 'notes' => [
            'Admin: "Delete project" on the work order page and "Delete this empty project" on the Attendance list, for projects entered by mistake or as an example',
            'Only when the project has no attendance, no sub-bills and no employees; its empty posts are removed with it; recorded in the Activity log',
        ]],
        ['version' => '2.9.2', 'date' => '2026-11-17 05:20', 'title' => 'TDS on/off per employee', 'type' => 'fix', 'notes' => [
            'TDS switch per employee, like EPF and ESIC: on the Employees list (EPF / ESIC / TDS switches) and in the employee form (KYC & statutory tab)',
            'On for everyone already in the app and for new employees, so nothing changes until a person is switched off; switched off = no TDS on the salary sheet, bank letter and Employee Management',
            'Data Upload: new "TDS" Yes/No column in the employee template (blank on new staff = Yes)',
        ]],
        ['version' => '2.9.0', 'date' => '2026-11-17 00:10', 'title' => 'TDS 2% on salaries', 'type' => 'feature', 'notes' => [
            'Salary payout sheet: TDS 2% of the earned amount (rounded to the rupee) is deducted from every employee; Net pay = earned − EPF 12% − ESIC 0.75% − TDS 2%',
            'The bank letters (per project and the Bank Letter module) and Employee Management use the same net pay, so the amounts sent to the bank are after TDS',
            'Bills to the client are not affected',
        ]],
        ['version' => '2.8.0', 'date' => '2026-11-17 00:03', 'title' => 'Bank Letter module: all employees of the month in one letter', 'type' => 'feature', 'notes' => [
            'New "Bank Letter" menu (Admin / Accounts): choose agency and month to see every employee of all its projects with net salary, bank, account no. and IFSC (other banks), with SBI / NEFT totals',
            'One salary transfer letter PDF on the agency\'s own letterhead (SWACHHA or Z Plus): SBI accounts by Emp ID, other banks by NEFT with account no. and IFSC; projects can be left out by unticking',
            'Projects whose month is not locked yet, and staff without bank details, are listed as not included',
            'The per-project bank letter on the attendance page still works',
        ]],
        ['version' => '2.7.0', 'date' => '2026-11-16 23:50', 'title' => 'Reset a project\'s month', 'type' => 'feature', 'notes' => [
            'Attendance month page → "Reset this month" (Admin only, type RESET to confirm): deletes that project\'s month – the attendance of all its employees, the corrections, the uploaded PDFs and its sub-bill – so the month can be started again',
            'Works for draft, approved and submitted sub-bills; never for paid ones. The sub-bill is taken out of its tax invoice: a tax invoice left empty is deleted, otherwise it is worked out again',
            'The work order\'s bill number for the month is kept, so the new sub-bill gets the same number',
            'Every reset is recorded in the Activity log',
        ]],
        ['version' => '2.6.3', 'date' => '2026-11-16 23:40', 'title' => 'Enter a work order once, add projects to it', 'type' => 'fix', 'notes' => [
            'Work Orders list: "Add project" on every work order, so projects using the same GeM bid and contract number go under it without typing them again',
            'Add work order: typing a bid or contract number that is already saved shows it, with a link to add your project there; saving a duplicate explains the same',
            'Work order page: "Add project" button at the top',
        ]],
        ['version' => '2.6.2', 'date' => '2026-11-16 23:32', 'title' => 'Project In-charge column on Employee Management', 'type' => 'fix', 'notes' => [
            'Employee Management list: new "Project In-charge" column after Agency (from the Project In-charge master), sortable A→Z / Z→A',
            'Staff without a Project In-charge or post stay at the bottom in either order',
        ]],
        ['version' => '2.6.1', 'date' => '2026-11-16 23:28', 'title' => 'Sort the Employee Management list', 'type' => 'fix', 'notes' => [
            'Employee Management: click Emp ID, Name or Post · project to sort A→Z; click again for Z→A (arrow shows the order)',
            'Employee IDs sort by number: SW-999, SW-1002, SW-1010',
        ]],
        ['version' => '2.6.0', 'date' => '2026-11-16 23:14', 'title' => 'Sub-bills in the agencies\' own format; Project In-charge module', 'type' => 'feature', 'notes' => [
            'Sub-bill PDF laid out like the SWACHHA sub-bills: text header (BILL, agency, address, mobile, EPF, ESIC, GST, PAN, CL&RA licence), SL No / Month / Date, one row per person with quantity, working days, rate per day and per month, Total Monthly Wages with total days, service charge @ 3.26% (excluding GST @ 18%), 18% GST, Grand Total, Round Up, amount in words, "Kindly Attention … (P.I)" and the signature box',
            'Tax invoice: the DES column shows the post only (e.g. PRS-II), as on the agencies\' tax invoice',
            'New "Project In-charge" module (was PI Allotment): every Project In-charge with their projects, the employees under each project and the sub-bills of those projects; a linked PI login sees the same sub-bills in the PI portal',
            'Add the CL&RA licence to an agency under Agencies → Edit → Registrations as a line "CL&RA No: K-438/2013"',
        ]],
        ['version' => '2.5.3', 'date' => '2026-11-16 22:58', 'title' => 'Old tax invoices get the SW/001/26-27 number', 'type' => 'fix', 'notes' => [
            'Tax invoices made before 16 Nov 2026 (e.g. SW26271/TI/01/2026) are renumbered to their work order\'s number for the month, SW/001/26-27 (the serial their sub-bills carry), linked to the work order and worked out again',
            '"Renumber and recalculate" button on such a tax invoice; "Recalculate all" on the Sub-bills page now also covers them; paid tax invoices are never changed',
        ]],
        ['version' => '2.5.2', 'date' => '2026-11-16 22:52', 'title' => 'Submitted bills can be recalculated too', 'type' => 'fix', 'notes' => [
            'Recalculate (one bill or "Recalculate all") now also corrects bills already submitted to the client, and their tax invoice if not paid; send the client the new PDF',
            'Paid bills and bills in a paid tax invoice are never changed',
        ]],
        ['version' => '2.5.1', 'date' => '2026-11-16 22:25', 'title' => 'Recalculate bills made before the new rules', 'type' => 'fix', 'notes' => [
            'Draft and approved bills made before 16 Nov 2026 can be worked out again with the current rules: service charge incl. GST (÷ 1.18), no rounding, new SW/001/26-27(CODE) number',
            'Sub-bills page lists those bills with a "Recalculate all" button; each bill also has "Recalculate with current rules"',
            'The bill keeps its status and attendance; a draft tax invoice holding it is updated too; submitted and paid bills are never changed',
            'Every recalculation is recorded in the Activity log (old and new total)',
        ]],
        ['version' => '2.5.0', 'date' => '2026-11-16 22:15', 'title' => 'Tax invoice in the agencies\' own format; no rounding of bill figures', 'type' => 'feature', 'notes' => [
            'Tax invoice PDF laid out like the SWACHHA tax invoice: agency header (GSTIN, PAN, EPF and ESIC numbers, mobile, e-mail), invoice no. SW/001/26-27, GeM contract no. and date, FROM / TO, bank details',
            'Particulars: every post of every project under the work order (project in brackets), number of manpower, total working days, per-day wage and amount; then EPF 12%, EDLI 0.5%, PF admin 0.5%, ESI 3.25%, sub total, service charge, total, CGST / SGST, grand total, rounding off and amount in words',
            'No figure on bills is rounded any more: everything is carried exactly, as in Excel, and only shown to 2 decimals; only the grand total is rounded ("Rounding off")',
            'The tax invoice works out service charge and GST once on the combined sub-total of its sub-bills (as on the agencies\' tax invoice), so it can differ from the sum of the sub-bills by a few paise',
            'The salary payout sheet and bank letter still pay each person in whole rupees',
        ]],
        ['version' => '2.4.0', 'date' => '2026-11-16 21:40', 'title' => 'Work orders, new bill numbers, PI Allotment, bank letter, Employee Management', 'type' => 'feature', 'notes' => [
            '"GeM Contracts" are now "Work Orders", Online (GeM) or Offline; their details are entered once and used for every project, sub-bill and tax invoice',
            'Work order page: projects, then the month\'s sub-bills (Add sub-bill), then the tax invoice (Add tax invoice) in one place',
            'New numbers: sub-bills SW/001/26-27(HTA), (CCRH), (MPH) and their tax invoice SW/001/26-27 (Z Plus: ZP/…); one tax invoice per work order per month; series restarts every April. Bills made earlier keep their numbers',
            'Service charge 3.85% now includes GST: charged as (amount × 3.85%) ÷ 1.18 = 3.26% on new bills; bills made earlier keep their amounts',
            'Employee IDs given automatically per agency: SW-1001, ZP-1001 …; existing IDs unchanged',
            'PI Allotment: each Project In-charge (name, designation, mobile, e-mail) entered once and mapped to projects under any work order; projects, certificates and PI logins use it',
            'Bank forwarding letter per project and month: SBI accounts (Emp ID, name, amount) and other banks by NEFT (account no., IFSC, amount)',
            'Employee Management: all employees in one list; pick one to see profile, KYC, month-wise attendance and month-wise bank payments',
            'Client form: all states and UTs with GST code, and their districts',
            'Bill, letter and project codes accept "/" and "-"; letter code up to 16 characters',
        ]],
        ['version' => '2.3.2', 'date' => '2026-11-04 07:10', 'title' => 'Security review fixes', 'type' => 'fix', 'notes' => [
            '5 wrong passwords for one account lock it for 15 minutes, from any computer (each IP was already limited)',
            'Change password is limited to 6 tries a minute',
            'Browser security headers on every page: HTTPS-only (HSTS), no framing by other sites, no content-type guessing',
            'Uploaded Excel files that were checked but never saved are deleted after a day',
        ]],
        ['version' => '2.3.1', 'date' => '2026-11-04 03:15', 'title' => 'Proof: no agency can enter the other\'s data', 'type' => 'fix', 'notes' => [
            'New test tries all 30 add / edit / delete / status actions of a Z Plus login on SWACHHA data and the other way round (60 attempts): every one is refused and no row changes',
            'An agency login\'s new records always get its own agency, even if a form does not send one',
        ]],
        ['version' => '2.3.0', 'date' => '2026-11-04 02:30', 'title' => 'Shared-computer safety and stricter agency isolation', 'type' => 'feature', 'notes' => [
            'Pages, PDFs and downloads shown to a signed-in user are never kept in the browser cache, so Back after signing out shows nothing',
            'Signing out tells the browser to clear this site\'s cache, cookies and storage',
            '"Keep me signed in" removed (office computers are shared); earlier remember-me cookies no longer work',
            'Changing a password (yourself, or an Admin resetting it) signs that user out on every other computer',
            'Form checks look records up through the agency filter, so another agency\'s records are rejected even before saving',
            'Database safety net: triggers refuse any contract, sub-bill, tax invoice or employee linked to a different agency\'s client or contract',
        ]],
        ['version' => '2.2.3', 'date' => '2026-11-04 01:15', 'title' => 'Month and year picker', 'type' => 'fix', 'notes' => [
            'Month fields on Attendance, Sub-bills and Tax invoices open a calendar: choose the year with the arrows, then click the month',
            'Works the same in every browser, including Firefox (which has no built-in month calendar)',
        ]],
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
