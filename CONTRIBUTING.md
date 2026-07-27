# Contributing

Contributions are welcome. To keep the package green:

1. Fork and branch from `master`.
2. Run the quality gate before opening a PR:
   ```bash
   composer format         # Pint (laravel preset + strict types)
   composer analyse        # PHPStan level 8, empty baseline
   composer test-coverage  # Pest, 100% line coverage on src/
   ```
   Front-end changes additionally need `npm run package` (Prettier + ESLint + Vite build) so the compiled `dist/` assets stay in sync.
3. Every PHP file starts with `<?php`, a blank line, then `declare(strict_types=1);`.
4. No `final` classes (an arch test forbids them).
5. Support both Nova 4 and Nova 5 — avoid Nova-5-only APIs.
6. Add tests. The [CHANGELOG](CHANGELOG.md) is generated from the release notes on release — describe your change clearly in the PR instead.

## Security

Please email `paris@big-boss-studio.com` for security issues instead of the issue tracker.
