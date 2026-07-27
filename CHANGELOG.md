# Changelog

All notable changes to `nova-permission` will be documented in this file

## v2.0.0 - 2026-07-27

A correctness, quality and tooling release. The general and granular (per-instance) permission features already existed in 1.x but were partly broken — v2.0.0 makes them work, modernizes the package to PHP 8.2+ / Nova 4 & 5 / spatie-permission 6, and adds a full test suite, static analysis and CI. See the [upgrade guide](https://github.com/BBS-Lab/nova-permission/blob/master/UPGRADE.md).

### ⚠️ Breaking changes

- Requires **PHP `^8.2`**, **Laravel Nova `^4.0 || ^5.0`** and **spatie/laravel-permission `^6`**.
- Your authenticatable model must use `BBSLab\NovaPermission\Traits\HasRoles` (not Spatie's) and implement `BBSLab\NovaPermission\Contracts\CanOverridePermission` — the policies call `hasPermissionToOnModel()`, which only exists on the package trait.
- Package migrations are now real `.php` files that **auto-run** on `php artisan migrate`; only Spatie's migration is published (`--tag="permission-migrations"`).
- The `gate_cache` config key is replaced by an opt-in `cache` section (disabled by default).
- The base `Policy` methods now return `?bool` and keep a `Model $model` parameter — do not narrow it in overrides.

### 🐛 Fixed

- **Granular (per-instance) permissions now work.** They shipped in 1.x but were effectively dead: the migration only added the `authorizable`/`group` columns and never replaced Spatie's `unique(name, guard_name)`, so a same-name scoped permission could not be created, and `Authorizations::scopeAuthorize()` filtered against a hardcoded `chambers.id` column left over from another project. Now installs a composite unique index on `(name, guard_name, authorizable_id, authorizable_type)` (with an explicit, MySQL-safe index name) and resolves the table/key dynamically.
- **Nova 4 support restored** — it had silently broken. Removed the Nova-5-only `laravel/nova-devtool` and (unused) `nova-kit/nova-packages-tool` dependencies and added a Nova 4/5 authorization-compatibility shim.
- **Permission builder search** now filters the groups and permissions as you type (debounced, with a loading indicator); previously typing had no effect.
- Correct permission/role class resolution and per-model authorization-cache invalidation; hardened request/controller handling.

### 🔀 Changed

- The gate cache is now **opt-in** and centralized (`config('nova-permission.cache.enabled')`, default `false`; `NOVA_PERMISSION_CACHE` / `NOVA_PERMISSION_CACHE_TTL`).
- Documentation rewritten (Requirements, granular-permissions guide, caching, Testing). The workbench is now both a manual test harness and a usage demo: seeded `admin`/`writer`/`reader` roles, `Post` and `Service` resources showcasing general vs granular permissions, and Nova impersonation.

### ➕ Added

- Full test suite (Pest, 100% line coverage, mutation testing, architecture tests), PHPStan level 8, Pint, a GitHub Actions CI matrix (Nova 4 & 5 × Laravel 11–13 × PHP 8.3–8.5) and Dependabot.

**Full changelog**: https://github.com/BBS-Lab/nova-permission/compare/v1.1.1...v2.0.0

## 1.0.0 - 2019-11-10

- initial release
