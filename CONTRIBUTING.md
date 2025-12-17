# Contributing to EPA WebCMS

Thank you for contributing to the EPA WebCMS project! This guide will help you set up your development environment and understand our development workflows.

## Table of Contents

- [First-Time Setup](#first-time-setup)
- [Development Environment](#development-environment)
- [Daily Development Workflow](#daily-development-workflow)
- [Deployment Guide](#deployment-guide)
  - [Skip-Build Deployments](#skip-build-deployments)
  - [Full Build Deployments](#full-build-deployments)
- [Git Workflow](#git-workflow)
- [Testing](#testing)
- [Code Standards](#code-standards)
- [Helpful Commands](#helpful-commands)
- [Troubleshooting](#troubleshooting)

---

## First-Time Setup

**Prerequisites:**
- DDEV 1.24 or higher
- Docker Desktop
- Git
- Composer
- Node.js and npm

### Step-by-Step Setup

1. **Clone the repository:**

   ```bash
   git clone -b main git@github.com:USEPA/webcms.git
   cd webcms
   ```

2. **Start DDEV:**

   ```bash
   cd services/drupal
   ddev start
   ```

3. **Create S3 bucket for s3fs:**

   ```bash
   ddev aws-setup
   ```

4. **Obtain the latest database:**
   - Contact Michael Hessling for the latest database dump
   - Place the `.tar` file in `services/drupal/.ddev/db/`

5. **Import the database:**

   ```bash
   ddev import-db
   ```

   > **Note:** For very large dumps, this may timeout. DDEV may continue the import in the background—verify with `docker stats`. If DDEV kills the process, connect a MySQL client directly using the forwarded port from `ddev status`.

6. **Copy environment file:**

   ```bash
   cp .env.example .env
   ```

7. **Install PHP dependencies:**

   ```bash
   ddev composer install
   ```

   > If you encounter errors, delete `services/drupal/.ddev/vendor` and run: `ddev composer clearcache`

8. **Install theme requirements:**

   ```bash
   ddev gesso install
   ```

9. **Build theme assets:**

   ```bash
   # One-time build
   ddev gesso build

   # Or watch for changes during development
   ddev gesso watch
   ```

10. **Apply latest configuration:**

    **⚠️ Warning:** Skip this if starting from a fresh database import—it will wipe your database!

    ```bash
    # Only run if you previously ran step 5 (import-db)
    ddev drush si --existing-config
    ```

11. **Run deployment updates:**

    ```bash
    ddev drush deploy -y
    ```

12. **Enable runtime caching:**

    Edit `services/drupal/.env` and change:
    ```
    ENV_STATE=build
    ```
    to:
    ```
    ENV_STATE=run
    ```

13. **Unblock admin user:**

    ```bash
    ddev drush user:unblock drupalwebcms-admin
    ```

14. **Install SSL certificates (first time only):**

    ```bash
    ddev stop --all
    mkcert -install
    ```

    For Firefox users, install `nss`:
    ```bash
    brew install nss
    mkcert -install
    ```

15. **Access the site:**

    Open <https://epa.ddev.site> in your browser.

---

## Development Environment

### Project Structure

```
webcms/
├── .gitlab-ci.yml              # CI/CD pipeline configuration
├── .gitlab/                    # Pipeline includes
│   ├── docker.yml              # Docker build jobs
│   └── teams-notifications.yml # MS Teams alerts
├── services/                   # Application services
│   ├── drupal/                 # Main Drupal codebase
│   │   ├── config/             # Configuration management
│   │   ├── drush/              # Drush commands
│   │   ├── patches/            # Composer patches
│   │   ├── scripts/            # Custom scripts
│   │   ├── web/                # Drupal web root
│   │   │   ├── modules/custom/ # Custom modules
│   │   │   └── themes/custom/  # Custom themes
│   │   ├── composer.json       # PHP dependencies
│   │   └── Dockerfile          # Multi-stage Docker build
│   ├── drush/                  # Drush container
│   ├── minio/                  # Local S3 emulation
│   ├── mysql/                  # Database container
│   └── simplesaml/             # SAML authentication
├── terraform/                  # Infrastructure as code
│   ├── infrastructure/         # AWS infrastructure
│   └── webcms/                 # Application deployment
├── ci/                         # CI automation scripts
├── push-dev.sh                 # Deploy to development
└── trigger-pipeline.sh         # Manual pipeline trigger
```

### Key Technologies

- **Drupal 10** - Content management system
- **PHP 8.1+** - Backend language
- **Docker** - Containerization
- **DDEV** - Local development environment
- **Terraform** - Infrastructure provisioning
- **GitLab CI/CD** - Continuous integration and deployment
- **AWS ECS** - Container orchestration
- **AWS RDS** - Managed database
- **AWS S3** - File storage

---

## Daily Development Workflow

### 1. Start Your Day

```bash
# Start DDEV (if not already running)
cd services/drupal
ddev start

# Pull latest changes from main branch
git checkout main
git pull origin main

# Create a feature branch
git checkout -b feature/your-feature-name

# Start watching theme changes
ddev gesso watch
```

### 2. Make Your Changes

- **PHP/Module Development:** Edit files in `services/drupal/web/modules/custom/`
- **Theme Development:** Edit files in `services/drupal/web/themes/custom/epa_theme/`
- **Configuration Changes:** Export config with `ddev drush cex`

### 3. Test Locally

```bash
# Clear cache
ddev drush cr

# Run updates
ddev drush updb -y

# Import configuration
ddev drush cim -y

# View site
open https://epa.ddev.site
```

### 4. Commit Your Changes

```bash
# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat: Add new feature description"

# Push to GitHub
git push origin feature/your-feature-name
```

### 5. Deploy to Development Environment

See [Deployment Guide](#deployment-guide) below.

---

## Deployment Guide

The WebCMS uses a **GitHub → GitLab CI/CD → AWS** deployment pipeline. Code is hosted on GitHub but deployed via GitLab CI/CD.

### Deployment Workflow

```
Developer → GitHub (development branch) → GitLab Mirror → CI/CD Pipeline → AWS ECS
```

### Environments

| Branch | Environment | Purpose | Deployment Method |
|--------|-------------|---------|-------------------|
| `development` | Dev site | Active development | Automatic via `push-dev.sh` |
| `live` | Stage site | Pre-production testing | Manual trigger |
| `live` | Production | Live public site | Manual trigger (future) |

---

## Skip-Build Deployments

Skip-build mode allows you to deploy code changes **without rebuilding Docker images**, reducing deployment time from ~15 minutes to ~3-5 minutes.

### When to Use Skip-Build

✅ **Use skip-build when:**
- You only changed Drupal PHP code (`services/drupal/web/`)
- You only changed configuration files
- You only changed custom modules or themes
- You need to quickly deploy a hotfix
- You're iterating on the same feature multiple times per day

❌ **DO NOT use skip-build when:**
- You changed `composer.json` or `composer.lock` (PHP dependencies)
- You changed the `Dockerfile`
- You changed Nginx configuration files
- You changed system packages or libraries
- This is your **first deployment of the day**
- You haven't deployed in several days

### How It Works

#### Full Build Mode (Default)
```bash
./push-dev.sh
```

**Pipeline stages:**
1. ✅ **Build** Docker images (~8-12 minutes)
   - `webcms-preproduction-dev-drupal:development-abc1234`
   - `webcms-preproduction-dev-nginx:development-abc1234`
   - `webcms-preproduction-dev-drush:development-abc1234`
   - Also tagged as `:development-latest` for reuse
2. ✅ **Deploy** via Terraform (~2-3 minutes)
3. ✅ **Update** via Drush (~1-2 minutes)

**Total time: ~12-17 minutes**

#### Skip-Build Mode (Fast)
```bash
./push-dev.sh --skip-build
```

**Pipeline stages:**
1. ⏭️ **SKIPPED** - Build Docker images (saves 8-12 minutes!)
2. ✅ **Deploy** via Terraform (~2-3 minutes) - Reuses `:development-latest` images
3. ✅ **Update** via Drush (~1-2 minutes)

**Total time: ~3-5 minutes** (60-70% faster!)

### Usage Examples

#### Example 1: Typical Daily Workflow
```bash
# 9:00 AM - First deployment of the day (full build)
git checkout development
git pull origin main
git merge main
git push origin development
./push-dev.sh

# 10:30 AM - Bug fix in custom module
# (edit services/drupal/web/modules/custom/epa_workflow/src/Plugin/WorkflowType/MyPlugin.php)
git add .
git commit -m "fix: Correct workflow validation logic"
git push origin development
./push-dev.sh --skip-build  # Fast deployment!

# 2:00 PM - Another iteration
# (edit services/drupal/web/themes/custom/epa_theme/templates/node.html.twig)
git add .
git commit -m "style: Update node template layout"
git push origin development
./push-dev.sh --skip-build  # Fast deployment!

# 4:00 PM - Added a new Composer dependency
# (edit composer.json, run ddev composer update)
git add composer.json composer.lock
git commit -m "chore: Add new library dependency"
git push origin development
./push-dev.sh  # Full build required!
```

#### Example 2: Hotfix Deployment
```bash
# Critical bug found in production, need fast fix
git checkout development
# (fix the bug)
git add .
git commit -m "fix: Critical security patch for XSS vulnerability"
git push origin development
./push-dev.sh --skip-build  # Deploy ASAP without waiting for build
```

#### Example 3: Force Push with Skip-Build
```bash
# Need to force push and deploy quickly
./push-dev.sh --skip-build -f
```

### Manual Pipeline Trigger with Skip-Build

You can also trigger skip-build mode directly from GitLab UI:

1. Navigate to: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines/new
2. Select branch: `development`
3. Click "Add variable"
   - Key: `SKIP_BUILD`
   - Value: `true`
4. Click "Run pipeline"

### Technical Implementation

#### Image Tagging Strategy

**Full Build creates two tags:**
```
webcms-preproduction-dev-drupal:development-abc1234  # Commit-specific
webcms-preproduction-dev-drupal:development-latest   # Reusable tag
```

**Skip-Build reuses existing:**
```
webcms-preproduction-dev-drupal:development-latest  # No new build
```

#### Pipeline Optimizations

1. **Enhanced Kaniko Caching**
   - `--cache-ttl=168h` - Caches Docker layers for 7 days
   - `--cache-copy-layers=true` - Aggressive layer reuse
   - Reduces build time by 30-50% even in full build mode

2. **Conditional Build Stage**
   - `build:drupal:dev` job skips entirely when `SKIP_BUILD=true`
   - Saves ~8-12 minutes per deployment

3. **Dynamic Image Tag Override**
   - Deploy stage automatically uses `:development-latest` when `SKIP_BUILD=true`
   - Uses commit-specific tag in normal mode

---

## Full Build Deployments

### Standard Deployment to Development

```bash
# Ensure you're on development branch
git checkout development

# Merge latest changes from main
git pull origin main

# Push to GitHub and trigger full CI/CD pipeline
./push-dev.sh
```

### What Happens During Full Build

1. **GitHub Push**
   - Code pushed to `development` branch on GitHub
   - `push-dev.sh` script triggers GitLab pipeline via API

2. **GitLab Mirror Sync**
   - GitLab pulls latest code from GitHub mirror
   - Takes ~20 seconds to sync

3. **Build Stage** (~8-12 minutes)
   - Kaniko builds 3 Docker images in parallel:
     - `drupal` - PHP-FPM with Drupal application
     - `nginx` - Web server with Drupal configuration
     - `drush` - CLI tools for database operations
   - Images pushed to AWS ECR
   - Images also pushed to GitLab Container Registry

4. **Deploy Stage** (~2-3 minutes)
   - Terraform initializes and validates configuration
   - Terraform plans ECS service changes
   - Terraform applies changes:
     - Updates ECS task definitions with new image tags
     - Triggers ECS service update
     - ECS performs rolling deployment (zero downtime)

5. **Update Stage** (~1-2 minutes)
   - Drush runs database updates (`drush updb`)
   - Drush imports configuration (`drush cim`)
   - Drush clears caches (`drush cr`)

6. **Monitoring**
   - View pipeline: https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/pipelines
   - View logs in GitLab UI
   - Check ECS service: AWS Console → ECS → webcms-preproduction-dev cluster

### Deployment to Stage (Pre-Production)

Stage deployments are only triggered from the `live` branch:

```bash
# Merge development into live
git checkout live
git pull origin live
git merge development
git push origin live

# GitLab CI automatically triggers stage deployment
# (No script needed - push triggers workflow)
```

---

## Git Workflow

### Branch Strategy

- **`main`** - Stable release branch (matches production)
- **`live`** - Pre-production branch (deploys to stage)
- **`development`** - Active development branch (deploys to dev)
- **`feature/*`** - Feature branches (local development only)
- **`bugfix/*`** - Bug fix branches (local development only)
- **`hotfix/*`** - Urgent fixes (fast-track to production)

### Commit Message Convention

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, etc.)
- `refactor:` - Code refactoring
- `perf:` - Performance improvements
- `test:` - Adding or updating tests
- `chore:` - Maintenance tasks

**Examples:**
```bash
git commit -m "feat(workflow): Add new approval step for content editors"
git commit -m "fix(theme): Correct responsive menu breakpoint"
git commit -m "docs: Update deployment guide with skip-build instructions"
git commit -m "chore: Update Drupal core to 10.2.3"
```

### Pull Request Process

1. Create feature branch from `development`
2. Make your changes and commit
3. Push branch to GitHub
4. Create Pull Request targeting `development`
5. Request review from team members
6. Address review feedback
7. Merge when approved (squash and merge preferred)

---

## Testing

### Local Testing

```bash
# Clear cache
ddev drush cr

# Run database updates
ddev drush updb -y

# Import configuration
ddev drush cim -y

# Check status
ddev drush status

# Run cron
ddev drush cron

# Rebuild cache
ddev drush rebuild
```

### Code Quality Checks

```bash
# PHP CodeSniffer
ddev composer phpcs

# PHP CodeSniffer auto-fix
ddev composer phpcbf

# PHPStan static analysis
ddev composer phpstan

# Run all checks
ddev composer check
```

### Testing in Dev Environment

After deploying to dev environment:

1. **Verify Deployment:**
   - Check GitLab pipeline completed successfully
   - Verify ECS service updated in AWS Console

2. **Smoke Test:**
   - Access dev site URL
   - Login as admin
   - Create/edit/delete content
   - Test key workflows

3. **Configuration Verification:**
   ```bash
   # SSH into ECS task (via AWS Console or ECS Exec)
   drush status
   drush config:status
   ```

---

## Code Standards

### PHP Standards

- Follow [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards)
- Use PHP 8.1+ features where appropriate
- Type hint all parameters and return values
- Document all public methods with PHPDoc

### CSS/SCSS Standards

- Follow BEM naming convention
- Use design tokens from theme configuration
- Mobile-first responsive design
- Accessibility: WCAG 2.1 AA compliance

### JavaScript Standards

- ES6+ syntax
- Use `const` and `let` (no `var`)
- Drupal behaviors for initialization
- Document complex functions

### Configuration Management

- **Always export configuration after changes:**
  ```bash
  ddev drush cex
  ```

- **Never commit configuration in code and database simultaneously**
- **Test configuration imports in clean environment**

### Security Best Practices

- Never commit secrets or credentials
- Use environment variables for sensitive data
- Sanitize all user input
- Follow [Drupal Security Best Practices](https://www.drupal.org/docs/security-in-drupal)

---

## Helpful Commands

### DDEV Commands

| Command | Description |
|---------|-------------|
| `ddev start` | Start the development environment |
| `ddev stop` | Stop the development environment |
| `ddev restart` | Restart all containers |
| `ddev ssh` | SSH into the web container |
| `ddev describe` | Show project details and URLs |
| `ddev logs` | View container logs |
| `ddev import-db` | Import a database dump |
| `ddev export-db` | Export database with timestamp |
| `ddev phpmyadmin` | Open PhpMyAdmin in browser |
| `ddev aws-setup` | Configure local S3 emulation |

### Drush Commands

| Command | Description |
|---------|-------------|
| `ddev drush cr` | Clear all caches |
| `ddev drush updb -y` | Run database updates |
| `ddev drush cim -y` | Import configuration |
| `ddev drush cex` | Export configuration |
| `ddev drush deploy -y` | Run deployment workflow (updb + cim + cr) |
| `ddev drush status` | Show Drupal status |
| `ddev drush uli` | Generate one-time login link |
| `ddev drush user:unblock <username>` | Unblock a user account |
| `ddev drush sqlq "SELECT * FROM users"` | Run SQL query |

### Gesso Theme Commands

| Command | Description |
|---------|-------------|
| `ddev gesso install` | Install node modules for theme |
| `ddev gesso build` | Build CSS and Pattern Lab |
| `ddev gesso watch` | Watch for changes and rebuild |
| `ddev gesso lint` | Lint CSS and JavaScript |

### Composer Commands

| Command | Description |
|---------|-------------|
| `ddev composer install` | Install PHP dependencies |
| `ddev composer update` | Update PHP dependencies |
| `ddev composer require <package>` | Add new dependency |
| `ddev composer remove <package>` | Remove dependency |
| `ddev composer clearcache` | Clear Composer cache |

### Deployment Commands

| Command | Description |
|---------|-------------|
| `./push-dev.sh` | Full build and deploy to dev |
| `./push-dev.sh --skip-build` | Fast deploy to dev (reuse images) |
| `./push-dev.sh -f` | Force push with full build |
| `./push-dev.sh --skip-build -f` | Force push with skip-build |
| `./trigger-pipeline.sh development` | Manually trigger GitLab pipeline |

---

## Troubleshooting

### Common Issues

#### Elasticsearch Errors

If you encounter Elasticsearch errors:

```bash
ddev poweroff
docker volume rm ddev-epa-ddev_elasticsearch
ddev start
```

Then re-index content:
```bash
ddev drush search-api:reindex
ddev drush search-api:index
```

#### Composer Install Errors

If `ddev composer install` fails:

```bash
# Delete vendor directory and clear cache
rm -rf services/drupal/.ddev/vendor
ddev composer clearcache
ddev composer install
```

#### Database Import Timeout

For large database imports that timeout:

1. Check if import is running in background: `docker stats`
2. If process was killed, connect MySQL client directly:
   ```bash
   # Get MySQL port
   ddev status
   
   # Connect directly (use port from status)
   mysql -h 127.0.0.1 -P <port> -u db -pdb db < backup.sql
   ```

#### SSL Certificate Warnings

Install mkcert certificates:

```bash
ddev stop --all
mkcert -install
ddev start
```

For Firefox users:
```bash
brew install nss
mkcert -install
ddev start
```

#### Deployment Failed: "Image not found"

**Problem:** Skip-build deployment fails with "image not found" error.

**Cause:** No `:development-latest` image exists yet.

**Solution:** Run a full build first:
```bash
./push-dev.sh  # Without --skip-build
```

#### Changes Not Visible After Deployment

**Problem:** Deployed successfully but changes aren't visible on dev site.

**Possible causes:**
1. Browser cache - Hard refresh (Ctrl+Shift+R)
2. Drupal cache - Run Drush update job manually in GitLab
3. Wrong image deployed - Check ECS task definition in AWS Console

**Solution:**
```bash
# SSH into ECS container and verify
drush cr
drush status
```

#### GitLab Pipeline Fails to Trigger

**Problem:** `push-dev.sh` completes but pipeline doesn't start.

**Causes:**
1. GitLab token expired or invalid
2. GitHub → GitLab mirror not syncing
3. GitLab project path changed

**Solution:**
```bash
# 1. Verify token exists
echo $GITLAB_TOKEN

# 2. Create new token if needed
# https://gitlab.epa.gov/-/user_settings/personal_access_tokens
# Required scope: "api"

# 3. Set token
export GITLAB_TOKEN="your-token-here"

# 4. Manually trigger mirror sync in GitLab UI
# https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/settings/repository
# Click "Update now" next to GitHub mirror
```

#### Memory Limit Errors

If you encounter PHP memory limit errors:

```bash
# Increase PHP memory limit in .ddev/config.yaml
# Add or modify:
php_version: "8.1"
webserver_type: nginx-fpm
php_memory_limit: "512M"

# Restart DDEV
ddev restart
```

### Getting Help

- **Slack:** #webcms-dev channel
- **Email:** webcms-team@epa.gov
- **GitLab Issues:** https://gitlab.epa.gov/drupalcloud/drupalclouddeployment/-/issues
- **Documentation:** See `docs/` directory

---

## Additional Resources

- [CI/CD Pipeline Documentation](docs/cicd-pipeline.md)
- [Terraform Infrastructure](terraform/infrastructure/README.md)
- [Terraform WebCMS Deployment](terraform/webcms/README.md)
- [Docker Build Configuration](.gitlab/docker.yml)
- [Drupal Documentation](https://www.drupal.org/docs)
- [DDEV Documentation](https://ddev.readthedocs.io/)

---

## License

See [LICENSE](LICENSE) file for details.

## Disclaimer

The United States Environmental Protection Agency (EPA) GitHub project code is provided on an "as is" basis and the user assumes responsibility for its use. EPA has relinquished control of the information and no longer has responsibility to protect the integrity, confidentiality, or availability of the information. Any reference to specific commercial products, processes, or services by service mark, trademark, manufacturer, or otherwise, does not constitute or imply their endorsement, recommendation or favoring by EPA. The EPA seal and logo shall not be used in any manner to imply endorsement of any commercial product or activity by EPA or the United States Government.
