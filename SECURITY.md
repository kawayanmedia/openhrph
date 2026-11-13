# Security policy

## Supported versions

Security fixes are made on the current `main` branch. If you maintain a fork, keep it up to date with the upstream repository.

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Use GitHub's private vulnerability reporting:

1. Open the repository's **Security** tab.
2. Choose **Report a vulnerability**.
3. Include the affected version/commit, reproduction steps, impact, and any suggested fix.

If private reporting is unavailable, contact the maintainers through the repository's listed security contact. Do not include exploit details in a public discussion.

You can expect an acknowledgement, an assessment, and a coordinated disclosure timeline. Please give the maintainers reasonable time to release a fix before publishing details.

## Handling secrets

- Never commit `.env`, `auth.json`, API keys, private keys, database dumps, or production data.
- Use `.env.example` for documented variable names and safe defaults only.
- Rotate a credential immediately if it is exposed. Removing a file from the current branch does not remove it from Git history.
- See the [open-source release checklist](docs/security/open-source-checklist.md) before publishing a fork.

## Scope

The following are in scope:

- Authentication and authorisation bypasses
- Tenant isolation failures (reading or writing another company's data)
- SQL injection, command injection, and unsafe deserialisation
- Server-side request forgery and unsafe file upload handling
- Exposure of payroll, employee, or credential data
- Privilege escalation through groups, permissions, or admin routes

The following are generally out of scope:

- Vulnerabilities in dependencies that are already publicly disclosed and have an upstream fix (report them upstream and open a dependency update issue)
- Issues that require a compromised server, database, or administrator account
- Denial-of-service attacks that rely on unlimited resources
- Missing security headers without a demonstrated impact

## Safe harbour

We will not pursue legal action against researchers who:

- Act in good faith and avoid privacy violations, data destruction, and service disruption
- Only test accounts and data they own or have explicit permission to use
- Report findings privately and allow reasonable time for a fix

Thank you for helping keep OpenHR PH and its users safe.
