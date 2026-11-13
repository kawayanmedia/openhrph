import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? process.env.APP_URL ?? 'https://openhrph.test';

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/support/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'storage/playwright-report' }]],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'light',
            use: { ...devices['Desktop Chrome'], colorScheme: 'light', viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'dark',
            use: { ...devices['Desktop Chrome'], colorScheme: 'dark', viewport: { width: 1440, height: 900 } },
            testMatch: /.*\.dark\.spec\.ts/,
        },
    ],
});
