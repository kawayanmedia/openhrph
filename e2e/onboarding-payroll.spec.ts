import { expect, Page, test } from '@playwright/test';

import { fixtures } from './support/fixtures';
import { login } from './support/login';

interface PayPeriod {
    label: string;
    start: Date;
    end: Date;
}

/**
 * Pick the same period the readiness checklist focuses on: the earliest open
 * period whose attendance window has not ended yet.
 */
async function focusPeriod(page: Page): Promise<PayPeriod> {
    await page.goto('/app/resources/pay-roll/new');

    const options = await page.locator('#form-payroll-date option').evaluateAll((elements) =>
        elements
            .map((element) => ({
                value: (element as HTMLOptionElement).value,
                label: element.textContent?.trim() ?? '',
            }))
            .filter((option) => option.value !== ''),
    );

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const periods: PayPeriod[] = options
        .map((option) => {
            const range = option.label.match(/\((.*?) to (.*?)\)/);

            if (range === null) {
                return null;
            }

            return { label: option.label, start: new Date(range[1]), end: new Date(range[2]) };
        })
        .filter((period): period is PayPeriod => period !== null && period.end >= today)
        .sort((left, right) => left.start.getTime() - right.start.getTime());

    expect(periods, 'the payroll group should generate a period that includes today').not.toHaveLength(0);

    return periods[0];
}

function isoDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function money(value: string): number {
    return Number(value.replace(/[^\d.-]/g, ''));
}

test.describe('new company onboarding', () => {
    test('sets up every payroll prerequisite, then runs payroll through to an approved payslip', async ({ page }) => {
        test.setTimeout(240_000);

        const { onboarding } = fixtures();
        const serverErrors: string[] = [];

        page.on('pageerror', (error) => serverErrors.push(`page error: ${error.message}`));
        page.on('response', (response) => {
            if (response.status() >= 500) {
                serverErrors.push(`${response.status()} ${response.request().method()} ${response.url()}`);
            }
        });

        await login(page, onboarding.user, onboarding.password);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

        await page.goto('/app/getting-started');
        await expect(page.locator('main')).toContainText('0 of 9');

        await page.goto('/app/resources/office/new');
        await page.locator('#form-code').fill('E2E-OB');
        await page.locator('#form-name').fill('Onboarding HQ');
        await page.locator('#form-region').selectOption({ label: 'NCR' });
        await page.locator('#form-industry').selectOption({ label: 'Non-agriculture' });
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Onboarding HQ');

        await page.goto('/app/resources/employment-type/new');
        await page.locator('#form-code').fill('E2E-REG');
        await page.locator('#form-name').fill('E2E Regular');
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('E2E Regular');

        await page.goto('/app/resources/department/new');
        await page.locator('#form-code').fill('E2E-HR');
        await page.locator('#form-name').fill('E2E HR');
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('E2E HR');

        await page.goto('/app/resources/pay-roll-group/new');
        await page.locator('#form-name').fill('E2E Semi-Monthly');
        await page.locator('#form-days-year').fill('261');
        await page.locator('#form-hours-day').fill('8');
        await page.locator('#form-frequency').selectOption('semi-monthly');
        await expect(page.locator('#form-first-cut-off')).toBeEnabled();

        await expect(page.locator('#form-first-cut-off')).toHaveValue('10');
        await expect(page.locator('#form-first-pay-date')).toHaveValue('15');
        await expect(page.locator('#form-second-cut-off')).toHaveValue('25');
        await expect(page.locator('#form-second-pay-date')).toHaveValue('30');

        await page.evaluate(() => {
            (document.querySelector('#form-is-default') as HTMLInputElement).click();
        });
        await page.locator('#form-pay-date-non-working').selectOption({ label: 'Pay date before non working day' });
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('E2E Semi-Monthly');

        const period = await focusPeriod(page);

        await page.goto('/app/resources/employee/new');
        await page.locator('#form-employee-number').fill('E2E-OB-01');
        await page.locator('#form-last-name').fill('Onboarding');
        await page.locator('#form-first-name').fill('Olivia');
        await page.locator('#form-office').selectOption({ label: 'Onboarding HQ' });
        await page.locator('#form-department').selectOption({ label: 'E2E HR' });
        await page.locator('#form-employmenttype').selectOption({ label: 'E2E Regular' });
        await page.locator('#form-position').fill('Payroll Clerk');
        await page.locator('#form-basic-salary').fill('30000');
        await page.locator('#form-payroll-group').selectOption({ label: 'E2E Semi-Monthly' });
        await page.evaluate(() => {
            (document.querySelector('#form-timesheet') as HTMLInputElement).click();
        });
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Onboarding, Olivia');
        await expect(page.locator('main').getByText('E2E-OB-01')).toBeVisible();

        await page.goto('/app/resources/employee-compensation/new');
        await page.locator('#form-employee').selectOption({ index: 1 });
        await page.locator('#form-compensation').selectOption({ label: '[Compensation] REPRESENTATION ALLOWANCE' });
        await page.locator('#form-amount').fill('2000');
        await page.evaluate(() => {
            (document.querySelector('#form-is-recurring') as HTMLInputElement).click();
        });
        await expect(page.locator('#form-frequency')).toBeEnabled();
        await page.locator('#form-frequency').selectOption({ label: 'Every Pay' });
        await page.locator('#form-valid-date-from').fill(isoDate(period.start));
        await page.locator('#form-valid-date-to').fill(isoDate(period.end));
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/employee-compensation/new');
        await page.locator('#form-employee').selectOption({ index: 1 });
        await page.locator('#form-compensation').selectOption({ label: '[Compensation] TRANSPORTATION ALLOWANCE' });
        await page.locator('#form-amount').fill('1000');
        await page.evaluate(() => {
            (document.querySelector('#form-is-recurring') as HTMLInputElement).click();
        });
        await expect(page.locator('#form-frequency')).toBeEnabled();
        await page.locator('#form-frequency').selectOption({ label: 'Every Pay' });
        await page.locator('#form-valid-date-from').fill(isoDate(period.start));
        await page.locator('#form-valid-date-to').fill(isoDate(period.end));
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/deduction/new');
        await page.locator('#form-code').fill('E2E-MED');
        await page.locator('#form-name').fill('E2E Medical Cash');
        await page.locator('#form-amount').fill('500');
        await page.locator('#form-rate-percent').fill('0');
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/deduction/new');
        await page.locator('#form-code').fill('E2E-PCT');
        await page.locator('#form-name').fill('E2E 2% Deduction');
        await page.locator('#form-calculation').selectOption({ label: 'Percentage of a base' });
        await page.locator('#form-amount').fill('0');
        await page.locator('#form-rate-percent').fill('2');
        await page.locator('#form-base').selectOption({ label: 'Period basic' });
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/employee-deduction/new');
        await page.locator('#form-employee').selectOption({ index: 1 });
        await page.locator('#form-deduction').selectOption({ label: 'E2E Medical Cash' });
        await page.locator('#form-amount').fill('500');
        await page.evaluate(() => {
            (document.querySelector('#form-is-recurring') as HTMLInputElement).click();
        });
        await expect(page.locator('#form-frequency')).toBeEnabled();
        await page.locator('#form-frequency').selectOption({ label: 'Every pay of the month' });
        await page.locator('#form-valid-date-from').fill(isoDate(period.start));
        await page.locator('#form-valid-date-to').fill(isoDate(period.end));
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/employee-deduction/new');
        await page.locator('#form-employee').selectOption({ index: 1 });
        await page.locator('#form-deduction').selectOption({ label: 'E2E 2% Deduction' });
        await page.locator('#form-amount').fill('0');
        await page.evaluate(() => {
            (document.querySelector('#form-is-recurring') as HTMLInputElement).click();
        });
        await expect(page.locator('#form-frequency')).toBeEnabled();
        await page.locator('#form-frequency').selectOption({ label: 'Every pay of the month' });
        await page.locator('#form-valid-date-from').fill(isoDate(period.start));
        await page.locator('#form-valid-date-to').fill(isoDate(period.end));
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/loan/new');
        await page.locator('#form-code').fill('E2E-LOAN');
        await page.locator('#form-name').fill('E2E Company Loan');
        await page.locator('#form-loan-agency').fill('Company');
        await page.evaluate(() => {
            (document.querySelector('#form-is-active') as HTMLInputElement).click();
        });
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/employee-loan/new');
        await page.locator('#form-employee').selectOption({ index: 1 });
        await page.locator('#form-loan-id').selectOption({ label: 'E2E Company Loan' });
        await page.locator('#form-reference-number').fill('E2E-LN-01');
        await page.locator('#form-amount').fill('12000');
        await page.locator('#form-balance').fill('12000');
        await page.locator('#form-terms').fill('12');
        await page.locator('#form-repayment-start-date').fill(isoDate(period.start));
        await page.locator('#form-scheme').selectOption({ label: 'Every pay of the month' });
        await page.locator('#form-monthly-amortization').fill('1000');
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        await page.goto('/app/resources/timesheet');
        await page.getByRole('button', { name: 'Generate Time Sheet' }).click();

        const actionModal = page.locator('div.fixed.inset-0').filter({ hasText: 'Generate Time Sheet' }).last();
        const actionDates = actionModal.locator('input[type="date"]');

        await actionDates.nth(0).fill(isoDate(period.start));
        await actionDates.nth(1).fill(isoDate(period.end));
        await actionModal.locator('select').selectOption({ label: 'Onboarding HQ' });
        await actionModal.getByRole('button', { name: 'Run Action' }).click();
        await expect(page.getByRole('cell', { name: 'E2E-OB-01' }).first()).toBeVisible();

        await page.goto('/app/getting-started');
        await expect(page.locator('main')).toContainText('8 of 9');

        await page.goto('/app/resources/pay-roll/new');
        await page.locator('#form-payroll-date').selectOption({ label: period.label });
        await page.getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Record created.')).toBeVisible();

        const payrollId = Number(new URL(page.url()).pathname.split('/').pop());

        await page.goto(`/app/payroll/${payrollId}`);
        await expect(page.getByRole('heading', { name: `Payroll #${payrollId}` })).toBeVisible();

        const registerRow = page.getByRole('row', { name: /Olivia Onboarding/ });
        await expect(registerRow).toBeVisible();

        const gross = money(await registerRow.locator('td').nth(1).innerText());
        const deductions = money(await registerRow.locator('td').nth(6).innerText());
        const net = money(await registerRow.locator('td').nth(7).innerText());

        expect(gross).toBe(18000);
        expect(deductions).toBeGreaterThan(500);
        expect(net).toBeCloseTo(gross - deductions, 2);

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Process payroll' }).click();
        await expect(page.getByText('Payroll processing started.')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByRole('button', { name: 'Mark reviewed' })).toBeVisible({ timeout: 30_000 });

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Approve & lock' }).click();
        await expect(page.getByText(/Enter an override reason/i)).toBeVisible();

        await page.getByPlaceholder('Review note (optional)').fill('E2E onboarding review');
        await page.getByRole('button', { name: 'Mark reviewed' }).click();
        await expect(page.getByText('Payroll marked as reviewed.')).toBeVisible();

        await page.getByPlaceholder(/Approval override reason/).fill('Single-admin test company; self-review acknowledged');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Approve & lock' }).click();
        await expect(page.getByText('Payroll approved and locked.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Reopen' })).toBeVisible();

        await registerRow.getByRole('link', { name: 'View' }).click();
        await expect(page.getByRole('heading', { name: 'Payslip', level: 1 })).toBeVisible();
        await expect(page.getByText('Olivia Onboarding').first()).toBeVisible();
        await expect(page.getByText('REPRESENTATION ALLOWANCE').first()).toBeVisible();
        await expect(page.getByText('TRANSPORTATION ALLOWANCE').first()).toBeVisible();
        await expect(page.getByText('E2E Medical Cash').first()).toBeVisible();
        await expect(page.getByText('E2E 2% Deduction').first()).toBeVisible();
        await expect(page.getByText('E2E Company Loan').first()).toBeVisible();
        await expect(page.getByText(gross.toLocaleString('en-PH', { minimumFractionDigits: 2 })).first()).toBeVisible();
        await expect(page.getByText(net.toLocaleString('en-PH', { minimumFractionDigits: 2 })).first()).toBeVisible();

        const pdfUrl = await page.getByRole('link', { name: 'Download PDF' }).getAttribute('href');
        expect(pdfUrl).not.toBeNull();

        const pdf = await page.request.get(pdfUrl!);
        expect(pdf.status()).toBe(200);
        expect(pdf.headers()['content-type']).toContain('pdf');
        expect((await pdf.body()).length).toBeGreaterThan(500);

        await page.goto('/app/getting-started');
        await expect(page.locator('main')).toContainText('9 of 9');
        await expect(page.getByText('Payroll is ready to run')).toBeVisible();

        expect(serverErrors, `Unexpected server/page errors:\n${serverErrors.join('\n')}`).toEqual([]);
    });
});
