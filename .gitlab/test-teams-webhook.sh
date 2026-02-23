#!/bin/bash
# Test script for Microsoft Teams webhook integration
# Usage: ./test-teams-webhook.sh <webhook-url>

set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <webhook-url>"
  echo ""
  echo "Example:"
  echo "  $0 https://outlook.office.com/webhook/..."
  exit 1
fi

WEBHOOK_URL="$1"

echo "Testing Microsoft Teams webhook..."
echo "URL: ${WEBHOOK_URL:0:40}..."
echo ""

# Test 1: Simple text message
echo "Test 1: Sending simple text message..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d '{"text":"✅ Test message from GitLab CI/CD webhook test script"}')

if [ "$RESPONSE" = "200" ]; then
  echo "✅ Success! Check your Microsoft Teams channel for the test message."
else
  echo "❌ Failed with HTTP status: $RESPONSE"
  exit 1
fi

echo ""

# Test 2: Rich formatted message (MessageCard format)
echo "Test 2: Sending formatted message..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d @- <<EOF
{
  "@type": "MessageCard",
  "@context": "https://schema.org/extensions",
  "summary": "Test Notification - Webhook Configuration Test",
  "themeColor": "28A745",
  "title": "🧪 Test Notification - Webhook Configuration Test",
  "text": "This is a test of the rich message format used by GitLab CI/CD notifications.",
  "sections": [{
    "facts": [
      {
        "name": "Test Field 1:",
        "value": "This is a test value"
      },
      {
        "name": "Test Field 2:",
        "value": "Another test value"
      },
      {
        "name": "Status:",
        "value": "✅ Webhook is working correctly"
      }
    ]
  }],
  "potentialAction": [{
    "@type": "OpenUri",
    "name": "View Documentation",
    "targets": [{
      "os": "default",
      "uri": "https://docs.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using"
    }]
  }]
}
EOF
)

if [ "$RESPONSE" = "200" ]; then
  echo "✅ Success! Check your Microsoft Teams channel for the formatted test message."
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
echo "   - Key: TEAMS_WEBHOOK_URL"
echo "   - Value: $WEBHOOK_URL"
echo "   - Flags: Protected + Masked (recommended)"
echo ""
echo "2. Push a commit to trigger the pipeline and see notifications in action!"
