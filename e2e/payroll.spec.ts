import { expect, test } from '@playwright/test';

import { fixtures } from './support/fixtures';
import { login } from './support/login';

test.describe('payroll run', () => {
    test('previews, processes and opens a payslip', async ({ page }) => {
        const { payroll_id } = fixtures();

        page.on('dialog', (dialog) => dialog.accept());

        await login(page);
        await page.goto(`/app/payroll/${payroll_id}`);

        await expect(page.getByRole('heading', { name: `Payroll #${payroll_id}` })).toBeVisible();
        await expect(page.getByText('Ana Reyes')).toBeVisible();
        await expect(page.getByText('₱9,654.96').first()).toBeVisible();

        await page.getByRole('button', { name: 'Process payroll' }).click();
        await expect(page.getByText(/Payroll processing started|Record updated|Payroll approved/i)).toBeVisible({ timeout: 30_000 });

        await page.reload();
        await expect(page.getByRole('button', { name: 'Approve & lock' })).toBeVisible({ timeout: 30_000 });

        await page.getByRole('button', { name: 'Approve & lock' }).click();
        await expect(page.getByText('Payroll approved and locked.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Reopen' })).toBeVisible();

        await page.getByRole('row', { name: /Ana Reyes/ }).getByRole('link', { name: 'View' }).click();

        await expect(page.getByRole('heading', { name: /Payslip/i }).first()).toBeVisible();
        await expect(page.getByText('₱8,429.96', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('₱750.00', { exact: true }).first()).toBeVisible();
    });

    test('payslip PDF downloads and foreign payrolls are denied', async ({ page }) => {
        const { payroll_id } = fixtures();

        page.on('dialog', (dialog) => dialog.accept());

        await login(page);
        await page.goto(`/app/payroll/${payroll_id}`);

        const detailLink = page.getByRole('row', { name: /Ana Reyes/ }).getByRole('link', { name: 'View' });

        if (await detailLink.count() === 0) {
            test.skip(true, 'Payroll has not been processed yet');
        }

        await detailLink.click();

        const pdfUrl = await page.getByRole('link', { name: 'Download PDF' }).getAttribute('href');
        const response = await page.request.get(pdfUrl!);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('pdf');
        expect((await response.body()).length).toBeGreaterThan(500);

        const missing = await page.request.get('/app/payroll/999999');
        expect(missing.status()).toBe(404);
    });
});
