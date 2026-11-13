import { readFileSync } from 'node:fs';
import path from 'node:path';

export interface E2eFixtures {
    company_id: number;
    payroll_id: number;
    payroll_group_id: number;
    user: string;
}

export function fixtures(): E2eFixtures {
    const file = path.resolve(process.cwd(), 'storage/e2e-fixtures.json');

    return JSON.parse(readFileSync(file, 'utf8')) as E2eFixtures;
}

export const credentials = {
    email: process.env.E2E_EMAIL ?? 'e2e@hris.test',
    password: process.env.E2E_PASSWORD ?? 'E2ePassword!2345',
};
