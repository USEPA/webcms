# Drupal 7 to Drupal 8 Migration Scripts (ARCHIVED)

## ⚠️ Status: ARCHIVED - DO NOT USE

These scripts are **historical artifacts** from the Drupal 7 to Drupal 8 migration completed in **December 2020**. They are retained for reference purposes only and **should not be executed** against the current Drupal 10 installation.

---

## 📜 Historical Context

### Migration Timeline
- **Migration Period:** 2020
- **Source:** Drupal 7 (legacy CMS)
- **Target:** Drupal 8 (migrated to D10 since then)
- **Environment:** Stage environment (`webcms-cluster-stage`)
- **Last Modified:** December 3, 2020

### Current System
- **Active Version:** Drupal 10
- **Status:** Migration completed and system upgraded
- **Database:** D7 database preserved for historical reference only

---

## 📂 Archived Scripts

### 1. `allow-unprocessed.sh`
**Purpose:** Configure acceptable thresholds for unprocessed migration items.

**Function:**
- Sets Drupal state variables for migration tolerance
- Allows migration to proceed with known unprocessed items
- Targets specific content types that had migration issues

**Usage (Historical):**
```bash
# Required: drushvpc-stage.json in current directory
# Required: AWS_PROFILE configured for stage environment
bash scripts/archive/d7-migration/allow-unprocessed.sh
```

**State Variables Set:**
- `epa.allowed_unprocessed.upgrade_d7_node_revision_document`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_event`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_faq`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_news_release`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_page`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_public_notice`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_regulation`
- `epa.allowed_unprocessed.upgrade_d7_node_revision_webform`
- Plus additional panelizer and paragraph variants

### 2. `start-stage-migration.sh`
**Purpose:** Initiate the D7→D8 migration process on the stage environment.

**Function:**
- Launches ECS task with `drush-migrate` command
- Allocates 8GB memory for migration process
- Runs on Fargate in the stage cluster

**Usage (Historical):**
```bash
# Required: drushvpc-stage.json in current directory
# Required: AWS_PROFILE configured for stage environment
bash scripts/archive/d7-migration/start-stage-migration.sh
```

**Notes:**
- Migration script location: `/services/drupal/scripts/ecs/drush-migrate.sh`
- Can be stopped using `halt-stage-migration.sh`

### 3. `halt-stage-migration.sh`
**Purpose:** Gracefully stop a running migration process.

**Function:**
- Sets `epa.migrations_halted` state variable to `true`
- Stops all active migrations (`drush mst --all`)
- Prevents migration from continuing to next content type

**Usage (Historical):**
```bash
# Required: drushvpc-stage.json in current directory
# Required: AWS_PROFILE configured for stage environment
bash scripts/archive/d7-migration/halt-stage-migration.sh
```

---

## 🔧 Technical Details

### Common Requirements (All Scripts)
- **VPC Configuration:** `drushvpc-stage.json` file in current directory
- **AWS Profile:** Environment variable `AWS_PROFILE` set appropriately
- **AWS Permissions:** Access to ECS, SSM, and CloudWatch
- **Target Cluster:** `webcms-cluster-stage`
- **Task Definition:** `webcms-drush-stage`

### Infrastructure References
- **ECS Cluster:** `webcms-cluster-stage`
- **AWS Region:** `us-east-1`
- **Network:** Fargate with private subnets
- **Console Link:** https://console.aws.amazon.com/ecs/home?region=us-east-1#/clusters/webcms-cluster-stage/tasks

### Script Metadata
- **Author Attribution:** Scripts reference `started_by="bschumacher"`
- **Shell:** Bash with `set -euo pipefail` (strict error handling)
- **JSON Processing:** Uses `jq` for AWS CLI parameter formatting

---

## 🚫 Why These Scripts Should Not Be Used

1. **Migration Complete:** The D7→D8 migration finished in 2020
2. **Wrong Target:** Scripts target D8, but the system is now D10
3. **Obsolete State:** Migration state variables are no longer relevant
4. **Database Schema:** Current database reflects D10 schema, not D7 or D8
5. **Breaking Changes:** Running these could corrupt the production D10 database

---

## 📚 References for Future Migrations

If you need to perform future migrations (e.g., D10→D11), consider this migration approach as a reference:

### Migration Pattern
1. **Pre-Migration:** Set acceptable thresholds for known issues
2. **Execution:** Run migration in isolated environment (stage)
3. **Monitoring:** Track progress via ECS console and CloudWatch logs
4. **Control:** Ability to halt migration gracefully if issues arise

### Related Files (Active)
- `/services/drupal/scripts/ecs/drush-migrate.sh` - Migration orchestration script
- `/services/drupal/scripts/local/f1-drush-migrate.sh` - Local migration variant

### Documentation
- Terraform: `terraform/webcms/README.md` - Database credentials structure
- Infrastructure: `terraform/infrastructure/README.md` - D7/D8 database setup

---

## 📞 Questions?

If you need information about the historical migration:
1. Review git history: `git log --follow -- scripts/archive/d7-migration/`
2. Check related migration scripts in `/services/drupal/scripts/`
3. Review migration documentation in project README files
4. Contact the infrastructure team for AWS Parameter Store values

---

## ⚖️ Retention Policy

These scripts are retained for:
- **Historical Reference:** Understanding past migration processes
- **Compliance:** Government record-keeping requirements
- **Knowledge Transfer:** Documenting institutional knowledge
- **Future Planning:** Reference for potential future migrations

**Review Date:** December 2025 (consider archiving to long-term storage after 5 years)

---

*Last Updated: October 30, 2025*
*Migration Completed: December 2020*
*Current System: Drupal 10*
