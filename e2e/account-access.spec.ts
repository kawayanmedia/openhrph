import { expect, test } from '@playwright/test';

import { fixtures } from './support/fixtures';
import { login, signOut } from './support/login';

test.describe('account access', () => {
    test('redirects guests to the admin sign-in screen', async ({ page }) => {
        await page.goto('/app/dashboard');

        await expect(page).toHaveURL(/\/app\/login/);
        await expect(page.getByRole('heading', { name: 'Sign in to the workspace' })).toBeVisible();
    });

    test('rejects an invalid password without signing the user in', async ({ page }) => {
        const { onboarding } = fixtures();

        await page.goto('/app/login');
        await page.fill('#email', onboarding.user);
        await page.fill('#password', 'not-the-password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
        await expect(page).toHaveURL(/\/app\/login/);
    });

    test('signs a provisioned owner in and back out', async ({ page }) => {
        const { onboarding } = fixtures();

        await login(page, onboarding.user, onboarding.password);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

        await signOut(page, 'E2E Onboarding Admin');
    });

    test('password recovery link opens a reset form', async ({ page }) => {
        await page.goto('/app/login');
        await page.getByRole('link', { name: 'Forgot password?' }).click();

        await expect(page.getByText(/reset your password|forgot your password/i).first()).toBeVisible();
    });
});
