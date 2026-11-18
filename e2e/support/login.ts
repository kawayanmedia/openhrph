import { expect, Page } from '@playwright/test';

import { credentials } from './fixtures';

export async function login(
    page: Page,
    email: string = credentials.email,
    password: string = credentials.password,
): Promise<void> {
    await page.goto('/app/login');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/app\/dashboard/);
}

export async function signOut(page: Page, displayName: string): Promise<void> {
    await page.getByText(displayName).first().click();
    await page.getByRole('button', { name: 'Sign out' }).click();

    await expect(page).toHaveURL(/\/app\/login/);
}
