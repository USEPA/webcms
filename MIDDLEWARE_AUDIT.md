# WebCMS Middleware Audit

_Generated from a full review of the middleware layer: Dockerfile, nginx configuration,
CI automation scripts (`ci/`), the CloudWatch logger, and the GitLab CI pipeline._

---

## Summary of Findings

| # | Severity | Area | Issue |
|---|----------|------|-------|
| 1 | 🔴 Critical | Dockerfile | PHP version mismatch between drupal (8.3) and drush (8.2) |
| 2 | 🔴 Critical | Dockerfile / nginx | nginx HEALTHCHECK uses wrong scheme and port |
| 3 | 🟠 High | CI Scripts | `cleanup-webforms.js` has no environment guard — can delete production data |
| 4 | 🟠 High | CloudWatch Logger | `sequenceToken` usage deprecated since 2023; unnecessary retries |
| 5 | 🟡 Medium | CloudWatch Logger | `setLogStreamFromContainerMetadata()` missing null guard after `json_decode` |
| 6 | 🟡 Medium | nginx config | Five RSS endpoint locations repeat 10 identical FastCGI param lines each |
| 7 | 🟡 Medium | nginx config | Inconsistent DNS resolvers across `proxy_pass` blocks |
| 8 | 🟡 Medium | GitLab CI | Security scan job rules are commented out — unclear intent, likely running too broadly |

---

## Critical Issues

### 1. PHP Version Mismatch: drupal (8.3) vs. drush (8.2)

**File:** `services/drupal/Dockerfile`

The `drupal` build stage uses `php:8.3.29-fpm-alpine3.22`, but the `drush` build stage
uses `php:8.2-cli-alpine`:

```dockerfile
# drupal stage (line ~1)
FROM public.ecr.aws/docker/library/php:8.3.29-fpm-alpine3.22 AS base

# drush stage (far below)
FROM public.ecr.aws/docker/library/php:8.2-cli-alpine AS drush
```

The drush container runs the same Drupal codebase and Composer dependencies as the
PHP-FPM container. A version mismatch means:

- PHP 8.3 features (readonly classes, `json_validate()`, typed class constants, etc.) will
  throw fatal errors when drush runs the same PHP files.
- Behavior differences in language semantics can cause deployment failures that only
  manifest during the Drush `deploy` step, not during web requests.
- The `--ignore-platform-reqs` flag in Composer install masks dependency version
  incompatibilities that would otherwise be caught.

**Fix:** Pin the drush stage to the same PHP version as the FPM stage (`8.3`).

---

### 2. nginx HEALTHCHECK — Wrong Scheme and Port

**File:** `services/drupal/Dockerfile` (nginx stage)

```dockerfile
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -f https://localhost/ping || exit 1
```

The `/ping` endpoint is defined in `status.conf` on **port 8080 over plain HTTP**:

```nginx
server {
  listen 8080 default_server;

  location = /ping {
    return 200 "pong";
  }
}
```

The main server block in `default.conf` listens on **port 443 with TLS** and does not
expose a `/ping` path. The HEALTHCHECK therefore:

- Attempts an HTTPS connection on port 443 — a TLS handshake nginx isn't expecting at
  that path.
- Never reaches the actual `/ping` handler.

ECS will mark the container as unhealthy, triggering continuous task restarts.

**Fix:** Change to `http://localhost:8080/ping`.

---

## High Severity

### 3. `cleanup-webforms.js` Has No Environment Guard

**File:** `ci/cleanup-webforms.js`

This script deletes **all webform submissions and webforms** using raw SQL:

```js
drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM webform_submission"
drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM webform_submission_data"
drush --uri="$WEBCMS_SITE_URL" sql:query "DELETE FROM config WHERE name LIKE 'webform.webform.%'"
```

Problems:

1. **No environment check.** The script reads `WEBCMS_SITE` and `WEBCMS_ENVIRONMENT` from
   the environment for ECS targeting (via `vars.js`), but never asserts that the current
   target is `dev`. Nothing prevents accidentally running this against stage or production.
2. **No confirmation prompt.** The script goes straight to execution with no "are you sure?"
   step.
3. **No dry-run mode.** There is no way to preview what will be deleted without deleting it.
4. **No count reported before deletion.** Operators have no idea how many records they're
   about to drop.

**Fix:** Add an explicit check that `WEBCMS_SITE === 'dev'`, add a `--dry-run` flag that
previews the operation, and print row counts before deleting.

---

### 4. CloudWatch Logger Uses Deprecated `sequenceToken`

**File:** `services/drupal/web/modules/custom/epa_cloudwatch/src/Logger/CloudWatch.php`

AWS deprecated the `sequenceToken` parameter for `PutLogEvents` in 2023. Any log group
using the new CloudWatch Logs resource policy model no longer requires or accepts sequence
tokens. The current code:

```php
protected $sequenceToken;

// ...

if (isset($this->sequenceToken)) {
  $args['sequenceToken'] = $this->sequenceToken;
}

// ... and catches InvalidSequenceTokenException to retry
```

Continuing to submit a sequence token:

- Can cause spurious `InvalidSequenceTokenException` errors on log groups that have
  transitioned to the new model.
- Creates unnecessary retry loops (up to 3x per batch) on any concurrent writer.
- The `$sequenceToken` stored in the instance is not shared between concurrent PHP-FPM
  workers, so it starts wrong on every request anyway.

**Fix:** Remove the `$sequenceToken` property and all code that reads/writes it. Replace
the `InvalidSequenceTokenException` handler with a `DataAlreadyAcceptedException` handler
(which means the batch was already received and can be ignored).

---

## Medium Severity

### 5. `setLogStreamFromContainerMetadata()` — Missing Null Guard

**File:** `services/drupal/web/modules/custom/epa_cloudwatch/src/Logger/CloudWatch.php`

```php
$metadata = json_decode($json, FALSE, 512, JSON_THROW_ON_ERROR);
$this->logStream = $metadata->DockerId;
```

`JSON_THROW_ON_ERROR` will throw `JsonException` on malformed JSON, which would propagate
as an uncaught exception and crash the request. But `json_decode` can still return
`null` for a valid JSON null payload, in which case `$metadata->DockerId` would throw a
fatal error.

**Fix:** Check `if ($metadata === null || !isset($metadata->DockerId)) { return FALSE; }`
before accessing the property, and wrap in a try/catch for `JsonException`.

---

### 6. Repeated FastCGI Parameter Blocks in nginx

**File:** `services/drupal/default.conf`

Five RSS-specific endpoint locations (`/newsreleases/search/rss`, `/faqs/search/rss`,
`/publicnotices/notices-search/rss`, `/perspectives/search/rss`, `/speeches/search/rss`)
each repeat 10 identical FastCGI parameter lines:

```nginx
include fastcgi_params;
fastcgi_param HTTP_HOST $WEBCMS_DOMAIN;
fastcgi_param HTTPS on;
fastcgi_param HTTP_X_FORWARDED_PROTO https;
fastcgi_param REMOTE_ADDR $realip_remote_addr;
fastcgi_hide_header X-Powered-By;
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;
fastcgi_intercept_errors on;
fastcgi_pass localhost:9000;
```

That's ~50 lines of copy-paste. A single change to buffer sizes or the FPM upstream
address requires editing 5 locations (plus the main PHP block — 6 total).

**Fix:** Extract the shared parameters into a dedicated nginx snippet file
(`fastcgi-drupal.conf`) and `include` it from each location block. The only lines that
differ are the `fastcgi_param PATH_INFO` and `fastcgi_param SCRIPT_FILENAME` lines.

---

### 7. Inconsistent DNS Resolvers Across `proxy_pass` Blocks

**File:** `services/drupal/default.conf`

The S3/CDN proxy locations use two different resolvers:

```nginx
# s3fs-css / s3fs-js block:
resolver 169.254.169.253 valid=30s;   # AWS VPC DNS

# sites/default/files/widgets and sites/default/files blocks:
resolver 127.0.0.11 valid=30s;        # Docker embedded DNS
```

`127.0.0.11` is the Docker daemon's embedded DNS resolver — it works in local
`docker-compose` environments but is not guaranteed to be present in ECS Fargate tasks,
where `169.254.169.253` is the correct VPC DNS server per AWS documentation.

**Fix:** Standardize all `proxy_pass` blocks to use `169.254.169.253` for DNS resolution.
The local ddev environment does provide Docker DNS, but both resolvers work in Docker; only
the VPC DNS works reliably in ECS.

---

### 8. GitLab CI Security Scan Rules Are Commented Out

**File:** `.gitlab-ci.yml`

The `sast`, `dependency_scanning`, and `secret_detection` jobs have their branch filter
rules commented out:

```yaml
sast:
  stage: Test
#  rules:
#    - if: '$CI_COMMIT_BRANCH == "live"'  # Only run on live branch
#    - when: never
```

The comment says "Only run on live branch (stage site)" but with the rules absent,
GitLab's default behavior applies the inherited template rules, which runs these jobs on
**every branch**. This means:

- Every `development` branch push runs SAST/dependency/secret scans — adding significant
  pipeline time to fast-iteration builds.
- The intent (scan only on `live`) is documented but not enforced.

**Fix:** Either restore the rules to match the documented intent, or add a comment
explicitly acknowledging the decision to scan all branches. Mixed partial commenting
is confusing to future maintainers.

---

## Other Observations (Low Priority)

- **`drush.js` main() error handling:** The `.catch()` followed by `.then()` pattern means
  `process.exit()` always runs regardless of whether `.catch()` was triggered. This is
  harmless but unusual; using `process.exitCode = 1` + a single `.finally()` would be
  cleaner.
- **`ssm.js` cache is module-level, unbounded:** The `cache` object is a plain object with
  no TTL. Fine for the short-lived CI scripts, but worth noting if this module is ever
  reused in longer-running contexts.
- **`epa_snapshot` hardcodes the snapshot hostname and alert markup:** Both
  `$snapshotHost` and the HTML in `createAlertMarkup()` reference
  `19january2025snapshot.epa.gov` directly. If the snapshot domain changes or a new
  snapshot is needed, multiple places must be updated. These should be class-level
  configuration or Drupal config.
- **`nginx-entrypoint.sh` htpasswd format:** The script writes `$WEBCMS_BASIC_AUTH`
  directly as the htpasswd file. nginx `auth_basic` requires
  `username:hashed_password` format. If the env var is not pre-hashed, authentication
  will not work. The README or env example should explicitly document the required format.

---

## Files Changed by This Audit

| File | Task |
|------|------|
| `services/drupal/Dockerfile` | PHP version fix, nginx HEALTHCHECK fix |
| `services/drupal/default.conf` | FastCGI DRY refactor, DNS resolver standardization |
| `services/drupal/web/modules/custom/epa_cloudwatch/src/Logger/CloudWatch.php` | sequenceToken removal, null guard |
| `ci/cleanup-webforms.js` | Environment guard, dry-run mode |
| `.gitlab-ci.yml` | Security scan job rules |
