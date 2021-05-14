# This is a jq script (see https://stedolan.github.io/jq/) to transform PHP-FPM's JSON
# stats into a more CloudWatch-friendly format.
#
# See the the README for a discussion of the transformation logic.

# Capture the input as $input
. as $input

# Next, iterate over each of the PHP-FPM metrics we're interested in. Each object has this
# structure:
# * key: PHP-FPM metric name
# * name: CloudWatch metric name
# * unit: CloudWatch metric unit
| [
  { key: "start since", name: "Age", unit: "Seconds" },
  { key: "accepted conn", name: "RequestsAccepted", unit: "Count" },
  { key: "listen queue", name: "RequestsPending", unit: "Count" },
  { key: "listen queue len", name: "ListenQueueLength", unit: "Count" },
  { key: "idle processes", name: "ProcessesIdle", unit: "Count" },
  { key: "active processes", name: "ProcessesActive", unit: "Count" },
  { key: "max children reached", name: "MaxCh8ildrenReached", unit: "Count" }
]

# For each PHP-FPM metric, construct a CloudWatch metric object
| map({
  MetricName: .name,
  Unit: .unit,
  Value: $input[.key],
  Timestamp: now | floor,
  Dimensions: [
    { Name: "Environment", Value: $ENV.WEBCMS_SITE }
  ],
})
