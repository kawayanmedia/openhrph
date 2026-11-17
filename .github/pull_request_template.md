## Summary

What problem does this pull request solve, and how?

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor
- [ ] Documentation
- [ ] Deployment or infrastructure
- [ ] Dependency update

## Related issues

Closes #

## How was this tested?

Describe the tests, commands, or manual checks you ran.

```text
vendor/bin/pest
make -C payroll-engine test
npm run build
npm run e2e
```

## Screenshots

For UI changes, include before/after screenshots in light and dark mode.

## Checklist

- [ ] The change is focused and does not include unrelated edits.
- [ ] Tests cover the new behaviour and its failure case.
- [ ] `vendor/bin/pint --dirty --format agent` passes.
- [ ] Relevant tests pass.
- [ ] `npm run build` succeeds for front-end changes.
- [ ] Light and dark mode were checked for UI changes.
- [ ] No secrets, personal data, or generated artifacts are included.
- [ ] Documentation was updated when behaviour or setup changed.
