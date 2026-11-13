import { expect, test } from '@playwright/test';

import { credentials } from './support/fixtures';
import { login } from './support/login';

test.describe('admin login', () => {
    test('signs in with valid credentials and reaches the dashboard', async ({ page }) => {
        await login(page);

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });

    test('shows an error for invalid credentials', async ({ page }) => {
        await page.goto('/app/login');
        await page.fill('#email', credentials.email);
        await page.fill('#password', 'not-the-password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await expect(page).toHaveURL(/\/app\/login/);
    });

    test('redirects guests to the admin login', async ({ page }) => {
        await page.goto('/app/dashboard');

        await expect(page).toHaveURL(/\/app\/login/);
        await expect(page.getByRole('heading', { name: 'Sign in to the workspace' })).toBeVisible();
    });

    test('signs out back to the login screen', async ({ page }) => {
        await login(page);

        await page.getByText('E2E Admin').first().click();
        await page.getByRole('button', { name: 'Sign out' }).click();

        await expect(page).toHaveURL(/\/app\/login/);
    });
});
