import { expect, Page, test } from '@playwright/test';

import { clearClock, manilaMoment, setClock } from './support/clock';
import { E2eAttendanceEmployee, E2eAttendanceFixtures, fixtures } from './support/fixtures';
import { login } from './support/login';

interface PunchPlan {
    timeIn: string;
    timeOut: string | null;
}

function isoDate(date: Date): string {
    return date.toISOString().slice(0, 10);
}

/**
 * Every weekday between two ISO dates, which is the schedule the seeded shift
 * assignments work (weekdays 1-5).
 */
function weekdays(from: string, to: string): string[] {
    const dates: string[] = [];
    const cursor = new Date(`${from}T00:00:00Z`);
    const end = new Date(`${to}T00:00:00Z`);

    while (cursor <= end) {
        if (cursor.getUTCDay() !== 0 && cursor.getUTCDay() !== 6) {
            dates.push(isoDate(cursor));
        }

        cursor.setUTCDate(cursor.getUTCDate() + 1);
    }

    return dates;
}

function addDays(date: string, days: number): string {
    const next = new Date(`${date}T00:00:00Z`);
    next.setUTCDate(next.getUTCDate() + days);

    return isoDate(next);
}

/** The portal prints the punch moment as `h:i A`. */
function clockLabel(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    const display = hour % 12 === 0 ? 12 : hour % 12;

    return `${String(display).padStart(2, '0')}:${String(minute).padStart(2, '0')} ${suffix}`;
}

function money(value: string): number {
    return Number(value.replace(/[^\d.-]/g, ''));
}

/** Compare a peso amount with the statutory expectation, tolerating centavo rounding per day. */
function expectAmount(actual: number | null, expected: number, label: string, tolerance = 0.1): void {
    expect(actual, label).not.toBeNull();
    expect(Math.abs((actual as number) - expected), `${label}: got ${actual}, expected ${expected}`).toBeLessThanOrEqual(tolerance);
}

/** `2026-11-01` as the portal prints it: `Nov 01, 2026`. */
function portalDate(date: string): string {
    const month = new Date(`${date}T00:00:00Z`).toLocaleString('en-US', { month: 'short', timeZone: 'UTC' });

    return `${month} ${date.slice(8, 10)}, ${date.slice(0, 4)}`;
}

function rowMatcher(value: string): RegExp {
    return new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
}

function crossesMidnight(employee: E2eAttendanceEmployee): boolean {
    return employee.time_out < employee.time_in;
}

async function portalLogin(page: Page, employee: E2eAttendanceEmployee): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/portal/login');
    await page.fill('#email', employee.email);
    await page.fill('#password', employee.password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/portal\/dashboard/);
}

/** Load the punch page on a given moment so it reflects that workday. */
async function openTimesheet(page: Page, date: string, time: string): Promise<void> {
    await setClock(page, manilaMoment(date, time));
    await page.goto('/portal/timesheet');
    await expect(page.getByRole('heading', { name: 'My Timesheet' })).toBeVisible();
}

function timesheetRows(page: Page) {
    return page.locator('main table tbody tr');
}

/**
 * Read a table cell by its column heading, tolerating the leading checkbox
 * column the admin index renders.
 */
async function cellByHeader(page: Page, rowText: RegExp, header: string): Promise<string> {
    const table = page.locator('main table').first();
    const headers = await table.locator('thead th').allInnerTexts();
    const index = headers.findIndex(
        (heading) => heading.trim().toLowerCase().startsWith(header.toLowerCase()),
    );

    expect(index, `column "${header}"`).toBeGreaterThanOrEqual(0);

    const row = table.locator('tbody tr').filter({ hasText: rowText }).first();
    await expect(row).toBeVisible();

    const cells = row.locator('td');
    const offset = (await cells.count()) - headers.length;

    return (await cells.nth(index + offset).innerText()).trim();
}

async function approveFirstPending(page: Page, rowName: RegExp, confirmButton: string): Promise<void> {
    const row = page
        .getByRole('row', { name: rowName })
        .filter({ hasText: 'Pending' })
        .filter({ has: page.getByRole('button', { name: 'Approve' }) })
        .first();

    await expect(row, `a pending row matching ${rowName}`).toBeVisible();
    await row.getByRole('button', { name: 'Approve' }).click();

    await page.locator('#actionfields-remarks').fill('Automated browser test approval');
    await page.getByRole('button', { name: confirmButton }).click();

    await expect(page.locator('#actionfields-remarks')).toBeHidden();
}

/** Read an earning or deduction line from the payslip, or null when it is absent. */
async function payslipAmount(page: Page, label: string): Promise<number | null> {
    const term = page.locator('dt').filter({ hasText: label }).first();

    if ((await term.count()) === 0) {
        return null;
    }

    return money(await term.locator('xpath=..').locator('dd').first().innerText());
}

test.describe.serial('attendance to payroll', () => {
    let attendance: E2eAttendanceFixtures;
    let period: E2eAttendanceFixtures['period'];
    let allDates: string[];
    let firstHalfDates: string[];
    let secondHalfDates: string[];
    let dayEmployee: E2eAttendanceEmployee;
    let nightEmployee: E2eAttendanceEmployee;
    let splitEmployee: E2eAttendanceEmployee;
    let lateDate: string;
    let missingOutDate: string;
    let overtimeDates: string[];

    const hourlyRate = (employee: E2eAttendanceEmployee): number => employee.daily_rate / 8;

    test.beforeAll(() => {
        attendance = fixtures().attendance;
        period = attendance.period;
        allDates = weekdays(period.from, period.to);
        firstHalfDates = allDates.filter((date) => date <= period.first_half_end);
        secondHalfDates = allDates.filter((date) => date >= period.second_half_start);
        dayEmployee = attendance.employees.find((employee) => employee.key === 'day')!;
        nightEmployee = attendance.employees.find((employee) => employee.key === 'night')!;
        splitEmployee = attendance.employees.find((employee) => employee.key === 'split')!;
        lateDate = firstHalfDates[2];
        missingOutDate = firstHalfDates[firstHalfDates.length - 2];
        overtimeDates = [firstHalfDates[3], missingOutDate];
    });

    test('punches every weekday of the month for the day, night and split shifts', async ({ page }) => {
        test.setTimeout(600_000);

        const failures: string[] = [];

        page.on('pageerror', (error) => failures.push(`page error: ${error.message}`));
        page.on('response', (response) => {
            if (response.status() >= 500) {
                failures.push(`${response.status()} ${response.request().method()} ${response.url()}`);
            }
        });

        for (const employee of attendance.employees) {
            await test.step(`${employee.key} shift (${employee.shift})`, async () => {
                await portalLogin(page, employee);

                for (const date of allDates) {
                    const plan: PunchPlan =
                        employee.key === 'day' && date === lateDate
                            ? { timeIn: '08:20', timeOut: '17:00' }
                            : employee.key === 'day' && date === missingOutDate
                              ? { timeIn: '08:00', timeOut: null }
                              : { timeIn: employee.time_in, timeOut: employee.time_out };

                    await openTimesheet(page, date, plan.timeIn);
                    await page.getByRole('button', { name: 'Time In' }).click();
                    await expect(page.getByText(`Time in recorded at ${clockLabel(plan.timeIn)}.`)).toBeVisible();

                    if (plan.timeOut === null) {
                        continue;
                    }

                    const outDate = crossesMidnight(employee) ? addDays(date, 1) : date;
                    await setClock(page, manilaMoment(outDate, plan.timeOut));
                    await page.getByRole('button', { name: 'Time Out' }).click();
                    await expect(page.getByText(`Time out recorded at ${clockLabel(plan.timeOut)}.`)).toBeVisible();
                }
            });
        }

        await test.step('the first cut-off lists the ten punched days for every shift', async () => {
            for (const employee of attendance.employees) {
                await portalLogin(page, employee);
                await openTimesheet(page, period.first_half_end, '23:00');

                await expect(
                    page.getByText(
                        `Current payroll period (${portalDate(period.from)} - ${portalDate(period.first_half_end)})`,
                    ),
                ).toBeVisible();
                await expect(timesheetRows(page)).toHaveCount(firstHalfDates.length);
            }
        });

        await test.step('the unpunched time out is flagged for correction', async () => {
            await portalLogin(page, dayEmployee);
            await openTimesheet(page, period.first_half_end, '23:00');

            const row = page.getByRole('row', { name: new RegExp(portalDate(missingOutDate)) });
            await expect(row).toContainText('missing punch');
        });

        await test.step('the second cut-off lists the remaining days at eight hours', async () => {
            for (const employee of attendance.employees) {
                await portalLogin(page, employee);
                await openTimesheet(page, period.to, '23:00');

                await expect(timesheetRows(page)).toHaveCount(secondHalfDates.length);

                const hours = await timesheetRows(page)
                    .locator('td:last-child')
                    .allInnerTexts();

                for (const value of hours) {
                    expect(Number(value.trim()), `${employee.key} hours`).toBeCloseTo(8, 2);
                }
            }
        });

        expect(failures, 'no server or page errors when punching').toEqual([]);
    });

    test('files an attendance correction and overtime, then HR approves both', async ({ page }) => {
        test.setTimeout(180_000);

        await test.step('the employee files the missing time out', async () => {
            await portalLogin(page, dayEmployee);
            await setClock(page, manilaMoment(addDays(missingOutDate, 1), '09:00'));

            await page.goto('/portal/attendance/create');

            const application = page
                .locator('form')
                .filter({ has: page.locator('input[type="date"]') })
                .locator('tbody tr')
                .first();

            await application.locator('input[type="text"]').fill('Forgot to punch out');
            await application.locator('input[type="date"]').fill(missingOutDate);
            await application.locator('input[type="time"]').fill('17:00');
            await application.locator('select').nth(1).selectOption('1');
            await page.waitForTimeout(750);

            await page.getByRole('button', { name: 'Submit' }).click();

            await expect(page).toHaveURL(/\/portal\/attendance/);
            await expect(page.getByRole('row', { name: /Forgot to punch out/ })).toContainText('Pending');
        });

        await test.step('the employee files two overtime requests', async () => {
            for (const [index, date] of overtimeDates.entries()) {
                const hours = 2 + index;
                const to = `${String(17 + hours).padStart(2, '0')}:00`;

                await setClock(page, manilaMoment(date, '17:30'));
                await page.goto('/portal/overtimes/create');

                const form = page.locator('form').filter({ has: page.locator('input[type="time"]') });

                await form.locator('#from').fill(date);
                await form.locator('input[type="time"]').nth(0).fill('17:00');
                await form.locator('input[type="time"]').nth(1).fill(to);
                await form.locator('input[type="text"]').fill(`Extended shift ${index + 1}`);
                await page.waitForTimeout(750);

                await expect(form.locator('input[type="number"]')).toHaveValue(String(hours));
                await page.getByRole('button', { name: 'Submit' }).click();

                await expect(page).toHaveURL(/\/portal\/overtimes/);
                await expect(page.getByRole('row', { name: new RegExp(`Extended shift ${index + 1}`) })).toContainText('Pending');
            }
        });

        await test.step('HR approves the attendance correction', async () => {
            await page.context().clearCookies();
            await clearClock(page);
            await login(page, attendance.admin.email, attendance.admin.password);

            await page.goto('/app/resources/attendance-request');
            await approveFirstPending(page, rowMatcher(dayEmployee.label), 'Approve Attendance');

            await expect(
                page.getByRole('row', { name: rowMatcher(dayEmployee.label) }).filter({ hasText: 'Approved' }),
            ).toHaveCount(1);
        });

        await test.step('HR approves both overtime requests', async () => {
            await page.goto('/app/resources/overtime-request');
            await expect(page.getByRole('row', { name: rowMatcher(dayEmployee.label) })).toHaveCount(overtimeDates.length);

            for (const [index] of overtimeDates.entries()) {
                await approveFirstPending(
                    page,
                    new RegExp(`${rowMatcher(dayEmployee.label).source}.*Extended shift ${index + 1}`),
                    'Approve Overtime',
                );
            }

            await expect(
                page.getByRole('row', { name: rowMatcher(dayEmployee.label) }).filter({ hasText: 'Approved' }),
            ).toHaveCount(2);
            await expect(
                page.getByRole('row', { name: rowMatcher(dayEmployee.label) }).filter({ hasText: 'Pending' }),
            ).toHaveCount(0);
        });

        await test.step('the corrected day now shows a complete eight-hour shift', async () => {
            await portalLogin(page, dayEmployee);
            await openTimesheet(page, missingOutDate, '18:00');

            const row = page.getByRole('row', { name: new RegExp(portalDate(missingOutDate)) });
            await expect(row).toContainText('17:00');
            await expect(row).not.toContainText('missing punch');
            await expect(row.locator('td:last-child')).toContainText('8.00');
        });

        await test.step('the late day is on the timekeeper worklist', async () => {
            await page.context().clearCookies();
            await login(page, attendance.admin.email, attendance.admin.password);

            await page.goto('/app/resources/timesheet');
            await page.getByRole('combobox', { name: 'Exceptions' }).selectOption('late');

            await expect(timesheetRows(page)).toHaveCount(1);

            await expect
                .poll(async () => cellByHeader(page, new RegExp(dayEmployee.employee_number), 'Late (min)'))
                .toBe('20');
            expect(await cellByHeader(page, new RegExp(dayEmployee.employee_number), 'Undertime (min)')).toBe('20');
        });
    });

    test('computes a payroll from the punches, overtime and night differential', async ({ page }) => {
        test.setTimeout(240_000);

        const firstHalfNightHours = 8 * firstHalfDates.length;
        const expectedOvertime = 5 * hourlyRate(dayEmployee) * 1.25;
        const expectedNightDifferential = firstHalfNightHours * hourlyRate(nightEmployee) * 0.1;

        await login(page, attendance.admin.email, attendance.admin.password);

        await test.step('the first cut-off previews the punched month', async () => {
            await page.goto(`/app/payroll/${attendance.payroll_ids.first_half}`);
            await expect(page.getByRole('heading', { name: `Payroll #${attendance.payroll_ids.first_half}` })).toBeVisible();

            for (const employee of attendance.employees) {
                const row = page.getByRole('row', { name: rowMatcher(employee.name) });
                await expect(row).toBeVisible();

                const gross = money(await row.locator('td').nth(1).innerText());
                const deductions = money(await row.locator('td').nth(6).innerText());
                const net = money(await row.locator('td').nth(7).innerText());

                expect(gross, `${employee.name} gross`).toBeGreaterThan(0);
                expect(net, `${employee.name} net`).toBeCloseTo(gross - deductions, 2);
            }
        });

        await test.step('the payroll is processed, reviewed and approved', async () => {
            page.once('dialog', (dialog) => dialog.accept());
            await page.getByRole('button', { name: 'Process payroll' }).click();
            await expect(page.getByRole('button', { name: 'Mark reviewed' })).toBeVisible({ timeout: 60_000 });

            await page.getByPlaceholder('Review note (optional)').fill('Attendance month regression');
            await page.getByRole('button', { name: 'Mark reviewed' }).click();
            await expect(page.getByText('Payroll marked as reviewed.')).toBeVisible();

            await page.getByPlaceholder(/Approval override reason/).fill('Automated browser test approval');
            page.once('dialog', (dialog) => dialog.accept());
            await page.getByRole('button', { name: 'Approve & lock' }).click();
            await expect(page.getByRole('button', { name: 'Reopen' })).toBeVisible();
        });

        await test.step('the overtime, night differential and undertime land on the payslips', async () => {
            await page.goto(`/app/payroll/${attendance.payroll_ids.first_half}/payslips/${dayEmployee.id}`);
            await expect(page.getByRole('heading', { name: 'Payslip' })).toBeVisible();

            expectAmount(await payslipAmount(page, 'Overtime'), expectedOvertime, 'overtime pay');
            await expect(page.locator('dt').filter({ hasText: 'Absences' })).toHaveCount(0);

            await page.goto(`/app/payroll/${attendance.payroll_ids.first_half}/payslips/${nightEmployee.id}`);
            expectAmount(await payslipAmount(page, 'Night differential'), expectedNightDifferential, 'night differential');
            expect(await payslipAmount(page, 'Overtime')).toBeNull();
            await expect(page.locator('dt').filter({ hasText: 'Absences' })).toHaveCount(0);

            await page.goto(`/app/payroll/${attendance.payroll_ids.first_half}/payslips/${splitEmployee.id}`);
            expect(await payslipAmount(page, 'Overtime')).toBeNull();
            expect(await payslipAmount(page, 'Night differential')).toBeNull();

            const pdfUrl = await page.getByRole('link', { name: 'Download PDF' }).getAttribute('href');
            const response = await page.request.get(pdfUrl!);

            expect(response.status()).toBe(200);
            expect(response.headers()['content-type']).toContain('pdf');
            expect((await response.body()).length).toBeGreaterThan(500);
        });

        await test.step('the second cut-off pays the remaining days', async () => {
            await page.goto(`/app/payroll/${attendance.payroll_ids.second_half}`);
            await expect(page.getByRole('heading', { name: `Payroll #${attendance.payroll_ids.second_half}` })).toBeVisible();

            page.once('dialog', (dialog) => dialog.accept());
            await page.getByRole('button', { name: 'Process payroll' }).click();
            await expect(page.getByRole('button', { name: 'Mark reviewed' })).toBeVisible({ timeout: 60_000 });

            await page.getByPlaceholder('Review note (optional)').fill('Attendance month regression');
            await page.getByRole('button', { name: 'Mark reviewed' }).click();
            await page.getByPlaceholder(/Approval override reason/).fill('Automated browser test approval');
            page.once('dialog', (dialog) => dialog.accept());
            await page.getByRole('button', { name: 'Approve & lock' }).click();
            await expect(page.getByRole('button', { name: 'Reopen' })).toBeVisible();

            await page.goto(`/app/payroll/${attendance.payroll_ids.second_half}/payslips/${nightEmployee.id}`);
            expectAmount(
                await payslipAmount(page, 'Night differential'),
                8 * secondHalfDates.length * hourlyRate(nightEmployee) * 0.1,
                'second half night differential',
            );
        });
    });
});
