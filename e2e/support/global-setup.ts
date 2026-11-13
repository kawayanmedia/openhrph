import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import path from 'node:path';

/**
 * Seeds the isolated E2E company and records the payroll id for the specs.
 */
export default function globalSetup(): void {
    const root = process.cwd();

    const output = execSync('php artisan testing:seed-e2e-company', { cwd: root, encoding: 'utf8' });
    const line = output.trim().split('\n').pop() ?? '{}';

    writeFileSync(path.join(root, 'storage', 'e2e-fixtures.json'), line);
}
