---
slug: /user-guide
description: Day-to-day OpenHR PH workflows for HR officers, payroll staff, administrators, and employees.
---

# OpenHR PH user guide

This guide is for the people who use OpenHR PH every day: HR officers, payroll staff, company owners, and employees. It explains the screens and the usual workflows without assuming any technical knowledge.

## The two interfaces

OpenHR PH has two separate places to work:

| Interface | Who uses it | What it is for |
| --- | --- | --- |
| **Admin workspace** (`/app`) | HR, payroll, and company owners | Managing people, attendance, payroll, settings, and reports |
| **Employee portal** (`/portal`) | Employees | Viewing schedules and submitting leave, overtime, undertime, official business, attendance, and schedule requests |

Your account may have access to one or both. If you are unsure, ask your administrator.

## Choose your sector

The installation is configured for one sector. Pick the guide set that matches it:

| Sector | Start here | What it includes |
| --- | --- | --- |
| **Private** | [Private-sector HR](private-hr.md) | Recruitment to hire, employment and contracts, probation and regularization, separation, clearance, final settlement, company retirement plans |
| **Government (PH)** | [Government-sector HR](government-hr.md) | Plantilla, qualifications, appointments, CSC actions, personnel actions, HRMPSB, service records, leave cards, GSIS retirement and government separation |

Shared guides — employees, organization, documents, attendance, payroll, performance, learning, retirement
mechanics, compliance and analytics — apply to both sectors and are listed below.

## How to use this guide

The guide is organised by the work you are doing. Start with the area that matches your task.

### Start here

| Guide | Use it for |
| --- | --- |
| [Company to payslip walkthrough](end-to-end-walkthrough.md) | One complete first run: company profile, reference data, payroll group, employee, portal access, payroll, payslip, corrections |
| [Getting started](getting-started.md) | Signing in, switching companies, navigation, search, password, dark mode |
| [Data import](data-import.md) | Bulk upload of offices, departments, payroll groups, positions, schedules, and employees |

### People and organization

| Guide | Use it for |
| --- | --- |
| [Employees](employees.md) | Employee records, documents, schedules, compensation, and import |
| [Organization and positions](organization-and-positions.md) | Organization units, locations, cost centers, positions, assignments, reporting lines, employment changes |
| [Documents and workflow](documents-and-workflow.md) | Document repository, versions, classification, acknowledgments, approvals, delegations, audit trail |

### Time, attendance and requests

| Guide | Use it for |
| --- | --- |
| [Attendance and requests](attendance-and-requests.md) | Leave, overtime, undertime, official business, attendance certificates, and approvals |
| [Timesheet management](timesheet-management.md) | Daily timesheet routine, device imports, night shifts, absences, locking and reopening |
| [Workforce corrections and approvals](workforce-corrections.md) | Attendance corrections and timesheet submit/approve/reject/lock |

### Payroll and reporting

| Guide | Use it for |
| --- | --- |
| [Payroll](payroll.md) | Payroll groups, compensation catalogue, calculations, processing, approval, locking |
| [Reports and payslips](reports-and-payslips.md) | Report centre, employee master file, 201 file, register, payslips, remittances |
| [Workforce analytics](workforce-analytics.md) | Headcount, turnover, retirement forecasts, completion rates, compliance exposure |

### HR administration

| Guide | Use it for |
| --- | --- |
| [Performance](performance.md) | Cycles, goals, reviews, ratings, finalization, acknowledgment, development plans |
| [Learning and certifications](learning.md) | Courses, sessions, enrollments, completion, certifications |
| [Retirement](retirement.md) | Plans, eligibility, cases, calculations, external claims |
| [Compliance](compliance.md) | Compliance sources, rules, versions, review, approval, resolution |
| [Employee self-service](employee-self-service.md) | Portal dashboard, requests, performance, training, documents |

### Sector guides

**Private sector**

| Guide | Use it for |
| --- | --- |
| [Private-sector HR](private-hr.md) | Private menu overview and workflow map |
| [Private recruitment to hire](private-recruitment.md) | Requisitions to hire, onboarding |
| [Private employment](private-employment.md) | Employments, contracts, probation, regularization, leave rules |
| [Private separation](private-separation.md) | Separation, clearance, final settlement |

**Government sector (PH)**

| Guide | Use it for |
| --- | --- |
| [Government-sector HR](government-hr.md) | Government menu overview and workflow map |
| [Plantilla and qualifications](government-plantilla.md) | Plantilla versions/items, qualification standards, screening, occupancy |
| [Appointments](government-appointments.md) | Appointment state machine, documents, CSC actions, assumptions |
| [Personnel actions](government-personnel-actions.md) | Promotion, transfer, detail, reassignment, separation actions |
| [HRMPSB](government-hrmpsb.md) | Committees, members, deliberations |
| [Government records](government-records.md) | Salary grades, NOSA, service records, leave cards, PDS/201 file |
| [Separation and retirement](government-separation-retirement.md) | Resignation, GSIS retirement, terminal leave, death in service, dropping, dismissal, clearance |

**HR module directory**

| Guide | Use it for |
| --- | --- |
| [HR, workforce and talent overview](hr-and-workforce.md) | Directory of every shared and sector HR guide |

### Settings and help

| Guide | Use it for |
| --- | --- |
| [Settings and access](settings-and-access.md) | Company settings, reference data, users, groups, permissions |
| [Troubleshooting](troubleshooting.md) | Common problems and when to contact an administrator |

### Shortcuts

- **Need to add another company?** Company owners can create one from **Settings → Company → Companies**. See [Getting started](getting-started.md#create-another-company).
- **Looking for a screen or record quickly?** Press **⌘/ or Ctrl+/** to open universal search. See [Getting started](getting-started.md#search-from-anywhere).
- **Want to try it without installing?** Sign up at [openhrph.laravel.cloud/register](https://openhrph.laravel.cloud/register), verify your email address, and explore an isolated demo workspace with sample data.
- **New to OpenHR PH?** Start with [Getting started](getting-started.md), then follow the [company-to-payslip walkthrough](end-to-end-walkthrough.md).

## A short glossary

| Term | Meaning |
| --- | --- |
| **Company** | Your organisation. One OpenHR PH installation can hold more than one company, and each company's data is kept separate. |
| **Workspace** | The admin area at `/app`. |
| **Employee portal** | The self-service area where employees submit requests. |
| **Payroll group** | The rules and schedule used to compute pay, such as semi-monthly or monthly. |
| **Payroll run** | One processing period for a payroll group. |
| **Payslip** | The employee-level result of a payroll run. |
| **Attendance correction** | A reviewed request to fix a punch. It adds a corrected punch; it never rewrites the original. |
| **Retirement calculation** | A recorded benefit or terminal-leave computation with its inputs and rule version. It is provisional until reviewed. |
| **Compliance rule** | A versioned, approved configuration linked to an official source. |
| **Remittance register** | The filing and payment record for each statutory agency for a payroll. |
| **Bank file** | A CSV credit file that tells the bank how much to pay each employee. |
| **Employee master file** | The filterable masterlist of every employee, with statutory IDs and bank details. |
| **201 file** | A printable employee dossier with personal, employment, payroll, leave, and document information. |
| **SSS** | Social Security System. |
| **PhilHealth** | Philippine Health Insurance Corporation. |
| **Pag-IBIG** | Home Development Mutual Fund. |
| **BIR** | Bureau of Internal Revenue. |
| **Cut-off** | The date range covered by a payroll run. |

## A note about permissions

What you can see depends on your account and your group. A company owner can usually see everything. Other users see the menu entries and records their group allows. If a menu item is missing, it usually means your group does not have that permission. See [Settings and access](settings-and-access.md).
