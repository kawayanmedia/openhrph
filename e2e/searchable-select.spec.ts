import { expect, test } from '@playwright/test';

import { login } from './support/login';

test.describe('searchable selects', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/app/resources/office/new');
    });

    test('filters a long option list and syncs the native select', async ({ page }) => {
        const region = page.getByRole('combobox', { name: 'Region' });

        await region.click();

        const dialog = page.getByRole('dialog', { name: 'Region' });
        const listbox = dialog.getByRole('listbox', { name: 'Region' });

        await dialog.getByLabel('Search options').fill('region vii');

        await expect(listbox.getByRole('option')).toHaveCount(2);
        await listbox.getByRole('option', { name: 'Region VII', exact: true }).click();

        await expect(region).toHaveText('Region VII');
        await expect(page.locator('#form-region')).toHaveValue('Region VII');
    });

    test('selects an option with the keyboard only', async ({ page }) => {
        const region = page.getByRole('combobox', { name: 'Region' });

        await region.focus();
        await page.keyboard.press('ArrowDown');

        const dialog = page.getByRole('dialog', { name: 'Region' });

        await expect(dialog.getByLabel('Search options')).toBeFocused();

        await page.keyboard.type('barmm');
        await page.keyboard.press('Enter');

        await expect(region).toHaveText('BARMM');
        await expect(page.locator('#form-region')).toHaveValue('BARMM');
    });

    test('keeps short option lists as native selects', async ({ page }) => {
        await expect(page.locator('#form-industry-trigger')).toHaveCount(0);

        await page.locator('#form-industry').selectOption('GENERAL');

        await expect(page.locator('#form-industry')).toHaveValue('GENERAL');
    });
});
