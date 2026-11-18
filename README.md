# yii2-audit

Records who changed what and when in a Yii2 application.

- **Changes:** ActiveRecord inserts, updates and deletes, with the old and new value of each changed attribute.
- **Requests:** one row per write request (POST, PUT, PATCH, DELETE by default) and per request that changed data:
  user, route, masked parameters, status code and duration.

Rows are stored in a dedicated database on the same MySQL server as the application database. Read-only requests
write nothing.

Not recorded: SQL queries, mail, page views, and bulk writes that bypass ActiveRecord events (`updateAll()`,
`deleteAll()`, raw SQL). Those writes are visible only through the request that ran them.

## Requirements

- PHP 8.1 or later
- Yii 2.0.45 or later
- MySQL 8.0 or later. The audit database must be on the same server as the application database.

## Installation

```bash
composer require websvc/yii2-audit
```

## Audit database

Create the audit database and give the application's database user access to it:

```sql
CREATE DATABASE app_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT SELECT, INSERT, DELETE ON app_audit.* TO 'app'@'%';
-- Needed only by the user that runs migrations
GRANT CREATE, ALTER, INDEX, DROP ON app_audit.* TO 'app'@'%';
```

Change rows are written through the model's own connection with qualified table names (`app_audit.audit_changes`).
MySQL transactions span databases on one server, so when the application rolls back a transaction, including a
nested one, the audit rows for it are rolled back too.

## Configuration

Add the component to the configuration shared by all application parts (for example `common/config/main.php`) and
bootstrap it:

```php
'bootstrap' => ['audit'],
'components' => [
    'audit' => [
        'class' => \websvc\audit\Audit::class,
        'database' => 'app_audit',
    ],
],
```

| Option | Default | Meaning |
|---|---|---|
| `db` | `'db'` | Connection used for request rows, the grids and cleanup |
| `database` | `null` | Audit database name. `null` keeps the tables in the connection's own database |
| `trackAllModels` | `false` | Track every ActiveRecord model |
| `excludeModels` | `[]` | Classes (and subclasses) skipped when every model is tracked |
| `ignoreAttributes` | `[]` | Attributes never recorded, for every model |
| `maskAttributes` | `password`, `password_hash`, `auth_key`, `password_reset_token`, `verification_token`, `access_token` | Recorded as changed, with the value replaced by `***` |
| `trackMethods` | `['POST', 'PUT', 'PATCH', 'DELETE']` | Methods that always get a request row. Add `CLI` to record every console run |
| `ignoreRoutes` | `['debug/*', 'gii/*']` | Routes that never get a request row (`*` matches anything) |
| `maskParams` | `password`, `passwd`, `pwd`, `token`, `secret`, `csrf`, `auth_key`, `api_key` | Parameter keys whose values become `***` (case-insensitive, partial match, any depth) |
| `maxParamsBytes` | `65536` | Larger parameter payloads are replaced by a marker that lists the top-level keys |
| `maxValueBytes` | `16384` | Longer attribute values are cut and marked. `0` keeps every value whole |
| `userNameCallback` | `null` | `callable(IdentityInterface $identity): ?string`. Defaults to the identity's `username` |
| `ipCallback` | `null` | `callable(): ?string`. Defaults to `Request::getUserIP()` |
| `failSilently` | `true` | Log audit write errors (category `audit`) instead of breaking the request |

Rows store `Yii::$app->id` next to the user id, because user ids from different applications (for example backend
staff and frontend customers) belong to different people.

### Choosing the tracked models

Add the behavior to the models you want to track:

```php
use websvc\audit\behaviors\AuditBehavior;

public function behaviors()
{
    return [
        'audit' => [
            'class' => AuditBehavior::class,
            'ignoreAttributes' => ['updated_at'],
            'maskAttributes' => ['tax_number'],
        ],
    ];
}
```

Or track every model, except a list:

```php
'audit' => [
    'class' => \websvc\audit\Audit::class,
    'database' => 'app_audit',
    'trackAllModels' => true,
    'excludeModels' => [\common\models\SessionLog::class],
],
```

Both can be combined. A model with the behavior is recorded once, with the behavior's settings.

## Migrations

The console application needs the `audit` component too, so the tables are created in the audit database:

```bash
php yii migrate --migrationNamespaces='websvc\audit\migrations'
```

## Audit pages

The package ships a base controller with three pages: changes (`index`), requests (`requests`) and request detail
(`view`). It denies access to everyone, so each application decides who can open it.

Extend it in the application and add your own access rules:

```php
namespace backend\controllers;

use websvc\audit\controllers\AuditLogController as BaseAuditLogController;
use yii\filters\AccessControl;

class AuditLogController extends BaseAuditLogController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['auditor']],
                ],
            ],
        ];
    }
}
```

The pages are then available at `audit-log/index`, `audit-log/requests` and `audit-log/view`, inside the
application's layout. Because the controller belongs to the application, any access system that works with the
application's own controllers works here too.

Without a subclass, the controller can be mapped with static rules:

```php
'controllerMap' => [
    'audit-log' => [
        'class' => \websvc\audit\controllers\AuditLogController::class,
        'as access' => [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['auditor']]],
        ],
    ],
],
```

Each page prints its title as an `<h1>`. When the application layout already shows `$this->title`, set
`public bool $showTitle = false;` in the subclass, or `'showTitle' => false` in the `controllerMap` entry.

To change the views, override `getViewPath()` in the subclass or map `@websvc/audit/views/audit-log` in the view
theme.

Strings use the `audit` translation category. The package ships `pt-PT`. Configure
`i18n.translations['audit']` to replace or extend it.

## Retention

Map the console controller and run the cleanup on a schedule:

```php
'controllerMap' => [
    'audit' => \websvc\audit\commands\AuditController::class,
],
```

```bash
# Delete rows older than 365 days
php yii audit/cleanup 365
```

## Cost

- Read-only requests write nothing.
- Each write request adds one insert at the end of the request.
- Each tracked save adds one insert. Inside a transaction it adds no commit of its own.
- Rows are inserted with plain SQL, so no table schema is loaded.

## Limits

- Every model connection that is tracked must reach the audit database on the same server. Exclude models that use
  another server, or their saves log an `audit` error each time.
- A request row is written once, at the end of the request. Long-running console workers (queue listeners) share one
  request id and write their request row only when they exit.
- When a request ends with a transaction still open, its request row is written through a separate connection, and
  the change rows inside that transaction disappear with it.
- A request counts as a write when either the method the application used or the method sent on the wire is in
  `trackMethods`, so `_method` and `X-Http-Method-Override` cannot hide a write.
- Request parameters nested deeper than 32 levels are replaced by `[depth limit]`.
- Attribute values are compared before they are cut, so a change beyond `maxValueBytes` is still recorded.
- Two strings are compared as text (`"0123"` and `"123"` differ). A number and a string are compared as numbers
  (`1` and `"1"` are equal).
- The status code is empty when the request ended without sending a response (for example `exit()` or a console
  crash).

## Development

Docker is the only requirement.

```bash
make install   # Start PHP 8.4 and MySQL 8.0 and install dependencies
make test      # Run the test suite
make lint      # Run PHPCS
make down      # Stop the stack
```

## License

BSD-3-Clause. See [LICENSE](LICENSE).
