import { Page } from '@playwright/test';

/**
 * The attendance journey freezes the server clock per request through the
 * local-only `X-E2E-Now` header, so the portal records a punch on the day the
 * test is pretending it is.
 */
export function manilaMoment(date: string, time: string): string {
    return `${date}T${time}:00+08:00`;
}

export async function setClock(page: Page, moment: string): Promise<void> {
    await page.setExtraHTTPHeaders({ 'X-E2E-Now': moment });
}

export async function clearClock(page: Page): Promise<void> {
    await page.setExtraHTTPHeaders({});
}
