# GeM contracts 2026-27 (SWACHHA → ICMR-NIHR Bhubaneswar), read 17 Nov 2026

The owner shared 5 GeM contract PDFs. They are not in git; only this summary is.
All five contracts share these details:
- **Generated:** 30-Sep-2026.
- **Service period:** 01-Oct-2026 to 30-Sep-2027 (12 months).
- **Seller:** SWACHHA (GeM seller ID JBMM260014835152, GSTIN 21AADAS8034Q1Z6).
- **Buyer:** Administrative Officer, ICMR-RMRC Bhubaneswar.
- **Paying authority:** PAO / Accounts Officer, payment mode Offline. The tax invoice is raised in the name of the Buyer.
- **Pay terms:** Fixed Remuneration, billing cycle monthly, service charge **3.85% inclusive of GST**.
- **Allowances:** PF / ESI / EDLI / EPF admin / bonus / optional allowances / overtime are all **0**, so the contract rate is the basic pay only.
- **GeM formula:** TCV = (m × 1.18 + m × 3.85/100) × 12 × qty, i.e. monthly cost = basic × 1.2185.
  - This equals the app's v2.4.0 rule: SC = total × 3.85% ÷ 1.18, then GST 18%.
  - Every contract total below was checked to the paisa.
- **ePBG:** 5% (SBI advisory), also allowed as a DD or RTGS to "Director Regional Medical Research Centre".
- **Option clause:** quantity or duration can be increased up to 50% after award.

| Contract | Bid | Posts | Staff | Monthly basic | Total value (₹) |
|---|---|---|---|---|---|
| GEMC-511687787069416 | GEM/2026/B/7972724 | 3 | 14 | 6,01,200 | 87,90,746.40 |
| GEMC-511687787708842 | GEM/2026/B/7975638 | 11 | 14 | 6,83,065 | 99,87,776.43 |
| GEMC-511687760725883 | GEM/2026/B/7972775 | 12 | 19 | 6,24,000 | 91,24,128.00 |
| GEMC-511687786837840 | GEM/2026/B/7972730 | 7 | 15 | 6,00,400 | 87,79,048.80 |
| GEMC-511687779961317 | GEM/2026/B/7993099 | 7 | 16 | 4,88,600 | 71,44,309.20 |

**Posts per contract** (designation, GeM profile, basic pay, quantity):
- **…069416:**
  - Project Research Scientist-II (Non Medical) 80,400 ×3
  - Project Technical Support-III 33,600 ×10
  - Project Technical Support-II 24,000 ×1
- **…708842** (same designation at several rates):
  - Consultant Microbiologist 75,246 / 89,578 / 71,663 ×1 each
  - Sr. Laboratory Technician 25,000 ×2, 40,517 ×3, 30,319 / 31,697 / 27,563 ×1 each
  - Biomedical Engineer 65,000
  - Biotechnologist 82,412
  - Data Analyst 38,036
- **…725883:**
  - PRS-II (Medical) 88,000
  - PTS-II (FW) 22,000 ×4, and 24,000 ×1
  - PTS-III (LT) 30,800 ×2
  - PTS-III (FI) 33,600
  - PTS-III 33,600 ×2
  - Assistant Multipurpose (Admin) 26,400
  - PRS-I (Non Medical) 67,200
  - Project Associate-I 37,200 ×2
  - Project Assistant 24,000
  - Field Worker / Animal House Keeper 21,600
  - Project Nurse-II 24,000 ×2
- **…837840:**
  - PRS-III (NM) 93,600
  - Consultant (Scientific Technical / NM) 70,000
  - PTS-III 33,600 ×3
  - PTS-II 24,000 ×2
  - PTS-I 21,600 ×4
  - PRS-I (NM) 67,200 ×2
  - Project Staff Nurse-III 33,600 ×2
- **…961317:**
  - Project Assistant 31,000 ×5
  - PRS-II 88,000
  - PTS-III (LT) 30,800 ×2
  - PTS-III (FI) 33,600
  - PTS-II (FW) 22,000 ×4
  - Assistant Multipurpose (Admin) 26,400
  - Project Technician-III (FW) 18,000 ×2

**SLA points relevant to the app:**
- **Leave:** casual leave is 8 days/year (5-day week) or 15 days/year (6-day week), pro-rata. Beyond that it is LWP, deducted from the bill if no substitute is given.
- **Substitutes:** absence of more than 2 days without approval needs a substitute within 2 days, otherwise up to 2 days' wages are deducted per day. A leaving employee must be notified 1 month ahead.
- **Wages and statutory deposits:**
  - Pay wages into bank accounts by the 7th of the following month, before claiming from the buyer.
  - Deposit EPF/ESI (both shares) by the 15th.
  - Give the buyer a copy of each EPF/ESI return within 7 days of filing.
- **Monthly bill:** invoice + attendance sheet + logbook + service feedback + salary payment proof (bank statement) + PF/ESI/EDLI challans, uploaded on GeM. Missing papers mean delay or deduction.
- **Joining papers:** photo ID card within 8 days of joining, police verification at deployment. Attendance by register and/or Aadhaar biometric.
- **Deduction table:** article lost/damaged, misconduct, absence without substitute, non-payment of wages/EPF/ESI (cancellation charge 10%).
- **Minimum wage changes** during the contract are paid by the buyer.

Gaps vs the app, suggested to the owner (17 Nov 2026, not built; owner said "just suggest"):
- service start/end dates, contract value, ePBG
- buyer/paying-authority details
- GeM category/profile per post
- bulk import of a contract's posts
- quantity/expiry alerts
- leave/LWP and SLA deductions on the bill
- statutory challan and wage-payment-proof uploads per month
- staff police verification / ID card dates
- a bill supporting-documents pack
