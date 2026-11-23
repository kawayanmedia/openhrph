import { readFileSync } from 'node:fs';
import path from 'node:path';

export interface E2eOnboardingFixtures {
    company_id: number;
    user: string;
    password: string;
}

export interface E2eAttendanceEmployee {
    key: string;
    id: number;
    name: string;
    /** How the admin tables print the employee: "Last, First". */
    label: string;
    employee_number: string;
    email: string;
    password: string;
    shift: string;
    time_in: string;
    time_out: string;
    daily_rate: number;
    basic_salary: number;
}

export interface E2eAttendanceFixtures {
    company_id: number;
    payroll_ids: {
        first_half: number;
        second_half: number;
    };
    period: {
        from: string;
        to: string;
        first_half_end: string;
        second_half_start: string;
    };
    admin: {
        email: string;
        password: string;
    };
    employees: E2eAttendanceEmployee[];
}

export interface E2eFixtures {
    company_id: number;
    payroll_id: number;
    payroll_group_id: number;
    user: string;
    onboarding: E2eOnboardingFixtures;
    attendance: E2eAttendanceFixtures;
}

export function fixtures(): E2eFixtures {
    const file = path.resolve(process.cwd(), 'storage/e2e-fixtures.json');

    return JSON.parse(readFileSync(file, 'utf8')) as E2eFixtures;
}

export const credentials = {
    email: process.env.E2E_EMAIL ?? 'e2e@hris.test',
    password: process.env.E2E_PASSWORD ?? 'E2ePassword!2345',
};
