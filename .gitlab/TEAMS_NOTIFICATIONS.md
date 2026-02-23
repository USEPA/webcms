# GitLab CI/CD Microsoft Teams Notifications

Automated Microsoft Teams notifications for the WebCMS GitLab pipeline.

## Overview

The pipeline sends Microsoft Teams notifications for major events:

- **Pipeline Started** 🚀 - When a new pipeline begins
- **Build Success/Failure** ✅❌ - After Docker image builds
- **Manual Approval Required** ✋ - Infrastructure changes need approval
- **Deployment Started/Success/Failure** 🚢🎉🔥 - Deployment lifecycle
- **Security Scan Issues** ⚠️ - When vulnerabilities detected
- **Pipeline Completed** 🏁 - Final summary

## Setup

### 1. Create Microsoft Teams Incoming Webhook

1. Go to your Microsoft Teams channel → **⋯ (More options)** → **Connectors**
2. Search for and configure **Incoming Webhook**
3. Provide a name (e.g., "GitLab CI/CD") and optionally upload an image
4. Copy webhook URL: `https://outlook.office.com/webhook/...`

### 2. Add to GitLab

1. Go to GitLab project → **Settings → CI/CD → Variables**
2. Click **Add Variable**:
   - **Key**: `TEAMS_WEBHOOK_URL`
   - **Value**: (paste webhook URL)
   - **Flags**: ✅ Protected, ✅ Masked
3. Click **Add Variable**

### 3. Test (Optional)

```bash
# Test with curl
curl -X POST "$TEAMS_WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d '{"text":"✅ Test from GitLab CI setup"}'
```

Or use the test script:
```bash
cd .gitlab
chmod +x test-teams-webhook.sh
./test-teams-webhook.sh "https://outlook.office.com/webhook/YOUR/WEBHOOK/URL"
```

## Notification Details

### Pipeline Started
- **When**: Push or manual pipeline run
- **Branch**: All branches
- **Color**: Orange
- **Info**: Branch, commit, author

### Build Success/Failure
- **When**: After `build:drupal` completes
- **Branch**: `live` only
- **Color**: Green (success) / Red (failure)
- **Info**: Environment, commit, job logs (failure)

### Manual Approval Required
- **When**: After infrastructure plan completes
- **Branch**: `live` only  
- **Color**: Blue
- **Info**: Direct link to approve

### Deployment Started
- **When**: Deployment begins
- **Branch**: `live` only
- **Color**: Orange
- **Info**: Environment, site, language

### Deployment Success
- **When**: Deployment completes successfully
- **Branch**: `live` only
- **Color**: Green
- **Info**: Duration, image tag, deployer

### Deployment Failure
- **When**: Deployment fails
- **Branch**: `live` only
- **Color**: Red
- **Alert**: Includes urgent warning message
- **Info**: Failed stage, commit author

### Security Scan Issues
- **When**: SAST/dependency/secret detection fails
- **Branch**: `live` only
- **Color**: Red
- **Info**: Link to security report

### Pipeline Completed
- **When**: All stages finish successfully
- **Color**: Green
- **Info**: Total duration, pipeline link

## Message Format

All notifications include:
- Color-coded card (🟢 success, 🔴 failure, 🟠 warning, 🔵 action)
- Emoji status indicator
- Actionable buttons for relevant links
- Environment (preproduction/dev or preproduction/stage)
- Branch name
- Commit SHA with link to commit view
- Author/Deployer name
- Pipeline number

## Configuration

### Optional Variables

Override defaults in GitLab CI/CD Variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `TEAMS_TITLE` | Notification title prefix | `GitLab CI` |

### Customize Notifications

Edit `.gitlab/teams-notifications.yml` to:
- Change notification triggers (modify `rules:` sections)
- Add custom fields to messages
- Create new notification types (extend `.teams_notify`)

Example - Only notify on `live` branch:
```yaml
notify:build:success:
  rules:
    - if: '$TEAMS_WEBHOOK_URL == null'
      when: never
    - if: '$CI_COMMIT_BRANCH == "live"'  # Removed "main" check
      when: on_success
```

## Troubleshooting

### No Notifications Appearing

**Check webhook URL:**
```bash
# Test webhook directly
curl -X POST "$TEAMS_WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d '{"text":"Test"}'
```

Expected response: `1` or success status
- `404`: Invalid URL
- `400`: Invalid payload format
- Timeout: Network/firewall issue

**Check GitLab:**
1. Go to **Settings → CI/CD → Variables**
2. Verify `TEAMS_WEBHOOK_URL` exists and is enabled
3. Check notification job logs in pipeline for curl errors

**Check Microsoft Teams:**
- Ensure webhook connector is enabled in the channel
- Verify channel still exists
- Check webhook configured for correct channel

### Notifications Too Frequent

Edit notification `rules:` in `.gitlab/teams-notifications.yml` to filter by branch, environment, or custom conditions.

### Job Not Running

If notification jobs don't appear in pipeline:
1. Check `TEAMS_WEBHOOK_URL` is set (jobs skip if not configured)
2. Verify branch matches `rules:` conditions
3. Check job `needs:` dependencies succeeded

## Security

✅ **DO**:
- Use "Masked" flag for `TEAMS_WEBHOOK_URL`
- Use "Protected" flag for production webhooks
- Rotate webhook if exposed (reconfigure connector in Teams)

❌ **DON'T**:
- Commit webhook URLs to git
- Share webhooks in public channels
- Use same webhook for dev/prod

## Support

1. Review this documentation
2. Check GitLab pipeline job logs
3. Test webhook with curl
4. Contact DevOps team
