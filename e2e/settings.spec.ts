import { expect, test } from '@playwright/test';

import { login } from './support/login';

test.describe('settings', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    for (const page of ['general', 'attendance', 'payroll', 'leave']) {
        test(`renders the ${page} settings page`, async ({ page: browserPage }) => {
            await browserPage.goto(`/app/settings/${page}`);

            await expect(browserPage.getByRole('heading', { level: 1 })).toContainText(/Settings/i);
            await expect(browserPage.getByRole('button', { name: 'Save' })).toBeVisible();
        });
    }

    test('saves the general settings', async ({ page }) => {
        await page.goto('/app/settings/general');

        const tradeName = process.env.E2E_TRADE_NAME ?? 'E2E Trading Co';

        await page.getByLabel('Trade Name').fill(tradeName);
        await page.getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('Settings saved.')).toBeVisible();

        await page.reload();
        await expect(page.getByLabel('Trade Name')).toHaveValue(tradeName);
    });
});
