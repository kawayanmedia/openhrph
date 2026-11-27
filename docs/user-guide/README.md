---
slug: /user-guide
description: "Choose the OpenHR PH user guide for your sector: Private organisations or Philippine Government agencies."
---

import Link from '@docusaurus/Link';

# OpenHR PH user guide

This guide is for the people who use OpenHR PH every day: HR officers, payroll staff, company owners, and
employees. It explains the screens and the usual workflows without assuming any technical knowledge.

The guide is published in two editions. Each edition contains the modules for one sector **and** its own
copy of the modules both sectors share, so everything you need for your work stays in one place.

## Choose your sector

The installation is configured for one sector. Open the guide that matches it:

| Sector | Guide | What it includes |
| --- | --- | --- |
| **Private** | <Link to="/docs/user-guide/private/private-hr">Private-sector user guide</Link> | Recruitment to hire, employment and contracts, probation and regularization, separation, clearance, final settlement, company retirement plans, plus every shared module |
| **Government (PH)** | <Link to="/docs/user-guide/government/government-hr">Government-sector user guide</Link> | Plantilla, qualifications, appointments, CSC actions, personnel actions, HRMPSB, service records, leave cards, GSIS retirement, government separation, plus every shared module |

If you are not sure which sector your installation uses, open **Settings → Sector Settings** in the admin
workspace. The sector is fixed for the whole installation, so the answer is the same for everyone in your
organisation.

## What is in every edition

Both editions follow the same structure:

1. **Start here** — the company-to-payslip walkthrough, signing in, and bulk data import.
2. **Your sector** — the modules that only exist for Private organisations or Government agencies.
3. **Shared modules** — people and organization, time and attendance, payroll and reporting, HR
   administration, and settings.

| Shared module group | Guides |
| --- | --- |
| People and organization | Employees, organization and positions, documents and workflow |
| Time and attendance | Attendance and requests, timesheet management, workforce corrections |
| Payroll and reporting | Payroll, reports and payslips, workforce analytics |
| HR administration | Performance, learning, retirement, compliance, employee self-service |
| Settings and help | Settings and access, troubleshooting |

## The two interfaces

OpenHR PH has two separate places to work:

| Interface | Who uses it | What it is for |
| --- | --- | --- |
| **Admin workspace** (`/app`) | HR, payroll, and company owners | Managing people, attendance, payroll, settings, and reports |
| **Employee portal** (`/portal`) | Employees | Viewing schedules and submitting leave, overtime, undertime, official business, attendance, and schedule requests |

Your account may have access to one or both. If you are unsure, ask your administrator.

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

What you can see depends on your account and your group. A company owner can usually see everything. Other
users see the menu entries and records their group allows. If a menu item is missing, it usually means your
group does not have that permission. See the *Settings and access* guide in your sector edition.
