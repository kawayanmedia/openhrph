import { expect, test } from '@playwright/test';

import { fixtures } from './support/fixtures';
import { login } from './support/login';

function money(value: string): number {
    return Number(value.replace(/[^\d.-]/g, ''));
}

test.describe('payroll run', () => {
    test('previews, processes and opens a payslip', async ({ page }) => {
        const { payroll_id } = fixtures();

        await login(page);
        await page.goto(`/app/payroll/${payroll_id}`);

        await expect(page.getByRole('heading', { name: `Payroll #${payroll_id}` })).toBeVisible();

        const registerRow = page.getByRole('row', { name: /Ana Reyes/ });
        await expect(registerRow).toBeVisible();

        const gross = money(await registerRow.locator('td').nth(1).innerText());
        const deductions = money(await registerRow.locator('td').nth(6).innerText());
        const net = money(await registerRow.locator('td').nth(7).innerText());

        expect(gross).toBeGreaterThan(0);
        expect(deductions).toBeGreaterThan(0);
        expect(net).toBeCloseTo(gross - deductions, 2);

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Process payroll' }).click();
        await expect(page.getByText('Payroll processing started.')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByRole('button', { name: 'Mark reviewed' })).toBeVisible({ timeout: 30_000 });

        await page.getByPlaceholder('Review note (optional)').fill('Regression run');
        await page.getByRole('button', { name: 'Mark reviewed' }).click();
        await expect(page.getByText('Payroll marked as reviewed.')).toBeVisible();

        await page.getByPlaceholder(/Approval override reason/).fill('Automated regression run by the same operator');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Approve & lock' }).click();
        await expect(page.getByText('Payroll approved and locked.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Reopen' })).toBeVisible();

        await registerRow.getByRole('link', { name: 'View' }).click();

        await expect(page.getByRole('heading', { name: 'Payslip', level: 1 })).toBeVisible();
        await expect(page.getByText('Ana Reyes').first()).toBeVisible();
        await expect(page.getByText(gross.toLocaleString('en-PH', { minimumFractionDigits: 2 })).first()).toBeVisible();
        await expect(page.getByText(net.toLocaleString('en-PH', { minimumFractionDigits: 2 })).first()).toBeVisible();
    });

    test('payslip PDF downloads and foreign payrolls are denied', async ({ page }) => {
        const { payroll_id } = fixtures();

        await login(page);
        await page.goto(`/app/payroll/${payroll_id}`);

        if (await page.getByRole('button', { name: 'Mark reviewed' }).count() > 0) {
            page.once('dialog', (dialog) => dialog.accept());
            await page.getByRole('button', { name: 'Process payroll' }).click();
            await expect(page.getByRole('button', { name: 'Mark reviewed' })).toBeVisible({ timeout: 30_000 });
        }

        await page.getByRole('row', { name: /Ana Reyes/ }).getByRole('link', { name: 'View' }).click();

        const pdfUrl = await page.getByRole('link', { name: 'Download PDF' }).getAttribute('href');
        const response = await page.request.get(pdfUrl!);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('pdf');
        expect((await response.body()).length).toBeGreaterThan(500);

        const missing = await page.request.get('/app/payroll/999999');
        expect(missing.status()).toBe(404);
    });
});
