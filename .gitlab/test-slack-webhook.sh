#!/bin/bash
# Test script for Slack webhook integration
# Usage: ./test-slack-webhook.sh <webhook-url>

set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <webhook-url>"
  echo ""
  echo "Example:"
  echo "  $0 https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXX"
  exit 1
fi

WEBHOOK_URL="$1"

echo "Testing Slack webhook..."
echo "URL: ${WEBHOOK_URL:0:40}..."
echo ""

# Test 1: Simple text message
echo "Test 1: Sending simple text message..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d '{"text":"✅ Test message from GitLab CI/CD webhook test script"}')

if [ "$RESPONSE" = "200" ]; then
  echo "✅ Success! Check your Slack channel for the test message."
else
  echo "❌ Failed with HTTP status: $RESPONSE"
  exit 1
fi

echo ""

# Test 2: Rich formatted message (similar to actual notifications)
echo "Test 2: Sending formatted message..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d @- <<EOF
{
  "username": "GitLab CI",
  "icon_emoji": ":gitlab:",
  "attachments": [{
    "color": "good",
    "fallback": "Test Notification - Webhook Configuration Test",
    "title": "🧪 Test Notification - Webhook Configuration Test",
    "text": "This is a test of the rich message format used by GitLab CI/CD notifications.",
    "fields": [
      {
        "title": "Test Field 1",
        "value": "This is a test value",
        "short": true
      },
      {
        "title": "Test Field 2",
        "value": "Another test value",
        "short": true
      },
      {
        "title": "Status",
        "value": "✅ Webhook is working correctly",
        "short": false
      }
    ],
    "footer": "Test Script",
    "ts": $(date +%s)
  }]
}
EOF
)

if [ "$RESPONSE" = "200" ]; then
  echo "✅ Success! Check your Slack channel for the formatted test message."
else
  echo "❌ Failed with HTTP status: $RESPONSE"
  exit 1
fi

echo ""
echo "✅ All tests passed! Your webhook is configured correctly."
echo ""
echo "Next steps:"
echo "1. Add the webhook URL to GitLab CI/CD variables:"
echo "   - Go to: Settings → CI/CD → Variables"
echo "   - Key: SLACK_WEBHOOK_URL"
echo "   - Value: $WEBHOOK_URL"
echo "   - Flags: Protected + Masked (recommended)"
echo ""
echo "2. Push a commit to trigger the pipeline and see notifications in action!"
