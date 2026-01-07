# EPA WebCMS

The United States Environmental Protection Agency's Web Content Management System, built on Drupal 10.

## Quick Start

```bash
# Clone repository
git clone -b main git@github.com:USEPA/webcms.git
cd webcms/services/drupal

# Start development environment
ddev start

# Complete setup
ddev aws-setup                    # Create local S3 bucket
ddev import-db                    # Import database (place .tar in .ddev/db/)
cp .env.example .env              # Copy environment configuration
ddev composer install
ddev gesso install                # Install theme dependencies
ddev gesso build                  # Build theme assets
ddev drush deploy -y              # Run deployment workflow
ddev drush user:unblock drupalwebcms-admin
```

Access the site at: <https://epa.ddev.site>

## Documentation

- **[Contributing Guide](CONTRIBUTING.md)** - Complete setup instructions, development workflows, and deployment guide
- **[WARP.md](WARP.md)** - AI agent guidance for working with this repository
- **[Git Workflow](docs/GIT_WORKFLOW.md)** - Branching and release process
- **[CI/CD Pipeline](.gitlab-ci.yml)** - GitLab CI configuration for automated deployments
- **[Deployment Workflow](.gitlab/DEPLOYMENT_WORKFLOW.md)** - Step-by-step deployment process
- **[Pipeline Optimizations](.gitlab/PIPELINE_OPTIMIZATIONS.md)** - Performance optimization details
- **[Terraform Infrastructure](terraform/infrastructure/README.md)** - AWS infrastructure provisioning
- **[Terraform WebCMS](terraform/webcms/README.md)** - Application deployment configuration

## Key Features

- **Fast Deployments** - Skip-build mode reduces deployment time from 15 minutes to 3-5 minutes
- **Zero-Downtime Deployments** - Rolling ECS deployments with health checks
- **Infrastructure as Code** - Complete AWS infrastructure managed via Terraform
- **Automated Testing** - Security scanning with Prisma Cloud and GitLab SAST
- **Multi-Environment** - Separate dev, stage, and production environments

## Development Workflow

### Daily Development
```bash
# Start local environment
cd services/drupal
ddev start
ddev gesso watch

# Make changes, then deploy to dev environment
git add .
git commit -m "feat: Your feature description"
git push origin development

# Full build (first deployment of day)
./push-dev.sh

# Fast deployment (code changes only)
./push-dev.sh --skip-build
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for complete development guide.

## Architecture

- **Platform:** Drupal 10 on PHP 8.2
- **Theme:** Gesso USWDS (Pattern Lab + USWDS 3.9.0)
- **Infrastructure:** AWS ECS (Fargate), RDS (PostgreSQL), S3, CloudFront
- **CI/CD:** GitLab CI/CD with GitHub mirror
- **Containers:** Multi-stage Docker builds with Kaniko (drupal, nginx, drush)
- **IaC:** Terraform for infrastructure and application deployment
- **Multi-site:** English and Spanish sites share the same codebase and infrastructure; each site/language pair is its own ECS service.
- **Authentication:** SimpleSAMLphp service backs production SSO; local development uses the mock IdP in `services/simplesaml/` so contributors can test SAML flows without external access.

### Deployment Pipeline

```
Developer → GitHub (branch) → GitLab Mirror → CI/CD → AWS ECS
```

**Branch to Environment Mapping:**
- `development` → Dev site (automatic deployment)
- `live` → Stage site (manual trigger, includes security scans)
- `live` → Production (manual trigger, future)

**Pipeline Stages:**
- Development branch: Build → Deploy → Update (~10-15 min)
- Live branch: Build → Test → Scan → Deploy → Update (~25-35 min)

## Quick Commands

### Local Development

| Command | Description |
|---------|-------------|
| `ddev start` | Start development environment |
| `ddev drush cr` | Clear Drupal cache |
| `ddev drush deploy -y` | Run deployment workflow (updb + cim + cr) |
| `ddev drush cex` | Export configuration |
| `ddev drush cim -y` | Import configuration |
| `ddev drush uli` | Generate one-time login URL |
| `ddev gesso watch` | Watch and rebuild theme assets |
| `ddev gesso build` | One-time theme build |
| `ddev composer phpcs` | Run PHP Code Sniffer |
| `ddev composer phpcbf` | Auto-fix coding standards |
| `ddev composer phpstan` | Run PHPStan static analysis |
> Theme artifacts are intentionally not committed—after pulling any theme changes, rerun `ddev gesso build` (or `ddev gesso watch`) so CSS/JS output stays fresh.

### Deployment

| Command | Description |
|---------|-------------|
| `./push-dev.sh` | Deploy to dev (full build) |
| `./push-dev.sh --skip-build` | Deploy to dev (fast, no rebuild) |
| `./push-dev.sh --skip-build -f` | Fast deployment with force push |
| `./trigger-pipeline.sh development` | Manually trigger GitLab pipeline |

**When to use `--skip-build`:**
- ✅ Changed PHP code in `services/drupal/web/`
- ✅ Changed custom modules or themes
- ✅ Changed configuration files
- ✅ Need to quickly deploy a hotfix
- ❌ Changed `composer.json` or `composer.lock`
- ❌ Changed Dockerfile or Nginx configs
- ❌ First deployment of the day
- ❌ Added/updated system packages

See [CONTRIBUTING.md](CONTRIBUTING.md#helpful-commands) for complete command reference.

## CI/CD

- **Active System:** GitLab CI (`.gitlab-ci.yml` and `.gitlab/docker.yml`)
- **Deprecated:** Buildkite pipelines (`.buildkite/`) - retained for historical reference only
- **Deployment Pipeline:** GitHub → GitLab Mirror → Docker Build → AWS ECS

## Requirements

- DDEV 1.24 or higher
- Docker Desktop
- Git
- Composer
- Node.js and npm

## Disclaimer

The United States Environmental Protection Agency (EPA) GitHub project code is provided on an "as is" basis and the user assumes responsibility for its use.  EPA has relinquished control of the information and no longer has responsibility to protect the integrity , confidentiality, or availability of the information.  Any reference to specific commercial products, processes, or services by service mark, trademark, manufacturer, or otherwise, does not constitute or imply their endorsement, recommendation or favoring by EPA.  The EPA seal and logo shall not be used in any manner to imply endorsement of any commercial product or activity by EPA or the United States Government.
