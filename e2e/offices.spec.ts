import { expect, test } from '@playwright/test';

import { login } from './support/login';

test.describe('offices', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/app/resources/office');
    });

    test('creates, searches, edits and deletes an office', async ({ page }) => {
        const name = `E2E Office ${Date.now()}`;

        await page.getByRole('link', { name: /Create Office/i }).click();
        await page.getByLabel('Code').fill('E2E-OFF');
        await page.getByLabel('Name').fill(name);
        await page.getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText('Record created.')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toContainText(name);

        await page.goto('/app/resources/office');
        await page.getByPlaceholder(/Search Offices/i).fill(name);
        await page.keyboard.press('Enter');

        const row = page.getByRole('row', { name: new RegExp(name) });
        await expect(row).toBeVisible();

        await row.getByRole('link', { name: 'Edit' }).click();
        await page.getByLabel('Name').fill(`${name} Updated`);
        await page.getByRole('button', { name: 'Update' }).click();

        await expect(page.getByText('Record updated.')).toBeVisible();

        await page.getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByRole('heading', { name: /Delete Office/i })).toBeVisible();
        await page.getByRole('button', { name: 'Delete', exact: true }).last().click();

        await expect(page.getByText('Record deleted.')).toBeVisible();
    });

    test('shows a validation error when the name is missing', async ({ page }) => {
        await page.getByRole('link', { name: /Create Office/i }).click();
        await page.getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText('The Name field is required.')).toBeVisible();
    });
});
