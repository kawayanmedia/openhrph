import { expect, Page } from '@playwright/test';

import { credentials } from './fixtures';

export async function login(page: Page): Promise<void> {
    await page.goto('/app/login');
    await page.fill('#email', credentials.email);
    await page.fill('#password', credentials.password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/app\/dashboard/);
}
