# Open-source release checklist

Use this checklist before making the repository public or publishing a fork. It is written for maintainers, not end users.

> **Important:** removing a secret from the current branch does not remove it from Git history. Any credential that has ever been committed must be treated as compromised and rotated.

## 1. Rotate exposed credentials first

The following items were present in this repository before the open-source cleanup. Rotate them before publishing, even if they are removed from the current tree.

| Item | Where it appeared | Action |
| --- | --- | --- |
| BugSnag API key | `.env.example` | Regenerate or revoke the project key. |
| Reverb app secret/key/id | `.env.example` | Regenerate the Reverb application credentials. |
| Application key | `.env.example` | Generate a new `APP_KEY`. If the old key encrypted stored data, plan a re-encryption/migration. |
| AWS account id / queue prefix | `.env.example` | Treat the account id as public information, but remove it from examples. Rotate any associated access keys. |
| User and employee data | `db.sql` | Removed from the tree. Purge it from history and review backups. |

After rotating:

- Invalidate active sessions and remember-me tokens if the `APP_KEY` or session configuration changed.
- Update deployment secrets in AWS SSM, Laravel Cloud, or your secret manager.
- Check CI/CD variables for the same values.
- Ask collaborators to rotate their local copies.

## 2. Purge secrets and personal data from history

Back up the repository first, then rewrite history with `git filter-repo` (recommended) or BFG Repo-Cleaner.

```bash
# Install git-filter-repo, then run from a fresh clone.
git filter-repo --path auth.json --path db.sql --invert-paths

# The schema is maintained as Laravel migrations, so the old dump can be
# removed outright.
```

If the repository has already been pushed:

```bash
git push --force --all
git push --force --tags
```

Then:

- Ask collaborators to re-clone instead of pulling the rewritten history.
- Delete and recreate forks if possible.
- Invalidate old release tarballs and artifacts that contain the removed files.
- Review GitHub Actions logs, issue attachments, and pull-request diffs for leaked values.
- Search the history for other secrets:

```bash
git log --all -p -S 'REVERB_APP_SECRET' -- .env.example
git log --all -p -S 'BUGSNAG_API_KEY' -- .env.example
git grep -I -n -E '(api[_-]?key|secret|password|token|private[_-]?key)' $(git rev-list --all) -- ':!vendor' ':!node_modules' | head
```

## 3. Remove production data

- [ ] `db.sql` is no longer in the working tree or history. The schema is maintained as Laravel migrations, so a fresh database is created with `php artisan migrate`.
- [ ] No employee names, addresses, birth dates, government ids, bank accounts, photos, fingerprints, or payslips remain in the repository.
- [ ] `storage/`, `public/attachments/`, `biometric-logs/`, and `govt-forms/` contain no real documents or logs.
- [ ] Playwright fixtures (`storage/e2e-fixtures.json`) and screenshots contain only synthetic data.
- [ ] Issue and pull-request attachments have been reviewed.
- [ ] `.gitignore` covers `.env`, `auth.json`, `db.sql`, logs, test artifacts, and uploaded media.

## 4. Review configuration examples

- [ ] `.env.example` contains no real keys, passwords, account ids, or hostnames.
- [ ] `APP_KEY` is blank and the setup guide tells users to run `php artisan key:generate`.
- [ ] `auth.json.example` contains placeholders only.
- [ ] `vapor.yml` does not contain a real project id if that is sensitive for your organisation.
- [ ] Example URLs use `localhost` or `example.com`, not a live tenant.
- [ ] Mail, Redis, SQS, Reverb, and BugSnag values are blank or clearly fake.

## 5. Check licences and attribution

- [ ] The root `LICENSE` is present and matches the intended licence (MIT in `composer.json`).
- [ ] Vendored or copied code, fonts, icons, PDFs, and sample documents are compatible with the project licence.
- [ ] `docs/2023-Handbook-on-Workers-Statutory-Benefits.pdf` is a public government reference and is attributed.
- [ ] Third-party notices are included where required.

## 6. Harden the default deployment

- [ ] `APP_DEBUG=false` and `APP_ENV=production` in production.
- [ ] HTTPS is enforced and cookies are secure.
- [ ] The database is not publicly accessible.
- [ ] Default or demo accounts are not created automatically.
- [ ] `admin:create` is the documented path for the first owner account.
- [ ] Queue workers and the scheduler run under a non-root user.
- [ ] Backups are encrypted and tested.
- [ ] Error reporting does not include request bodies, credentials, or personal data.

## 7. Publish

- [ ] `README.md` has accurate setup, testing, deployment, security, and licence sections.
- [ ] `SECURITY.md`, `CONTRIBUTING.md`, and `CODE_OF_CONDUCT.md` are present.
- [ ] The documentation hub (`docs/README.md`) has no broken links.
- [ ] CI runs the test suite, Pint, the front-end build, and deployment linters.
- [ ] A tagged release exists and its artifacts were built from the cleaned history.
- [ ] A post-publication scan of the repository and history finds no secrets or personal data.

## 8. After publication

- Monitor the repository's security advisories and dependency alerts.
- Keep `composer.lock` and `package-lock.json` current.
- Re-run the history and secret scans before every major release.
- If a leak is found, rotate first, then remove and disclose responsibly.
