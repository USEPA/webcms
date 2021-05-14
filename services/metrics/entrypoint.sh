#!/bin/sh

# Preflight check: ensure required environment variables have been set

if test -z "$AWS_REGION"; then
  echo "Required environment variable AWS_REGION is not set." >&2
  exit 1
fi

if test -z "$WEBCMS_SITE"; then
  echo "Required environment variable WEBCMS_SITE is not set." >&2
  exit 1
fi

while true; do
  # Sleep at the start of the loop to allow this task to fully warm up
  sleep 60

  # If curl returned an error, skip reporting and
  if ! input="$(curl -sS "http://localhost:8080/status?json")"; then
    echo "Curl failed to load metrics; skipping" >&2
    continue
  fi

  if test -z "$input"; then
    echo "Received empty metrics; skipping" >&2
    continue
  fi

  metrics="$(echo "$input" | jq -c -f /etc/transform.jq)"

  echo "Input metrics: $input"
  echo "Output metrics: $metrics"

  aws cloudwatch put-metric-data --namespace WebCMS/FPM --metric-data "$metrics"
done
