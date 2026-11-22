# Changelog

Versions follow MAJOR.MINOR.PATCH. Dates are Indian Standard Time.

## 2.50.0 (2026-11-22): Post-wise summary for GeM returns (Excel and PDF)

- New page Sub-bills → "Post-wise summary": choose the month, the work order, then one sub-bill or all, and one post or all
- One row per post: staff, days paid, wages, EPF, ESIC, sub total, service charge, value before GST, CGST/SGST (or IGST) and total, with a total row and, below it, the same figures as stored on the sub-bills to compare
- Wages, EPF and ESIC are as billed for each person; the service charge and GST are worked out per post with each sub-bill's own rates, so the posts add up to the bills
- Download Excel and Download PDF (landscape, agency header) for filing GeM returns online; "Post-wise summary" button on each work order row of the Sub-bills list
- Read only: no amounts or numbers change. Each login sees only its own agency's work orders

## 2.49.0 (2026-11-22): Attendance sheets in the month and work order ZIPs

- The work order ZIP and the whole-month ZIP now also hold each project's attendance sheet for the month (attendance-sheets/attendance-SW-001-26-27-HTA.pdf, the same Monthly Attendance and Working Certificate as on the attendance page, without bank details)
- If the PI's signed certificate was uploaded on the attendance sheet, it is added next to it (…-signed-by-PI.pdf / .jpg)
- No amounts or numbers change

## 2.48.0 (2026-11-22): Post-wise sub-bills: all posts in one PDF

- Post-wise sub-bills page: green "All posts (PDF)" button gives every post of the month in one PDF, whatever post is chosen on the page; "This post (PDF)" when a post is chosen
- The PDF (landscape) starts with a summary line per post (projects, staff, days paid, wages), then each post's projects and sub-bills with a total per post, the grand total and the wages in words
- Fixed: the post name cell no longer spans rows, so the columns stay in place when the PDF runs onto the next page
- Internal report; nothing in the bills changes

## 2.47.1 (2026-11-22): Green Sub-bills statement button

- The "Sub-bills statement (PDF)" button is green on the Sub-bills page and the Month bill page

## 2.47.0 (2026-11-22): Sub-bills statement PDF

- New "Sub-bills statement (PDF)": every sub-bill of the agency's month in columns, as on the Sub-bills page: sub-bill no., project, staff, wages, EPF, ESIC, service charge, before GST, CGST, SGST, bill amount, tax invoice and status
- Grouped by GeM contract in serial order, a total row per work order and the month total with the amount in words; landscape A4, the heading repeats on each page
- Buttons next to "Show <month> month bill" on the Sub-bills page and at the top of the Month bill page
- Internal statement; figures as stored on the bills, nothing changes

## 2.46.1 (2026-11-22): PDF tables keep their columns on the next page

- Fixed: when the consolidated tax invoice statement (or a long tax invoice) ran onto a second page, every row there shifted one column left and the Amount column was empty; the HSN code cell no longer spans many rows, so the columns stay in place on every page
- The HSN code still shows once at the top and the GST rows still read as one block; no amounts or numbers change

## 2.46.0 (2026-11-22): Consolidated tax invoice statement for the month

- Month bill page: new "Consolidated tax invoice statement" (screen and PDF) in the regular tax invoice layout: agency header, FROM / TO, bank details, posts of all work orders, EPF 12% / EDLI 0.5% / admin 0.5%, ESI, sub total, service charge, CGST / SGST, grand total and amount in words
- The same post at the same rate across all work orders is one row (manpower and days added up), as on each tax invoice
- It lists the tax invoices it combines (number, GeM contract, amount); every figure is the sum of those tax invoices
- Titled "Consolidated tax invoice statement" with no new number: it is not a separate tax invoice, so the month is not billed twice under GST

## 2.45.0 (2026-11-22): PDFs of a month split by work order

- Sub-bills (with a month chosen): "Download PDFs (ZIP)" on each work order's row gives just that work order's sub-bills, covering letters and tax invoice (e.g. SW-GEMC-511687731718030-documents-2026-10.zip)
- The whole-month ZIP now has a folder per work order (GEMC-…/sub-bills, covering-letters, tax-invoice), with the bank letter at the top
- No amounts or numbers change

## 2.44.2 (2026-11-22): Month ZIP: no time-out, faster letterhead

- Fixed: the month ZIP stopped with "Maximum execution time of 30 seconds exceeded"; it may now take up to 10 minutes
- A large letterhead logo (e.g. a 7 MB scan) is shrunk to print size (at most 1200 px wide) once per download and reused for every PDF, instead of being read and decoded in full for each one: a 55-PDF month now takes about 14 seconds in the test; the uploaded logo file itself is not changed
- No amounts or numbers change

## 2.44.1 (2026-11-22): Month ZIP no longer runs out of memory; smaller PDFs; button colours

- Fixed: "Download all PDFs of this month (ZIP)" stopped with "Allowed memory size exhausted" for a full month (all PDFs were held in memory); now each PDF is written to a temporary file as it is made (tested with 88 PDFs)
- PDFs embed only the letters they use: a sub-bill or tax invoice PDF is now about 40–70 KB instead of about 1.5 MB, so the month ZIP went from about 133 MB to about 3 MB in the test; the look of every PDF is unchanged
- Fixed: the "Show <month> month bill" button (and the "Make tax invoice" buttons on the work order rows) on the Sub-bills page showed white until hovered; they are blue now
- No amounts or numbers change

## 2.44.0 (2026-11-22): Month bill: all tax invoices of the month in one sheet

- New Month bill (Billing): for one agency and month, one row per work order with its tax invoice no., sub-bills, staff, wages, EPF, ESIC, service charge, before GST, CGST, SGST, sub-bills total and tax invoice amount, and the month total
- Button "Show <month> month bill" at the end of the Sub-bills list (one per agency when both are listed)
- Download as PDF: landscape A4 on one page, agency text header, month total in words, signatory
- Internal summary only: not a GST invoice, no number; figures are read from the sub-bills and tax invoices already made, nothing is worked out again. Sub-bills not yet in a tax invoice are pointed out
- Each agency sees only its own month bill

## 2.43.1 (2026-11-22): Staff column on the Sub-bills list

- Sub-bills list: new Staff column after Month with the number of employees each sub-bill is worked out for; click it to see their names, posts and paid days
- The work order total rows and the total at the bottom add up the staff too
- Look only: no amounts or numbers change

## 2.43.0 (2026-11-22): Sub-bills take a project's new short name

- Changing a project's short name (Edit project) now renames its open sub-bills: SW/004/26-27(SW/007/26-27) becomes SW/004/26-27(SDOH); the serial (004) never changes
- Paid bills, and bills in a paid tax invoice, keep their number; the message lists what was renamed and what was kept
- A tax invoice holding a renamed sub-bill is worked out again (its own number stays); a renamed bill that was approved needs approving again; each rename is in the Activity log
- Short project names can no longer contain "/" (it goes inside the sub-bill number); client and agency codes are unchanged. A project that has "/" in its short name must get a new one the next time it is edited

## 2.42.0 (2026-11-22): Bill components and totals on the Sub-bills and Tax Invoices lists

- Sub-bills list: columns Wages, EPF, ESIC, Service charge, Before GST, CGST, SGST (IGST when any) and Bill amount for every sub-bill (Agency, Client and Work order columns dropped: they head their blocks)
- Each work order's group ends with a total row of every column and a dark line; "Make tax invoice" on the work order's heading row
- Dark total row at the bottom: all sub-bills listed (choose a month for the month total)
- Tax Invoices list: CGST and SGST (IGST when any) in their own columns, and a dark total row at the bottom (count of sub-bills, before GST, GST, invoice amount)
- Look only: no amounts or numbers change

## 2.41.0 (2026-11-22): One-page tax invoice, bills listed in serial order

- Tax invoice PDF always fits one page: with more post rows (and the contract balance) the type and spacing get smaller on their own; checked with 12 and 20 posts
- Sub-bills menu: inside each agency and client, a group per GeM contract (contract no., number of sub-bills, total), bills in serial order SW/001/26-27(HTA), SW/001/26-27(NTM), SW/002/26-27(…), …; groups follow the serial of their first bill
- Tax Invoices menu: in serial order SW/001/26-27, SW/002/26-27, …; new Sub-bills column right after the number: click the count to see the sub-bills it combines (each opens the sub-bill)
- Fixed: the Sub-bills list showed the contract number again as "Bid …" under each work order
- Look only: no amounts or numbers change

## 2.40.0 (2026-11-22): No more staff on a post than sanctioned

- A post with 4 sanctioned takes at most 4 staff: the 5th is refused with "Post already filled: <post> (<project>) has 4 sanctioned and 4 already placed or waiting for approval"
- Checked everywhere staff are placed: Add employee, Edit (moving to another post or setting someone Active again), the one-page work order entry, Data Upload (rows above in the same file count too) and the Super Admin's approval
- New staff waiting for approval, and staff waiting to be moved onto the post, already hold their place, so a post cannot be over-filled through the approval queue
- Employee form: full posts show "— POST FULL" and cannot be chosen (the employee's own post stays selectable)
- The sanctioned number of a post cannot be lowered below the staff already on it
- Posts already over-filled from before stay as they are (still red): their staff can be edited, set Inactive or deleted, but nobody new is added until there is room

## 2.39.1 (2026-11-22): Sub-bill service charge label same as the tax invoice

- Sub-bill PDF now says "Service charge @ 3.85% on sub total (Excluding GST)", exactly like the tax invoice (it said "@ 3.26% (excluding GST @ 18%)")
- Checked: both bills already used the same formula, sub total × 3.85% ÷ 1.18; the sub-bills' service charges add up to the tax invoice's (e.g. 2363.35 + 2926.16 + 1693.35 = 6982.86)
- Wording only: no amount changes; old bills made before the 3.85%-includes-GST rule still say "@ 3.85% on sub total"

## 2.39.0 (2026-11-22): Laptop demo: full month, sub-bills and tax invoices

- New command for a laptop copy of the data: php artisan demo:full-month 2026-10 gives every active employee full attendance, locks the month, makes a sub-bill per project (SW/001/26-27(HTA) …) and one tax invoice per work order clubbing them, with demo approvals marked "(demo)"
- Refuses to run on the live site; asks to type DEMO first; months already locked or billed are left as they are
- Guide docs/guides/LAPTOP_DEMO.md: copy the live database to the laptop safely, run the demo, look at the bills
- Nothing changes on the live site

## 2.38.0 (2026-11-22): Coloured post rows and click-to-see names

- Post rows now carry a coloured strip: green = exactly filled, amber = vacant, red = more staff than sanctioned (Posts page, work order page, project rows on Work Orders)
- The Filled number is coloured the same way and can be clicked: a small list opens with the staff names (each opens the employee) and their Emp IDs; click anywhere else or press Esc to close
- Same click-to-see list on: the "x/y Filled" tag of each project card on Posts, Filled / Total on the Work Orders project rows, the staff tag on Attendance cards, and on Project In-charge the "N projects" tag (project names, open the work order) and "N staff" tag
- Look only: no data, amounts or numbers change

## 2.37.0 (2026-11-22): Over-filled posts highlighted

- A post with more staff placed than sanctioned is now highlighted in red: the row is shaded with a red edge, the Filled number is red and the Vacant column says "N over"
- Posts page: new tab Over-filled, a red "N over" tag on the project card, and "N more staff than sanctioned" in the Filled box
- Work Orders page: red "N over" tag on the contract and on each project row, and an Over-filled choice in the Status filter
- Work order page: over-filled post rows shaded red, and names that appear twice on the same post (capitals and Dr./Mr./Mrs./Ms. ignored) tagged "same name?" to help find staff entered twice
- Nothing is blocked or changed: it only shows where to check

## 2.36.3 (2026-11-22): Employees list: Post, Project and Project In-charge columns

- Employees list: Post, Project and Project In-charge are now three separate columns, in that order (the project and PI were small grey text under the post before)
- Project In-charge comes from the Project In-charge master, or the PI name typed on the project if none is linked
- Fixed column widths so every column lines up; on a small screen the table scrolls sideways
- No data changes

## 2.36.2 (2026-11-22): Employee Management: Project in its own column

- All employees list: Post and Project are now separate columns (Project right after Post), and both can be sorted by clicking the heading
- The employee details card shows Post and Project on separate lines
- Fixed column widths so every column lines up; EPF / ESIC / TDS shown as small green (on) or grey (off) tags on one line
- No data changes

## 2.36.1 (2026-11-22): Correct times in the version history; Posts columns lined up

- Version history and the sidebar now show the real Indian time (IST) of each release; versions 2.2.0 to 2.3.1 and 2.16.1 to 2.36.0 had wrong times, some even dated a day or two ahead
- A check now stops any version being dated in the future
- Posts page: Sanctioned, Filled, Vacant, Rate and Actions columns now line up exactly from one project card to the next
- No data changes

## 2.36.0 (2026-11-22): Posts, Employees, Employee Management and Attendance in the Work Orders style

- Same design, colours and fonts as Work Orders on four more pages: a heading with an icon, five figure boxes, tabs, filter dropdowns in one bar
- Posts: figures (posts, projects, sanctioned, filled, vacant), tabs All / Vacant / Fully filled, and one card per project with its posts, filled count, Add post and Open work order
- Employees: figures (total, active, placed on posts, on notice, bank details missing), status tabs, search, and the list in one table with a light agency › client row; EPF / ESIC / TDS switches, Edit and Delete as before
- Attendance: figures for the month (projects, staff, waiting, locked, billed), tabs Not started / In progress / Submitted by PI / Locked, and one card per project with Start sheet or Open sheet (and the sub-bill once billed)
- Employee Management: figures (total, active, EPF on, ESIC on, bank details missing) and the details, month-wise pay and all-employees tables in the new cards
- Look only: no amounts, numbers or data change

## 2.35.0 (2026-11-22): Project In-charge page in the Work Orders style

- Project In-charge page now has the same design, colours and fonts as Work Orders: a heading with an icon, five figure boxes (In-charges, projects allotted, staff under them, with PI portal login, projects without PI), tabs All / Active / Inactive, Agency and Project In-charge dropdowns, and a search box
- Each Project In-charge is a card that opens to show their projects, the staff under each and the latest sub-bills; Edit / allot projects and Delete stay on the card
- The Work Orders styles now live in the shared stylesheet so both pages stay alike; no data changes

## 2.34.1 (2026-11-21): Contract number instead of bid number in post lists

- Employee form (GeM post list and "Add new post"), attendance sheet heading, Make tax invoice, the Dashboard's recent bills and the Sub-bills waiting lists now show the GeM contract number (GEMC-…) instead of the bid number
- No data changes

## 2.34.0 (2026-11-21): Move a project to another work order

- Work order page (Super Admins): "Move to another work order" on each project, to bring projects out of the stand-in contract numbers made when one contract could hold only one project (e.g. …8031, …8032 into …8030)
- Posts, staff, attendance, PI and appointment letters move with the project; letter numbers do not change; the emptied work order can then be deleted
- Refused when the project already has sub-bills under the old work order, or the target already has a project with the same short name; recorded in the Activity log
- Changes which work order a project belongs to; take a database backup before moving

## 2.33.1 (2026-11-21): Delete button on the employee edit page

- Employees → Edit: a Delete button after "Save employee" (Super Admins and Accounts), for someone entered by mistake; greyed out with the reason once the person is on attendance, bills or letters (set them Inactive instead)
- A waiting approval of a deleted employee is closed; recorded in the Activity log

## 2.33.0 (2026-11-21): Approvals: new employees in a clean table

- Approvals page: new employees waiting are shown in a table per agency with columns Employee, Post (project, rate), Bank, Name as per bank, A/c no., IFSC, EPF, ESIC, TDS (On / Off badges)
- Actions at the end of each row: Approve, Return (asks for the reason) and Delete for an employee entered by mistake (only if not yet on attendance or bills; the request is then closed as withdrawn)
- Rows changed after sending are highlighted and say what changed
- No data changes

## 2.32.2 (2026-11-21): New staff corrected while waiting: approval kept up to date

- Correcting a new employee while the approval is waiting (EPF / ESIC / TDS switch on the list, bank, post, rate) now updates the waiting request, so the Super Admin approves the latest details instead of getting "changed after sending"
- A request that is already out of date says exactly what changed (on the Approvals page and the employee page), and the employee page now has "Send again for approval" for it
- No data changes

## 2.32.1 (2026-11-21): Approving new staff: false "changed after sending" fixed

- Approving a new employee could fail with "The employee's details were changed after sending" although nothing was changed: staff saved without the EPF / ESIC (or older imports without TDS) switch were recorded as blank, while the database stored "off"
- New requests now record the details exactly as saved; requests already waiting are accepted when the only difference is such a blank switch, and still refused if anything really changed
- No data changes

## 2.32.0 (2026-11-21): Allot projects to a Project In-charge by work order

- Add / Edit Project In-charge: choose a work order from a dropdown, then tick its projects; repeat for other work orders
- All allotted projects show as chips under "Allotted projects" (project code, name, work order), each with a remove button; projects already with another In-charge are marked
- Saving works as before; no data changes

## 2.31.4 (2026-11-21): Delete button next to Status

- Project In-charge edit page: the Delete button is now at the top, next to Status; pointing at it explains why it is greyed out when projects are still allotted
- Deleting removes only the Project In-charge card; projects are never deleted

## 2.31.3 (2026-11-21): Delete a Project In-charge from its edit page

- "Edit / allot projects" page of a Project In-charge now has a Delete button (Super Admins and Accounts); it works only when no project is allotted, otherwise it explains what to untick first
- Recorded in the Activity log; no other data changes

## 2.31.2 (2026-11-21): Project In-charge filter

- Project In-charge page: new "Project In-charge" dropdown; choose one to see only that PI with their projects, employees and sub-bills; "Clear" shows all again
- No data changes

## 2.31.1 (2026-11-21): Work order shown by its contract number

- Posts, Employees, Attendance, Sub-bills, Clients and the Dashboard now show the work order by its GeM contract number (GEMC-…) instead of the bid number; the bid number is shown underneath in small text
- No data changes

## 2.31.0 (2026-11-21): New look on every page

- The Work Orders design now runs through the whole app: navy side menu with the current page in blue, blue buttons and links, Inter font, light grey background, rounded cards with soft borders, cleaner table headings
- Figure cards on the dashboard and reports are tinted with an icon; agency and client headings on the lists are lighter and easier to read
- Sign-in, company choice and error pages in the same colours
- No change to any page's contents, buttons, data or permissions

## 2.30.1 (2026-11-21): TDS off by default for new staff

- New employees (employee form, one-page work order entry, Data Upload, GeM import) now start with TDS switched off; switch it on per person where TDS applies
- Existing employees keep their current TDS switch
- Changes salary payouts only for new staff whose TDS is left off (no 2% deduction)

## 2.30.0 (2026-11-21): New look for Work Orders (Contracts)

- Work Orders page redesigned: heading "Contracts – <agency>" with Add Contract and Export (Excel of the list), figure cards (contracts, projects, posts, filled and open posts with percentages)
- Tabs All / Active / Completed / Draft (no projects yet), Agency, Status (open posts, fully filled, ending within 30 days), Sort, search across contract, bid, project and PI, list or grid view, 10 work orders per page
- Each work order is a card, collapsed by default: contract no., GeM / offline, bid no., agency, filled posts and a progress bar, Add Project and Open; opening it shows the projects with In-Charge, posts filled and View / Edit / more (posts, employees, attendance, sub-bills)
- Nothing else changes: same pages behind the buttons, same data and permissions

## 2.29.0 (2026-11-21): Bank letter in Excel, appointment no. 001, longer codes

- Bank Letter: new "Excel" button next to "Bank letter PDF": one sheet for SBI accounts, one for NEFT and one for staff with missing bank details (only after the salary payout is approved, like the PDF)
- Appointment letter numbers now have three digits: SWH/NIHR/<short project name>/001/26 (new letters; letters already numbered keep their number)
- Short project name, client code and letter code: up to 20 characters, and may contain spaces and / - . _ ( ) & + # , ; the boxes are wider
- No bill amount or data changes

## 2.28.0 (2026-11-21): Edit buttons everywhere; makers can delete unused records

- Edit buttons: on each work order and each of its projects (Work orders list), on each post (Posts list, opens the post's edit form on its work order page) and on each employee (Employees list); clients and Project In-charge already had them
- Accounts logins (SWACHHA / Z Plus makers) can now switch on "Show delete options" and delete records that are not in use: work orders, projects, posts, clients, Project In-charge and employees of their own agency
- Deleting bills, tax invoices and agencies stays with the Super Admins; paid or used records can never be deleted; every delete is in the Activity log

## 2.27.0 (2026-11-21): Post-wise filter and post-wise sub-bills

- Posts: new "Post" filter; choosing a post (e.g. Project Technical Support-II) lists it in every project, with a summary of the projects, sanctioned and filled
- Sub-bills → "Post-wise sub-bills": for a month (and optionally one post and agency), each post with the projects, sub-bill numbers, rates, staff, days paid and wages billed, with totals; PDF for your own records (marked not for submission)
- No bill amount, number or data changes

## 2.26.0 (2026-11-21): Acceptance letter with every appointment order

- Every appointment order now has a second page: the appointee's "Acceptance of Appointment" letter to SWACHHA / Z Plus, set out like a government letter (From, Employee ID, Date, To, Sub, Ref to the order no. and date, numbered paragraphs, particulars table, enclosures, Yours faithfully, Copy to, office-use line)
- It states the post, project, place and joining date, the EPF / ESI / TDS that apply, and has boxes for bank, IFSC, UAN, ESI IP and nominee; the appointee signs it and returns it with the listed documents
- The order itself stays on one page; its last term now points to the acceptance letter
- No bill amount, number or data changes

## 2.25.0 (2026-11-21): One-page entry made simpler

- Add work order now goes step by step: 1. GeM contract / work order number, 2. contract details, 3. projects, each with its Project In-charge, posts and staff
- One PI for several projects: a new PI typed in one project can be chosen from the list in the others, and is saved once; typing the name of an already saved PI uses that PI
- The same one-page entry is on every work order's page, to add more projects, PIs, posts and staff later without switching menus
- No bill amount, number or data changes

## 2.24.0 (2026-11-21): Government-style appointment order

- Appointment letter is now an "APPOINTMENT ORDER" in a government office style: No. and Dated, Sub and Ref (GeM contract), numbered terms, signatory name and title, Copy to, and an Acceptance slip; still one page on the agency letterhead
- New term 3 "Statutory deductions": EPF 12% (on wages up to Rs. 15,000), ESI 0.75% and TDS 2%, each shown as Applicable or Not applicable from the employee's switches, with the amounts and the net monthly payment worked out exactly as on the salary payout sheet
- Reference number unchanged: SWH/NIHR/<short project name>/01/26, numbered per project
- No bill amount, number or data changes

## 2.23.0 (2026-11-21): Contract balance on the tax invoice; same post in one row

- Tax invoice PDF and page: "Contract value position": total tender value (incl. GST), less tax invoices billed before, opening balance, less this tax invoice, net balance; the net balance is next month's opening balance, through the contract period
- Shown once the total contract value is entered on the work order
- Tax invoice particulars: the same post at the same rate in different projects is now one row, with the manpower and days added up (also on earlier tax invoices' PDFs)
- No amounts or totals change; only how the rows are grouped

## 2.22.0 (2026-11-21): Resignation date on attendance

- Attendance (PI portal and agency): a "Last working day" box for each person; enter it for anyone who resigned or left in that month
- Days after the last working day are not paid, whatever code is entered; they show in red on the attendance grid and the attendance certificate
- The sub-bill (page and PDF) shows "Resigned, last working day" in red under the person's name; the tax invoice PDF lists staff who resigned that month, with days paid
- When that month's attendance is locked the person becomes Inactive and is not on next month's sheet
- Amounts change only for the staff whose last working day is entered (paid up to that date)

## 2.21.0 (2026-11-21): Work order, projects, posts and staff on one page

- Add work order now has a "Projects, posts and staff" section: for each project the short name, project name and Project In-charge (choose one or type a new name and mobile), its posts (designation, sanctioned, rate) and its staff (name, post, joining date, mobile, EPF / ESI / TDS)
- Everything is saved together; if anything is wrong nothing is saved and the form keeps what was typed
- New staff get the next SW-/ZP- ID and stay Inactive until a Super Admin approves them, as before; bank, PAN and Aadhaar are completed later under Employees
- The client is chosen automatically when the agency has only one; Clients page has an "Add work order, projects and staff" button
- No bill amount, number or data changes

## 2.20.0 (2026-11-21): Merge into one client

- Clients → "Merge into one client" (developer only): per agency, choose the client to keep; work orders, employees and tax invoices of the other clients move to it, it is renamed "Administrative Officer NIHR - Regional Medical Research Centre, Bhubaneswar" and the emptied clients are removed
- Shows what each client has before anything moves; type MERGE to confirm; every move is in the Activity log
- Bill amounts and numbers are unchanged. Take a database backup before using it

## 2.19.0 (2026-11-21): Agencies page, projects on work orders, short project name

- Agencies page shows each agency's full details (address, contacts, GSTIN, PAN, registrations, signatory, bank, bill prefix) with an Edit button; client names and Add client removed
- Work orders list shows each work order's projects (short name, project name, Project In-charge, posts filled) instead of the client
- "Code" of a project is now called "Short project name" (used in sub-bill numbers and appointment letters)
- Clients no longer need a bill code; one is filled in automatically
- No bill amount, number or data changes

## 2.18.1 (2026-11-18): Error pages, health check and icon

- Error pages (not found, not allowed, page expired, too many attempts, something went wrong, maintenance) now match the app's design and never show technical details
- New /health address for Coolify and uptime monitors; it also checks that the database answers
- Real Z+ icon in the browser tab and on phones
- CHANGELOG.md and a rewritten README for developers
- No bill amount, number or data changes

## 2.18.0 (2026-11-18): Choose company after signing in

- Logins for both agencies (the Super Admins) now choose the company after signing in: SWACHHA, M/S Z Plus Surakhya Seva or Both agencies
- With one company chosen, every page, list, approval and dropdown shows only that company, exactly like the agency's own login; header shows the company with a "Change" link, and "Change company" is in the menu
- GeM import and adding an agency need "Both agencies"
- Agency logins (SWACHHA / Z Plus makers) are unchanged: they always see only their own agency
- No bill amount, number or data changes

## 2.17.2 (2026-11-18): Own agency filled in; work orders told apart

- Agency logins (SWACHHA / Z Plus): the Agency box on every list page (Employees, Attendance, Sub-bills, Tax invoices, Posts, Work orders, Clients, Project In-charge, Bank Letter) shows your own agency instead of "All agencies"
- Work order filters show bid no. · contract no. · client, so several contracts under the same GeM bid no longer look like duplicates
- No bill amount, number or data changes

## 2.17.1 (2026-11-18): Add post: agency filled in

- Add new post box: the Agency field now shows the login's own agency (SWACHHA or Z Plus) instead of a blank box
- No bill amount, number or data changes

## 2.17.0 (2026-11-18): Makers see what is waiting for approval

- Approvals page for makers (Accounts / Operator): new list "Sent – waiting for Super Admin approval" with each item, who sent it, when, and how many days it has waited (yellow after 3 days)
- Sidebar: grey count of items waiting for a Super Admin, red count of items returned to you
- Dashboard card for makers: "X waiting for Super Admin · Y returned to you", opens Approvals
- Each maker sees only their own agency's items. Super Admin pages unchanged. No bill amount, number or data changes

## 2.16.1 (2026-11-18): Edit button for the second owner

- Users list: the developer now sees the Edit button on the other Super Admin's row (to correct the name or reset the password); nobody else can open Users
- No bill amount, number or data changes

## 2.16.0 (2026-11-18): Two levels: makers and Super Admins

- Two levels only: the maker of each agency (an Accounts login) enters staff, attendance, bills and salaries and sends them; a Super Admin approves, returns or deletes
- Two Super Admins for both agencies (the owner and the second owner). Nobody approves what they sent themselves
- The app's developer (the owner) alone keeps Users, making Super Admins, the Activity log, Data Upload and GeM import; the developer can reset the second owner's login
- Sub-bills: the extra internal "Approve" step is removed: draft → (owner approval) → submitted → paid. Bills already marked approved continue to submitted
- No bill amount, number or data changes

## 2.15.1 (2026-11-17): Security hardening

- Security check of the whole app; no bill, amount, number or approval changes
- Closed a way to hide script in a project name (Delete project pop-ups); browser security policy (CSP) added on every page
- Activity log Excel export: names and notes can never become Excel formulas
- PAN shown in full only to Admin, Accounts and the owner (Operators see the last 4 characters) and masked in the Activity log
- Laravel and Symfony updated with their security fixes; the app answers only for its own domain; heavy downloads limited to 20 a minute; kept out of search engines; upper limit on rates

## 2.15.0 (2026-11-17): Owner approval of staff and pay changes

- New employees (added by hand, by Data Upload or by the GeM workbook import) stay Inactive – not on attendance or bills – until the owner approves; then they become Active
- Changes of an employee's post, own rate, bank name, account holder, account number, IFSC and the EPF / ESIC / TDS switches wait for the owner; the old details stay in use until approved. Name, mobile, address and other details are saved at once
- A change of a post's rate waits for the owner (designation and sanctioned number are saved at once)
- The Approvals page shows each change as "now → changed to"; returned changes are never applied and the reason is shown to whoever made them
- Strict four-eyes as for bills; changes made some other way meanwhile are never overwritten. Held bank account numbers are stored encrypted

## 2.14.0 (2026-11-17): Owner approval (maker–checker)

- From October 2026 the owner (Super Admin) approves before anything goes further; earlier months stay exactly as they were
- Attendance: locking a month sends it to the owner; the sub-bill is made when the owner approves. If returned (with a reason), the month opens again for correction
- Sub-bills and tax invoices: "Send for approval"; only approved ones can be marked submitted, e-mailed or put in the GeM bill pack. A tax invoice is approved after its sub-bills
- Salary payout sheets and bank letters: approved per agency and month on the Bank Letter page before they can be downloaded
- Anything changed after approval (figures, bank account, attendance) must be approved again
- Strict four-eyes: whoever sent it can never approve it. New "Approvals" menu: the owner's inbox with Approve / Return, and makers see what was returned to them
- Super Admin can make a second owner login a Super Admin (Users → Edit). Every step is in the Activity log. No bill amount or number changes

## 2.13.0 (2026-11-17): GeM contract tools: import, papers, bill pack, reminders

- Work orders: service start and end date and total contract value; "Ends in N days" warning from 30 days before the end date (work order list, work order page and dashboard)
- Import posts from the GeM contract PDF: upload it on the work order page, check the posts (designation, number, basic pay), service period and value, then add the ticked posts to a project. The app checks its reading against the contract's printed total
- Monthly papers on each work order: salary payment proof, EPF challan, ESIC challan, logbook, service feedback – checklist per month (EPF/ESIC only when someone has it switched on)
- "Download bill pack (ZIP)": tax invoice, sub-bills, covering letters (signed copies where uploaded), PI attendance certificates and the month's papers, for uploading on GeM
- Dashboard reminders: last month's wages due by the 7th and EPF/ESIC by the 15th per work order, work orders ending soon, posts with more staff than sanctioned
- Admin / Accounts only (removing a paper: Admin); salary proofs only for those who may see bank details; every upload, download and removal is in the Activity log. No bill amount or number changes

## 2.12.1 (2026-11-17): Search box for employees

- Employee Management: type part of the name, Emp ID, post, project or agency in the Employee box; matching employees drop down as you type
- Click one (or use the arrow keys and Enter) to open all their details, as before

## 2.12.0 (2026-11-17): E-mail to client

- "E-mail to client" box on every sub-bill (sends the sub-bill + covering letter) and tax invoice (sends the tax invoice + all its sub-bills), from the agency's own Gmail
- The DSC-signed copy is attached when it was uploaded after the last change; otherwise the PDF is made fresh and marked "not signed"
- To (client's e-mail from the Clients page), CC, subject and message are filled in and can be changed; every e-mail (sent or failed) is listed on the page and in the Activity log
- Switched off until each agency's Gmail address and Gmail app password are added on the server (Coolify → Environment Variables: AGENCY_MAIL_SWH_USERNAME / _PASSWORD, AGENCY_MAIL_ZPS_USERNAME / _PASSWORD); the password is never stored in the app or shown
- Admin / Accounts only; no change to any bill, amount or number

## 2.11.0 (2026-11-17): Month ZIP and DSC-signed copies

- Download all PDFs of a month as one ZIP per agency (sub-bills, covering letters, tax invoices and the bank letter) from the Sub-bills page (choose month and agency) or the Bank Letter page – to sign them together with the DSC token (Adobe Reader / JSignPdf)
- "Signed copy (DSC)" box on every sub-bill, tax invoice and monthly bank letter: upload the signed PDF; the app keeps it, shows "Signed ✓" and warns if the bill changed after signing
- Admin / Accounts only (bank letter: those who may see bank details); every upload and download is in the Activity log

## 2.10.0 (2026-11-17): Super Admin delete options

- Super Admin only: a "Show delete options" switch in the sidebar; Delete buttons appear only while it is on
- Delete agencies, clients, work orders, Project In-charges, projects and employees – only when nothing depends on them; otherwise the button is disabled and says what to remove or move first
- Employees with attendance, bills or letters are kept (set them Inactive); only records entered by mistake can be deleted
- Sub-bills and tax invoices: the Super Admin can delete any that is not paid (a sub-bill leaves its tax invoice, which is worked out again or deleted when empty)
- Every delete is recorded in the Activity log; other Admins, Accounts and Operators are unchanged

## 2.9.4 (2026-11-17): Only the Super Admin can delete a project

- "Delete project" and "Delete this empty project" are shown to and allowed for the Super Admin only; other Admins, Accounts and Operators no longer see them

## 2.9.3 (2026-11-17): Delete an empty example project

- Admin: "Delete project" on the work order page and "Delete this empty project" on the Attendance list, for projects entered by mistake or as an example
- Only when the project has no attendance, no sub-bills and no employees; its empty posts are removed with it; recorded in the Activity log

## 2.9.2 (2026-11-17): TDS on/off per employee

- TDS switch per employee, like EPF and ESIC: on the Employees list (EPF / ESIC / TDS switches) and in the employee form (KYC & statutory tab)
- On for everyone already in the app and for new employees, so nothing changes until a person is switched off; switched off = no TDS on the salary sheet, bank letter and Employee Management
- Data Upload: new "TDS" Yes/No column in the employee template (blank on new staff = Yes)

## 2.9.0 (2026-11-17): TDS 2% on salaries

- Salary payout sheet: TDS 2% of the earned amount (rounded to the rupee) is deducted from every employee; Net pay = earned − EPF 12% − ESIC 0.75% − TDS 2%
- The bank letters (per project and the Bank Letter module) and Employee Management use the same net pay, so the amounts sent to the bank are after TDS
- Bills to the client are not affected

## 2.8.0 (2026-11-17): Bank Letter module: all employees of the month in one letter

- New "Bank Letter" menu (Admin / Accounts): choose agency and month to see every employee of all its projects with net salary, bank, account no. and IFSC (other banks), with SBI / NEFT totals
- One salary transfer letter PDF on the agency's own letterhead (SWACHHA or Z Plus): SBI accounts by Emp ID, other banks by NEFT with account no. and IFSC; projects can be left out by unticking
- Projects whose month is not locked yet, and staff without bank details, are listed as not included
- The per-project bank letter on the attendance page still works

## 2.7.0 (2026-11-16): Reset a project's month

- Attendance month page → "Reset this month" (Admin only, type RESET to confirm): deletes that project's month – the attendance of all its employees, the corrections, the uploaded PDFs and its sub-bill – so the month can be started again
- Works for draft, approved and submitted sub-bills; never for paid ones. The sub-bill is taken out of its tax invoice: a tax invoice left empty is deleted, otherwise it is worked out again
- The work order's bill number for the month is kept, so the new sub-bill gets the same number
- Every reset is recorded in the Activity log

## 2.6.3 (2026-11-16): Enter a work order once, add projects to it

- Work Orders list: "Add project" on every work order, so projects using the same GeM bid and contract number go under it without typing them again
- Add work order: typing a bid or contract number that is already saved shows it, with a link to add your project there; saving a duplicate explains the same
- Work order page: "Add project" button at the top

## 2.6.2 (2026-11-16): Project In-charge column on Employee Management

- Employee Management list: new "Project In-charge" column after Agency (from the Project In-charge master), sortable A→Z / Z→A
- Staff without a Project In-charge or post stay at the bottom in either order

## 2.6.1 (2026-11-16): Sort the Employee Management list

- Employee Management: click Emp ID, Name or Post · project to sort A→Z; click again for Z→A (arrow shows the order)
- Employee IDs sort by number: SW-999, SW-1002, SW-1010

## 2.6.0 (2026-11-16): Sub-bills in the agencies' own format; Project In-charge module

- Sub-bill PDF laid out like the SWACHHA sub-bills: text header (BILL, agency, address, mobile, EPF, ESIC, GST, PAN, CL&RA licence), SL No / Month / Date, one row per person with quantity, working days, rate per day and per month, Total Monthly Wages with total days, service charge @ 3.26% (excluding GST @ 18%), 18% GST, Grand Total, Round Up, amount in words, "Kindly Attention … (P.I)" and the signature box
- Tax invoice: the DES column shows the post only (e.g. PRS-II), as on the agencies' tax invoice
- New "Project In-charge" module (was PI Allotment): every Project In-charge with their projects, the employees under each project and the sub-bills of those projects; a linked PI login sees the same sub-bills in the PI portal
- Add the CL&RA licence to an agency under Agencies → Edit → Registrations as a line "CL&RA No: K-438/2013"

## 2.5.3 (2026-11-16): Old tax invoices get the SW/001/26-27 number

- Tax invoices made before 16 Nov 2026 (e.g. SW26271/TI/01/2026) are renumbered to their work order's number for the month, SW/001/26-27 (the serial their sub-bills carry), linked to the work order and worked out again
- "Renumber and recalculate" button on such a tax invoice; "Recalculate all" on the Sub-bills page now also covers them; paid tax invoices are never changed

## 2.5.2 (2026-11-16): Submitted bills can be recalculated too

- Recalculate (one bill or "Recalculate all") now also corrects bills already submitted to the client, and their tax invoice if not paid; send the client the new PDF
- Paid bills and bills in a paid tax invoice are never changed

## 2.5.1 (2026-11-16): Recalculate bills made before the new rules

- Draft and approved bills made before 16 Nov 2026 can be worked out again with the current rules: service charge incl. GST (÷ 1.18), no rounding, new SW/001/26-27(CODE) number
- Sub-bills page lists those bills with a "Recalculate all" button; each bill also has "Recalculate with current rules"
- The bill keeps its status and attendance; a draft tax invoice holding it is updated too; submitted and paid bills are never changed
- Every recalculation is recorded in the Activity log (old and new total)

## 2.5.0 (2026-11-16): Tax invoice in the agencies' own format; no rounding of bill figures

- Tax invoice PDF laid out like the SWACHHA tax invoice: agency header (GSTIN, PAN, EPF and ESIC numbers, mobile, e-mail), invoice no. SW/001/26-27, GeM contract no. and date, FROM / TO, bank details
- Particulars: every post of every project under the work order (project in brackets), number of manpower, total working days, per-day wage and amount; then EPF 12%, EDLI 0.5%, PF admin 0.5%, ESI 3.25%, sub total, service charge, total, CGST / SGST, grand total, rounding off and amount in words
- No figure on bills is rounded any more: everything is carried exactly, as in Excel, and only shown to 2 decimals; only the grand total is rounded ("Rounding off")
- The tax invoice works out service charge and GST once on the combined sub-total of its sub-bills (as on the agencies' tax invoice), so it can differ from the sum of the sub-bills by a few paise
- The salary payout sheet and bank letter still pay each person in whole rupees

## 2.4.0 (2026-11-16): Work orders, new bill numbers, PI Allotment, bank letter, Employee Management

- "GeM Contracts" are now "Work Orders", Online (GeM) or Offline; their details are entered once and used for every project, sub-bill and tax invoice
- Work order page: projects, then the month's sub-bills (Add sub-bill), then the tax invoice (Add tax invoice) in one place
- New numbers: sub-bills SW/001/26-27(HTA), (CCRH), (MPH) and their tax invoice SW/001/26-27 (Z Plus: ZP/…); one tax invoice per work order per month; series restarts every April. Bills made earlier keep their numbers
- Service charge 3.85% now includes GST: charged as (amount × 3.85%) ÷ 1.18 = 3.26% on new bills; bills made earlier keep their amounts
- Employee IDs given automatically per agency: SW-1001, ZP-1001 …; existing IDs unchanged
- PI Allotment: each Project In-charge (name, designation, mobile, e-mail) entered once and mapped to projects under any work order; projects, certificates and PI logins use it
- Bank forwarding letter per project and month: SBI accounts (Emp ID, name, amount) and other banks by NEFT (account no., IFSC, amount)
- Employee Management: all employees in one list; pick one to see profile, KYC, month-wise attendance and month-wise bank payments
- Client form: all states and UTs with GST code, and their districts
- Bill, letter and project codes accept "/" and "-"; letter code up to 16 characters

## 2.3.2 (2026-11-04): Security review fixes

- 5 wrong passwords for one account lock it for 15 minutes, from any computer (each IP was already limited)
- Change password is limited to 6 tries a minute
- Browser security headers on every page: HTTPS-only (HSTS), no framing by other sites, no content-type guessing
- Uploaded Excel files that were checked but never saved are deleted after a day

## 2.3.1 (2026-11-03): Proof: no agency can enter the other's data

- New test tries all 30 add / edit / delete / status actions of a Z Plus login on SWACHHA data and the other way round (60 attempts): every one is refused and no row changes
- An agency login's new records always get its own agency, even if a form does not send one

## 2.3.0 (2026-11-03): Shared-computer safety and stricter agency isolation

- Pages, PDFs and downloads shown to a signed-in user are never kept in the browser cache, so Back after signing out shows nothing
- Signing out tells the browser to clear this site's cache, cookies and storage
- "Keep me signed in" removed (office computers are shared); earlier remember-me cookies no longer work
- Changing a password (yourself, or an Admin resetting it) signs that user out on every other computer
- Form checks look records up through the agency filter, so another agency's records are rejected even before saving
- Database safety net: triggers refuse any contract, sub-bill, tax invoice or employee linked to a different agency's client or contract

## 2.2.3 (2026-11-03): Month and year picker

- Month fields on Attendance, Sub-bills and Tax invoices open a calendar: choose the year with the arrows, then click the month
- Works the same in every browser, including Firefox (which has no built-in month calendar)

## 2.2.2 (2026-11-03): Agency filled in on forms

- For a Z Plus or SWACHHA login, the Agency field on Add client, Add GeM contract, Add employee and Add new post is filled in and locked to their agency
- The Super Admin and both-agency logins still choose the agency

## 2.2.1 (2026-11-03): Agency name on screen

- Dashboard shows "You are working for" with the agency's name, logo and GSTIN (or "Both agencies")
- Every page shows the agency badge next to the page title
- Single-agency logins no longer see the Agency filter on the dashboard

## 2.2.0 (2026-11-03): Separate logins for each agency

- Each login works for one agency (Z Plus or SWACHHA) or, set by the Super Admin, for both
- A single-agency login sees and enters only its own agency's clients, contracts, projects, posts, staff, attendance, sub-bills and tax invoices; the other agency's records are "not found"
- Agency Admins create logins (operators, accounts, PIs) for their own agency only; only the Super Admin creates both-agency logins
- Data Upload rejects rows for the other agency, its contract numbers and its employee codes; employee codes stay unique across both agencies
- Adding agencies and the GeM workbook import are for both-agency logins only; the sidebar shows which agency a login works for

## 2.1.0 (2026-11-03): Activity log for the Super Admin

- Every add, edit, delete and status change is logged automatically: who, when, from which IP, and each field's old → new value
- Views of employee details and downloads of salary sheets, bills, letters, certificates, documents and data exports are logged
- Sign-ins, failed sign-ins, sign-outs, password changes and data uploads are logged; attendance saves list every code changed
- Bank account and Aadhaar numbers appear only as "••••" plus the last 4 digits
- Activity log page (Super Admin only) with filters by user, action, section, dates and search, and Excel download; entries cannot be changed or deleted
- Super Admin: the owner's account; other Admins cannot change it

## 2.0.0 (2026-11-02): PI portal and user management

- PI (project in-charge) logins: a PI sees only their own projects and staff (no bank, Aadhaar, PAN, phone or salary)
- PI marks and saves attendance, then submits it: the certificate PDF is made automatically and the month is frozen for the PI
- PI downloads the PDF, signs it and uploads the signed copy in the app (or e-mails it), and can view and download the project's sub-bills
- Operator checks the submitted month, corrects any day the PI changed by hand (each change recorded and printed on the certificate), then approves
- Approving (locking) a month makes its sub-bill automatically as a draft; tax invoices stay one click
- Users screen (Admin): add staff and PI logins, temporary passwords that must be changed at first sign-in, switch accounts off, recent sign-ins
- Change password for every user; switched-off accounts are signed out

## 1.2.0 (2026-11-02): Data upload from Excel templates

- Excel templates for Agencies, Clients, GeM contracts, Projects, Posts and Employees, with instructions, help on every heading and dropdowns from the live data
- Download a blank template or one filled with the data already in the app
- Upload checks every row and shows New / Update / No change / Error before anything is saved; rows with errors are skipped
- Records are matched by code or number and updated; blank cells keep stored values; bank account and Aadhaar numbers are never downloaded

## 1.1.1 (2026-11-02): Release times

- Each release shows its date and time (IST) in the sidebar and on the Release notes page

## 1.1.0 (2026-11-02): Version and release notes

- Version number and release date shown in the sidebar
- Release notes page listing every version since the project started

## 1.0.0 (2026-11-02): Live release

- Running at zplus.epf-dashboard.online on the Hostinger VPS (Coolify, Docker, MySQL 8, HTTPS)
- Database updates applied automatically on every deploy
- Deployment guides for Coolify and for a plain Ubuntu server

## 0.10.0 (2026-11-02): EPF and ESIC switches

- EPF and ESIC switched on or off per employee from the Employees list or form
- Employer EPF (13% of wages up to ₹15,000) and ESIC (3.25%) added on sub-bills, tax invoices and bill screens
- Employee EPF 12% and ESIC 0.75% deducted on the salary payout sheet

## 0.9.0 (2026-11-02): Salary payout sheet

- Bank transfer sheet per locked month on the agency letterhead: earned salary, deductions, net pay and bank details
- Available to Admin and Accounts only

## 0.8.3 (2026-11-02): Appointment letter on one page

- Appointment letter fits on one page under the letterhead

## 0.8.2 (2026-11-02): Letterhead on attendance certificate

- Attendance certificate printed on the agency letterhead

## 0.8.1 (2026-11-02): SWACHHA tax invoices

- SWACHHA tax invoices always use the SWACHHA letterhead

## 0.8.0 (2026-11-02): SWACHHA letterhead

- SWACHHA banner letterhead (logo, registrations, address box) on its sub-bills, covering letters, tax invoices and appointment letters
- Letterhead layout chosen per agency

## 0.7.0 (2026-11-02): Sub-bills and tax invoices

- Sub-bill per project per month
- Tax invoice combining a client's sub-bills, with HSN/SAC and bank details

## 0.6.0 (2026-11-01): Z Plus letterhead

- Z Plus letterhead with seal, registrations and footer on bills and letters
- Agency logo upload and letterhead details on the Agencies page

## 0.5.0 (2026-11-01): Agency and client blocks

- Every list grouped into agency blocks with client blocks inside

## 0.4.0 (2026-11-01): Contracts and projects everywhere

- GeM contract and project columns and filters on employees, attendance, bills, posts and the dashboard
- Agencies page lists clients; Clients page lists contracts and employees

## 0.3.0 (2026-11-01): Posts

- Posts page and an "Add new post" window on the employee form

## 0.2.2 (2026-11-01): Agency and client on the dashboard and PDFs

- Agency and client named on the dashboard, employees list, bill PDF, covering letter, appointment letter and attendance certificate
- Employee form explains why the GeM post list can be empty

## 0.2.1 (2026-11-01): Attendance fix

- Fixed an error when starting the same month's attendance again

## 0.2.0 (2026-11-01): Agencies and clients

- Each client belongs to one agency, chosen from a dropdown
- Agency and client shown on the employee and contract forms, attendance and bills

## 0.1.1 (2026-11-01): Bills page help

- Bills page explains how a bill is made and lists months not yet locked

## 0.1.0 (2026-11-01): Project started

- Masters: agencies, clients, GeM contracts, projects, posts
- Employees with encrypted bank and Aadhaar numbers
- Attendance with PI certificate, GeM bill calculator (service charge 3.85% before GST)
- Covering letter, appointment letter and Excel import of the GeM workbook
