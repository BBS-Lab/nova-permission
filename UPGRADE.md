# Upgrade guide

## From v1.x to v2.0.0

v2.0.0 modernizes the package (PHP 8.2+, Laravel Nova 4 & 5, spatie/laravel-permission ^6)
and reworks how permissions are resolved. This is a breaking release; follow the steps below.

`composer update bbs-lab/nova-permission` pulls in the new requirements.

### 1. Requirements

- PHP `^8.2`
- Laravel Nova `^4.0 || ^5.0`
- spatie/laravel-permission `^6.0`

### 2. Use this package's `HasRoles` trait on your User model

The Nova policies resolve per-instance abilities through `hasPermissionToOnModel()`,
which exists only on **this package's** `HasRoles` trait. Replace Spatie's trait and
implement the `CanOverridePermission` contract:

```php
use BBSLab\NovaPermission\Contracts\CanOverridePermission;
use BBSLab\NovaPermission\Traits\HasRoles; // not Spatie\Permission\Traits\HasRoles
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements CanOverridePermission
{
    use HasRoles;
}
```

With Spatie's trait every protected resource throws `Call to undefined method
…::hasPermissionToOnModel()`.

### 3. Migrations now run automatically

This package's migrations are now real `.php` files that run on `php artisan migrate`
— there is no `.php.stub` publish step anymore. Only Spatie's migration is published:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
php artisan migrate
```

The added migration replaces Spatie's `unique(name, guard_name)` on the `permissions`
table with a composite unique index that includes the polymorphic `authorizable`
columns, so the same permission name can exist once generally and once per scoped
instance. Existing rows are unaffected.

### 4. Policy signatures

If you extend the base `BBSLab\NovaPermission\Policies\Policy`, overridden methods now
return `?bool` and keep the base `Model $model` parameter type (do **not** narrow it to
a concrete model class — PHP forbids that and it fatals at class load):

```php
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;

public function view(Authorizable $user, Model $model): ?bool
{
    // ...
    return null;
}
```

### 5. Config

Publishing the config uses the `nova-permission-config` tag:

```bash
php artisan vendor:publish --tag="nova-permission-config"
```

A new opt-in `cache` section is available in `config/nova-permission.php`
(disabled by default).
