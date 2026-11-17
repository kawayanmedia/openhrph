import { expect, test } from '@playwright/test';

import { login } from './support/login';

test.describe('dark mode', () => {
    test('renders the dashboard in dark mode', async ({ page }) => {
        await login(page);

        await page.evaluate(() => {
            document.documentElement.classList.add('dark');
            localStorage.theme = 'dark';
        });

        await page.reload();

        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });
});
