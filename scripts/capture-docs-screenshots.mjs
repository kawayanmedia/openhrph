#!/usr/bin/env node
/**
 * Re-captures the user guide screenshots from a running OpenHR PH installation.
 *
 * Every screenshot uses the synthetic E2E company, light mode, a 1600x900 viewport
 * and the Laravel debug bar hidden, which matches the existing files in `docs/images`.
 *
 * Usage (from the repository root):
 *
 *   APP_URL=https://openhrph.test npm run docs:screenshots
 *   npm run docs:screenshots -- --only=admin-positions-light,admin-sector-settings-light
 *
 * Credentials default to the browser-test fixtures users. Point the script at another
 * workspace with E2E_EMAIL / E2E_PASSWORD. Only ever point it at a workspace that holds
 * synthetic data: the images are published with the documentation.
 */
import fs from 'node:fs';
import path from 'node:path';
import {chromium} from 'playwright';

const baseUrl = process.env.APP_URL ?? process.env.E2E_BASE_URL ?? 'https://openhrph.test';
const outDir = path.resolve('docs/images');

const admin = {
  email: process.env.E2E_EMAIL ?? 'e2e@hris.test',
  password: process.env.E2E_PASSWORD ?? 'E2ePassword!2345',
};

const employee = {
  email: process.env.E2E_PORTAL_EMAIL ?? 'e2e-day@hris.test',
  password: process.env.E2E_PORTAL_PASSWORD ?? 'E2ePassword!2345',
};

/** Admin workspace captures. */
const adminCaptures = [
  ['admin-org-units-light', '/app/resources/organization-unit'],
  ['admin-org-unit-create-light', '/app/resources/organization-unit/new'],
  ['admin-positions-light', '/app/resources/position'],
  ['admin-document-create-light', '/app/resources/document/new'],
  ['admin-workflow-definition-create-light', '/app/resources/workflow-definition/new'],
  ['admin-attendance-correction-create-light', '/app/resources/attendance-correction/new'],
  ['admin-performance-cycle-create-light', '/app/resources/performance-cycle/new'],
  ['admin-training-course-create-light', '/app/resources/training-course/new'],
  ['admin-retirement-plan-create-light', '/app/resources/retirement-plan/new'],
  ['admin-compliance-source-create-light', '/app/resources/compliance-source/new'],
  ['admin-workforce-analytics-light', '/app/reports/workforce-analytics'],
  ['admin-employee-master-file-light', '/app/reports/employee-master-file'],
  ['admin-sector-settings-light', '/app/settings/sector'],
  ['admin-requisition-create-light', '/app/resources/job-requisition/new'],
  ['admin-applicant-create-light', '/app/resources/applicant/new'],
];

/** Employee portal captures. */
const portalCaptures = [
  ['portal-leave-request-light', '/portal/leaves/create'],
];

const onlyArgument = process.argv.find((argument) => argument.startsWith('--only='));
const only = onlyArgument
  ? new Set(onlyArgument.replace('--only=', '').split(',').map((name) => name.trim()))
  : null;

const wants = (name) => only === null || only.has(name);

const hideDebugBar = `
  .phpdebugbar, [id^="phpdebugbar"], [class*="debugbar"] { display: none !important; }
  * { animation: none !important; transition: none !important; caret-color: transparent !important; }
`;

/** @param {import('playwright').Page} page */
async function prepare(page) {
  await page.addStyleTag({content: hideDebugBar});
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(400);
}

/** @param {import('playwright').Browser} browser */
async function captureAdmin(browser) {
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: {width: 1600, height: 900},
    colorScheme: 'light',
  });
  const page = await context.newPage();

  await page.goto(`${baseUrl}/app/login`);
  await page.fill('#email', admin.email);
  await page.fill('#password', admin.password);
  await page.getByRole('button', {name: 'Sign in'}).click();
  await page.waitForURL(/\/app\/dashboard/);

  for (const [name, route] of adminCaptures.filter(([name]) => wants(name))) {
    await page.goto(`${baseUrl}${route}`);
    await prepare(page);
    await page.screenshot({path: path.join(outDir, `${name}.png`)});
    console.log(`captured ${name}`);
  }

  await context.close();
}

/** @param {import('playwright').Browser} browser */
async function capturePortal(browser) {
  if (!portalCaptures.some(([name]) => wants(name))) {
    return;
  }

  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: {width: 1600, height: 900},
    colorScheme: 'light',
  });
  const page = await context.newPage();

  await page.goto(`${baseUrl}/portal/login`);
  await page.fill('#email', employee.email);
  await page.fill('#password', employee.password);
  await page.getByRole('button', {name: 'Sign in'}).click();
  await page.waitForURL(/\/portal\/(dashboard|leaves)/);

  for (const [name, route] of portalCaptures.filter(([name]) => wants(name))) {
    await page.goto(`${baseUrl}${route}`);
    await prepare(page);
    await page.screenshot({path: path.join(outDir, `${name}.png`)});
    console.log(`captured ${name}`);
  }

  await context.close();
}

async function main() {
  if (!fs.existsSync(outDir)) {
    throw new Error(`Missing ${outDir}. Run the script from the repository root.`);
  }

  const browser = await chromium.launch();
  try {
    await captureAdmin(browser);
    await capturePortal(browser);
  } finally {
    await browser.close();
  }
}

await main();
