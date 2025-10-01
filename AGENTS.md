# AGENTS.md — AdamRMS Repository Guide for Agents and Contributors

This document explains how this repository is structured, how the application runs, and the conventions to follow when reading, changing, or extending the code. It is intended for both humans and AI coding agents working inside this repo.

Scope: root of the repository (applies to all files).


## Overview

- Stack: PHP 8.x (Apache), MySQL 8, Twig templates, Composer dependencies.
- Purpose: Asset, projects, and instance (business) management with a web UI and JSON API.
- Storage: AWS S3 (or compatible) for file storage with optional CloudFront; local S3 emulator supported in devcontainer.
- Email: SendGrid, Mailgun, Postmark, or SMTP providers via pluggable handlers.
- Migrations: Phinx for DB schema and seeds.
- API docs: swagger-php annotations embedded in PHP files.


## How To Run

- Docker quickstart (local):
  - `docker compose up --build` (exposes web at http://localhost:8088, DB at 3306)
  - DB credentials are taken from `docker-compose.yml` and `.env` (root password).
  - On container start, `migrate.sh` runs Phinx migrations and seeds.
- Devcontainer (VS Code): see `.devcontainer/` for a full local stack with:
  - MySQL, Mailpit (SMTP test UI), S3 emulator, phpMyAdmin, and automatic `composer install` + migrations.
- First‑run config: after DB connects, missing configuration is collected using the built‑in config form (`src/common/libs/Config/configForm.twig`).


## Directory Layout

- Root
  - `Dockerfile`, `docker-compose.yml`: container setup and orchestration.
  - `composer.json`, `composer.lock`: PHP dependencies.
  - `phinx.php`, `migrate.sh`: DB migration configuration and entrypoint.
  - `db/` migrations, seeds, and generated `schema.php`.
  - `Readme.md`: placeholder readme.
- `src/` (document root)
  - `index.php`: dashboard + CMS fallback; renders Twig via `$TWIG`.
  - `*.php` + matching `*.twig`: page controllers and templates.
  - `.htaccess`: basic 404 mapping.
  - `common/` core bootstrapping and shared libraries:
    - `head.php`: Composer autoload, Twig, DB (`MysqliDb`), config, Sentry, CSP, Twig extensions, session; defines `$PAGEDATA`.
    - `headSecure.php`: wraps `head.php`, enforces login, sets Sentry scope, analytics log, and provides instance join suggestions; renders interstitials (ToS, email verify, force change password, suspended instance).
    - `libs/`:
      - `Auth/` (`main.php`, `instanceActions.php`, `serverActions.php`): token/session auth, JWT, permission checks, magic link, email flows; exposes `$AUTH` (class `bID`).
      - `Config/` (`Config.php`, `configStructureArray.php`, `configForm.twig`): typed, validated config with env fallbacks; caches values and builds/validates the setup form.
      - `bCMS/` (`bCMS.php`, `projectFinance.php`): sanitization, HTML purifier, S3 URL generation, CloudFront signing, analytics/audit helpers, instance capacity checks, formatting utils.
      - `Email/` (handlers): `SMTP`, `Sendgrid`, `Mailgun`, `Postmark` implementations.
      - `Search/`, `Telemetry/`, `twigExtensions.php`: custom Twig filters/functions (`s3URL`, `money`, `timeago`, etc.).
  - `api/` JSON endpoints organized by domain (account, assets, projects, instances, etc.).
    - `apiHead.php`: JSON headers, CORS, request body merge into `$_POST`, `finish()` helper, money/mass formatters, shared helpers.
    - `apiHeadSecure.php`: requires auth, sets Sentry scope, logs analytics; include this for protected endpoints.
    - `openApiYAMLHeaders.php`: swagger-php Info/Servers/Tags declarations used by annotations.
    - `notifications/`: `main.php` (router) + `email/` (`email.php`, `email_template.twig`).
    - `file/`: S3/CloudFront passthrough (`index.php`) and admin actions (share, delete, rename, etc.).
  - Feature directories: `assets`, `clients`, `instances`, `project`, `training`, `cms`, `server`, `login`, etc. Each mixes page controllers (`.php`) and Twig templates.
  - `static-assets/`: CSS/JS/images and vendor static libs.
  - `assets/templates` + `assets/widgets`: shared Twig fragments and dashboard widgets with `statsWidgets.php` backend.


## Core Runtime Objects

- `$TWIG`: Twig environment rooted at `src/` (templates referenced relative to `src/`).
- `$DBLIB`: `MysqliDb` instance configured from `DB_*` env vars.
- `$CONFIGCLASS`: `Config` accessor; use `$CONFIGCLASS->get('KEY')` for single values.
- `$CONFIG`: array of cached config values (from DB/env/defaults) for fast access.
- `$AUTH`: authentication/session/permission manager (`bID`), exposes:
  - `$AUTH->login` boolean
  - `$AUTH->data` user + instance data, permissions arrays
  - `serverPermissionCheck('PERM')`, `instancePermissionCheck('PERM')`, `setInstance(id)`
  - Token/JWT helpers, magic link, email verification and password reset senders.
- `$bCMS`: app helpers (sanitization, S3 URL signing, audit/analytics, HTML purify, etc.).
- `$PAGEDATA`: default render context: `['CONFIG' => $CONFIG, 'VERSION' => $bCMS->getVersionNumber()]` plus page‑specific fields.


## Security & Privacy

- Use `headSecure.php` on any page requiring login. Use `apiHeadSecure.php` for protected API endpoints.
- CSP headers are assembled from config and sent by `head.php`; avoid adding inline hosts/scripts outside the allowed lists.
- Sanitization:
  - Escape user strings via Twig by default; only output raw HTML where content is already sanitized (`|raw`) or through `$bCMS->cleanString()`.
  - Prefer parameterized DB queries through `MysqliDb`; never interpolate untrusted values without `$DBLIB->escape()`.
- Files: `bCMS->s3URL()` enforces secure, requires‑instance rules by file type and can generate presigned URLs or CloudFront signed URLs.
- Tokens: `authTokens` are IP‑bound and expire after 12 hours.
- Error reporting: If configured, Sentry DSN is used in production; user scope is set in secure contexts.


## API Conventions

- Bootstrap: include `apiHeadSecure.php` (auth required) or `apiHead.php` (public or internally used routes).
- Request body: JSON body is merged into `$_POST` and `$_GET` for compatibility; prefer reading from `$_POST`.
- Response: use `finish($result, $error, $response)` to return JSON. On success, set `$result = true` and pass payload in `$response`.
- Money/mass helpers: `apiMoney()` and `apiMass()`.
- Permissions: check with `$AUTH->serverPermissionCheck()` and/or `$AUTH->instancePermissionCheck()` before modifying data.
- OpenAPI docs: annotate endpoints with swagger-php `@OA\...` blocks; global headers are in `src/api/openApiYAMLHeaders.php`.

Example skeleton for a secure endpoint:

```php
<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck('SOME:PERMISSION')) finish(false, ['message' => 'Permission denied']);
// Validate input
$foo = $bCMS->sanitizeString($_POST['foo'] ?? '');
// Do work with $DBLIB
finish(true, null, ['ok' => true]);
```


## UI (Twig) Conventions

- Render pages via `$TWIG->render('path/to/template.twig', $PAGEDATA)`.
- Base layout: `src/assets/template.twig` (includes CSS/JS, helpers, AJAX error handling).
- Set `$PAGEDATA['pageConfig']` with `TITLE`, optional `BREADCRUMB`, `NOMENU`, etc. for layout behavior.
- Common Twig filters/functions available:
  - `s3URL(id, size?)`, `timeago`, `formatsize`, `money`, `moneyDecimal`, `moneyPositive`, `moneySymbol`, `mass`, `md5`, `randomString`, `jsonDecode`, permissions checks, etc.
- Keep logic in PHP controllers; limit Twig to rendering and simple conditionals/loops.


## Emails & Notifications

- Entry point: `src/api/notifications/main.php` exposes `notify($typeId, $userId, $instanceId, $headline, $messageHtml?, $templatePath?, $dataArray?)`.
- Providers: configured by `EMAILS_PROVIDER` and `EMAILS_ENABLED` via `Config`; supported: Sendgrid, Mailgun, Postmark, SMTP.
- Email rendering: `src/api/notifications/email/email.php` renders `api/notifications/email/email_template.twig` with:
  - `SUBJECT`, `HTML` (sanitized via `$bCMS->cleanString`), optional `TEMPLATE` (Twig partial), `DATA` (template data), `INSTANCE`, `CONFIG`, `FOOTER`.
- Add custom email bodies as Twig partials (e.g. `src/api/instances/addUser-EmailTemplate.twig`) and pass path as `TEMPLATE`.


## Files & Storage

- Download: `src/api/file/index.php` returns or redirects to a presigned URL based on `f`, `d`, `r`, `e`, and optional `key`.
- Generation: use `$bCMS->s3URL($fileId, $forceDownload?, $expires?, $shareKey?)`.
- Visibility is enforced by file type (secure/instance‑scoped exceptions are documented in code comments in `bCMS.php`).
- Twig `|s3URL` filter returns a redirecting API URL for convenience in templates.


## Database & Migrations

- Migrations: `db/migrations/*.php` (Phinx). Seeds in `db/seeds/`.
- Config table: dynamic settings stored in `config` table are validated/typed using `Config` and `configStructureArray.php`.
- On container start or devcontainer postStart, migrations + seeds run automatically.
- When changing schema:
  - Create a new Phinx migration; keep changes minimal and reversible.
  - Avoid unrelated refactors inside migration files.


## Analytics, Telemetry, Errors

- Analytics: every page/API call is logged into `analyticsEvents` with context (user, instance, token, path, payload).
- Telemetry: see `src/common/libs/Telemetry` and Config keys `TELEMETRY_*`. Levels: Disabled, Limited, Standard.
- Sentry: set DSN via config to enable server‑side error capture; disabled in `DEV_MODE` unless allowed by `USE-DEV` permission.


## Coding Guidelines

- Keep changes focused and minimal; match current structure and naming.
- Prefer adding new endpoints under existing domain folders in `src/api/<domain>/`.
- For UI pages, co‑locate controller `.php` and view `.twig`. Include `headSecure.php` for protected pages.
- Use `$CONFIGCLASS->get('KEY')` instead of hardcoding config values.
- Sanitize untrusted input and prefer `$DBLIB` methods for queries.
- Do not echo JSON directly from API endpoints; always use `finish()`.
- Do not add framework/formatter configs; follow the existing style.


## Common Tasks (Recipes)

- Add a secure API endpoint:
  - Create `src/api/<domain>/<action>.php`.
  - `require_once __DIR__ . '/../apiHeadSecure.php';`
  - Check permissions with `$AUTH`.
  - Validate inputs; run DB ops with `$DBLIB`; return via `finish()` with payload.
  - Optionally add swagger‑php `@OA\Post`/`@OA\Get` annotations.

- Add a page + template:
  - Add `src/<feature>.php` and `src/<feature>.twig`.
  - In PHP, `require_once __DIR__ . '/common/headSecure.php';`, prepare `$PAGEDATA`; `echo $TWIG->render('feature.twig', $PAGEDATA);`.

- Send an email:
  - `require_once __DIR__ . '/../notifications/main.php';`
  - Build a `$user` via `$bCMS->notificationSettings($userId)` or an existing lookup path.
  - Call `notify(TYPE_ID, $userId, $instanceId, $subject, $messageHtml?, $templatePath?, $dataArray?)`.

- Add a config setting:
  - Extend `src/common/libs/Config/configStructureArray.php` with a new key (type, validation, env fallback, default).
  - Read via `$CONFIGCLASS->get('YOUR_KEY')`.

- Work with S3 files:
  - Generate download link via `$bCMS->s3URL($fileId, $forceDownload)` or use API `/api/file/index.php`.
  - Use Twig `|s3URL` to embed links in templates.

- Change schema:
  - Write a new Phinx migration in `db/migrations/` and run `php vendor/bin/phinx migrate`.


## Configuration Keys (Selected)

- General: `ROOTURL`, `TIMEZONE`, `PROJECT_NAME`.
- Auth: `AUTH_SIGNUP_ENABLED`, `AUTH_JWTKey`, Google/Microsoft OAuth keys.
- Email: `EMAILS_ENABLED`, `EMAILS_PROVIDER`, `EMAILS_FROMEMAIL`, SMTP fields, provider API key.
- Files: `FILES_ENABLED`, `AWS_S3_*`, optional `AWS_CLOUDFRONT_*`.
- Errors: `ERRORS_PROVIDERS_SENTRY`.
- Billing: `STRIPE_KEY`, `STRIPE_WEBHOOK_SECRET`.
- Telemetry: `TELEMETRY_*`.

See `src/common/libs/Config/configStructureArray.php` for the full, validated list.


## Notes for AI Agents

- Respect this file’s guidance and the existing architecture.
- When adding code:
  - Choose the correct bootstrap (`head.php`/`headSecure.php`, `apiHead.php`/`apiHeadSecure.php`).
  - Keep logic out of Twig; use Twig only for rendering.
  - Use `$DBLIB` for DB writes/reads and `$AUTH` for permission checks.
  - Use `$CONFIGCLASS->get()` for dynamic configuration.
  - Prefer existing helpers in `$bCMS` and Twig filters where available.
- When modifying DB schema, add a Phinx migration and do not break seeds.
- Do not introduce new external services without updating container/devcontainer configs.
- Avoid logging sensitive data; rely on Sentry configuration for error reporting.


## Troubleshooting

- Database connection errors on boot usually indicate missing `DB_*` env vars or the DB not yet healthy; `docker compose` waits until the DB container is healthy.
- First page asking for configuration: fill out required values (ROOTURL, JWT, etc.); after submit, you will be redirected to the app.
- Email not sending in dev: ensure `EMAILS_ENABLED=Enabled` and provider configured. In devcontainer, SMTP is preconfigured for Mailpit (`http://localhost:8083`).
- S3 errors in dev: use devcontainer’s S3 emulator and ensure `CONFIG_AWS_S3_*` envs are set (see `.devcontainer/docker-compose.yml`).


## File References (Key Entrypoints)

- src/common/head.php
- src/common/headSecure.php
- src/common/libs/Auth/main.php
- src/common/libs/Config/Config.php
- src/common/libs/bCMS/bCMS.php
- src/common/libs/twigExtensions.php
- src/api/apiHead.php
- src/api/apiHeadSecure.php
- src/api/openApiYAMLHeaders.php
- src/api/file/index.php
- src/api/notifications/email/email.php
- src/api/notifications/email/email_template.twig
- src/index.php
- src/assets/template.twig
- db/migrations
- Dockerfile
- docker-compose.yml
- phinx.php


---
This AGENTS.md reflects the current repository structure and conventions discovered by scanning the codebase. Keep changes surgical and consistent with these patterns.
