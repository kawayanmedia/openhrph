# Reports and payslips

This page helps you export lists, review payroll totals, and give employees their payslips.

## Export a list as CSV

Many admin lists include an **Export As CSV** action.

1. Open the list you need, for example Employees, Timesheet, or Payroll.
2. Use the search and filters to narrow the rows.
3. Select the rows you want. If the action works on all filtered rows, select them all.
4. Choose **Export As CSV** from the toolbar or bulk-action bar.
5. Confirm the download.
6. Open the file in a spreadsheet application.

The exported file contains the visible or selected records. It deliberately leaves out passwords and remember-me tokens.

## Payroll register

The payroll run page shows a register of employees and their pay for the period. Use it to review:

- employee name and number
- gross pay
- deductions
- net pay
- payslip access

The register is the best place to check the run before approving it.

## Payslips in the admin workspace

After a payroll is processed or approved:

1. Open the payroll run.
2. Find the employee in the register.
3. Select the payslip link.

The payslip page is print-ready. Use your browser's print command to print it or save it as a PDF.

## Payslip PDFs

A payslip PDF opens from the protected payslip route. A user with admin access can open any payslip in the active company; an employee can open only the payslip that belongs to them, and only with an admin-workspace sign-in. Employee-portal accounts cannot open payslips.

If a PDF does not open:

- Confirm you are signed in to the admin workspace.
- Confirm the payroll has been processed.
- Confirm the employee belongs to the current company.
- Ask your administrator to check file storage and permissions.

## Remittance and BIR working papers

`payroll:remittance` can produce employee-level working papers in addition to the four-row summary:

```bash
php artisan payroll:remittance {payroll} --format=summary
php artisan payroll:remittance {payroll} --format=sss --path=sss.csv
php artisan payroll:remittance {payroll} --format=philhealth --path=philhealth.csv
php artisan payroll:remittance {payroll} --format=pagibig --path=pagibig.csv
php artisan payroll:remittance {payroll} --format=bir --path=bir.csv
php artisan payroll:remittance {payroll} --format=all --path=remittance/
```

Each agency file lists the employees and ends with a `TOTAL` control row. Reconcile that total to the payroll register and the general ledger payable before filing. These are working papers, not the agency's e-filing file; verify the current form mapping before submission.

| File | Columns |
| --- | --- |
| SSS | Employee no, Name, SSS number, Monthly salary credit, Employee share, Employer share, Total |
| PhilHealth | Employee no, Name, PhilHealth number, Monthly basic, Employee share, Employer share, Total |
| Pag-IBIG | Employee no, Name, Pag-IBIG number, Fund salary, Employee share, Employer share, Total |
| BIR | Employee no, Name, TIN, Taxable income, Tax withheld |
| Summary | Agency, Employee share, Employer share, Total |

`payroll:bir-2316` writes the year-to-date BIR 2316 data with a control total. It rejects rows with an invalid TIN unless `--allow-invalid-tin` is passed; use `--rejects=path.csv` to capture the rejected rows.

## Period-close evidence pack

Build the evidence pack after approval:

```bash
php artisan payroll:evidence-pack {payroll} --path=storage/app/payroll-evidence/{payroll}
```

The pack contains the payroll register, the remittance summary and per-agency files, the BIR 2316 data (when year-to-date records exist), a `manifest.json` with SHA-256 hashes, and a `README.txt` retention note. Confirm the retention period with your CPA or tax adviser before disposing of any file.

## Employee portal

Employees can use the portal to view their own attendance report, request history, and to submit leave, attendance, schedule adjustment, overtime, undertime, and official business requests.

The portal has no payslip screen. Print or download payslips from the admin workspace and hand them to employees.

## Printing tips

- Use **Landscape** for wide payroll registers.
- Set margins to **Default** or **Minimum**.
- Turn off browser headers and footers if the payslip has its own header.
- Use **Save as PDF** to keep an electronic copy.
- Print a test copy for one employee before printing a batch.

## Common questions

**The CSV is empty.**
Make sure rows are selected and that the filters return results.

**The payslip is missing.**
Process the payroll first. Payslips are available after processing.

**The PDF or printout is cut off.**
Try landscape orientation, smaller margins, or a different paper size. Check the print preview before printing a batch.

**Can employees see other employees' payslips?**
No. A payslip opens only for the employee it belongs to, or for a user with admin access to the company.
