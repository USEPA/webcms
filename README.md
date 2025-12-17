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
ddev aws-setup
ddev import-db
ddev composer install
ddev gesso install
ddev gesso build
ddev drush deploy -y
```

Access the site at: <https://epa.ddev.site>

## Documentation

- **[Contributing Guide](CONTRIBUTING.md)** - Complete setup instructions, development workflows, and deployment guide
- **[CI/CD Pipeline](.gitlab-ci.yml)** - GitLab CI configuration for automated deployments
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

- **Platform:** Drupal 10 on PHP 8.1+
- **Infrastructure:** AWS ECS (Fargate), RDS (PostgreSQL), S3, CloudFront
- **CI/CD:** GitLab CI/CD with GitHub mirror
- **Containers:** Multi-stage Docker builds with Kaniko
- **IaC:** Terraform for infrastructure and application deployment

## Quick Commands

| Command | Description |
|---------|-------------|
| `ddev start` | Start development environment |
| `ddev drush cr` | Clear Drupal cache |
| `ddev drush deploy -y` | Run deployment updates |
| `ddev gesso watch` | Watch and rebuild theme assets |
| `./push-dev.sh` | Deploy to dev (full build) |
| `./push-dev.sh --skip-build` | Deploy to dev (fast, no rebuild) |

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
