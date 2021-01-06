data "aws_iam_policy_document" "ssm_automation_assume_role_policy" {
  version = "2012-10-17"

  statement {
    sid     = "1"
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ssm.amazonaws.com"]
    }
  }
}

# Create a service role for Systems Manager. This role is used by AWS to interact with the
# automation documents in this file, and is distinct from the role used by the automated
# EC2 instances themselves.
resource "aws_iam_role" "ssm_automation_role" {
  name        = "${local.role-prefix}SSMAutomationRole"
  description = "Role for Systems Manager to launch automation documents"

  assume_role_policy = data.aws_iam_policy_document.ssm_automation_assume_role_policy.json

  tags = local.common-tags
}

# Grant the automation role basic automation permissions
resource "aws_iam_role_policy_attachment" "ssm_automation" {
  role       = aws_iam_role.ssm_automation_role.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonSSMAutomationRole"
}

# Grant automation permission to provide the automated EC2 role to scripts
data "aws_iam_policy_document" "ssm_automation_pass_role" {
  version = "2012-10-17"

  statement {
    sid       = "passRole"
    effect    = "Allow"
    actions   = ["iam:PassRole"]
    resources = [aws_iam_role.ec2_automation_role.arn]
  }
}

resource "aws_iam_policy" "ssm_automation_pass_role" {
  name        = "${local.role-prefix}AutomationPassRole"
  description = "Permits using the EC2 automation role"

  policy = data.aws_iam_policy_document.ssm_automation_pass_role.json
}

resource "aws_iam_role_policy_attachment" "ssm_automation_pass_role" {
  role       = aws_iam_role.ssm_automation_role.name
  policy_arn = aws_iam_policy.ssm_automation_pass_role.arn
}

data "aws_iam_policy_document" "ec2_automation_assume_role_policy" {
  version = "2012-10-17"

  statement {
    sid     = "1"
    effect  = "Allow"
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

# Create an automated instance role. This is the role that is attached to the automated
# EC2 instances and thus needs permissions to interact with the backups bucket and DB
# credentials.
resource "aws_iam_role" "ec2_automation_role" {
  name        = "${local.role-prefix}AutomatedInstanceRole"
  description = "Role for automated EC2 instances"

  assume_role_policy = data.aws_iam_policy_document.ec2_automation_assume_role_policy.json

  tags = local.common-tags
}

# Grant automated EC2 instances permission to read from (and write to) the DB backups
# S3 bucket.
data "aws_iam_policy_document" "ec2_automation_s3_access" {
  version = "2012-10-17"

  statement {
    sid       = "objectReadWrite"
    effect    = "Allow"
    actions   = ["s3:GetObject", "s3:PutObject"]
    resources = ["${aws_s3_bucket.backups.arn}/*"]
  }
}

resource "aws_iam_policy" "ec2_automation_s3_access" {
  name        = "${local.role-prefix}AutomatedInstanceS3Access"
  description = "Grants access to the DB backups S3 bucket"

  policy = data.aws_iam_policy_document.ec2_automation_s3_access.json
}

resource "aws_iam_role_policy_attachment" "ec2_automation_s3_access" {
  role       = aws_iam_role.ec2_automation_role.name
  policy_arn = aws_iam_policy.ec2_automation_s3_access.arn
}

# Grant automated EC2 instances permission to use the D8 and D7 database credentials
data "aws_iam_policy_document" "ec2_automation_secrets_access" {
  version = "2012-10-17"

  statement {
    sid     = "readCredentials"
    effect  = "Allow"
    actions = ["secretsmanager:GetSecretValue"]

    resources = [
      aws_secretsmanager_secret.db_app_credentials.arn,
      aws_secretsmanager_secret.db_app_d7_credentials.arn,
    ]
  }

  statement {
    sid       = "decryptSecret"
    effect    = "Allow"
    actions   = ["kms:Decrypt"]
    resources = [data.aws_kms_alias.secretsmanager.target_key_arn]

    condition {
      test     = "StringEquals"
      variable = "kms:ViaService"
      values   = ["secretsmanager.${var.aws-region}.amazonaws.com"]
    }
  }
}

resource "aws_iam_policy" "ec2_automation_secrets_access" {
  name        = "${local.role-prefix}AutomatedInstanceSecretsAccess"
  description = "Grants access to the D8 and D7 database credentials"

  policy = data.aws_iam_policy_document.ec2_automation_secrets_access.json
}

resource "aws_iam_role_policy_attachment" "ec2_automation_secrets_access" {
  role       = aws_iam_role.ec2_automation_role.name
  policy_arn = aws_iam_policy.ec2_automation_secrets_access.arn
}

resource "aws_iam_role_policy_attachment" "ec2_automation_managed_instance" {
  role       = aws_iam_role.ec2_automation_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy_attachment" "ec2_automation_cloudwatch_agent" {
  role       = aws_iam_role.ec2_automation_role.name
  policy_arn = "arn:aws:iam::aws:policy/CloudWatchAgentServerPolicy"
}

resource "aws_iam_instance_profile" "ec2_automation_profile" {
  name = "${local.role-prefix}AutomatedInstanceProfile"
  role = aws_iam_role.ec2_automation_role.name
}

# Elements shared across all SSM documents. Since our documents share the same general
# structure (launch instance, run script, terminate instance), we avoid repetition and
# potential configuration drift by storing the shared steps here.
locals {
  # Step 1. Spawn a new EC2 instance using our automated instance profile.
  ssm-run-instance = {
    name   = "runInstance"
    action = "aws:runInstances"

    inputs = {
      # Start a relatively small EC2 instance running stock Amazon Linux 2.
      ImageId                = "{{ InstanceAMI }}"
      SubnetId               = aws_subnet.private[0].id
      InstanceType           = "t3a.small"
      IamInstanceProfileName = aws_iam_instance_profile.ec2_automation_profile.name

      # Use the automated server security group and also grant access to the RDS proxy
      # and VPC interfaces.
      SecurityGroupIds = [
        aws_security_group.automation.id,
        aws_security_group.proxy_access.id,
        aws_security_group.interface_access.id,
      ]

      # Use a large ephemeral volume due to the large size of uncompressed DB dumps.
      BlockDeviceMappings = [
        {
          DeviceName = "/dev/xvda"
          Ebs = {
            DeleteOnTermination = true
            VolumeSize          = 64
          }
        }
      ]
    }
  }

  # Step 2. Ensure the instance is ready and connected to Systems Manager.
  ssm-instance-warmup = {
    name   = "instanceWarmUp"
    action = "aws:waitForAwsResourceProperty"

    # Wait for an hour, up to three times, before giving up
    timeoutSeconds = 600
    maxAttempts    = 3

    # If the warmup fails, terminate the instance
    onFailure = "step:terminateInstance"

    inputs = {
      Service          = "ec2"
      Api              = "DescribeInstanceStatus"
      PropertySelector = "$.InstanceStatuses[0].InstanceStatus.Details[0].Status"
      DesiredValues    = ["passed"]
      InstanceIds      = ["{{ runInstance.InstanceIds }}"]
    }
  }

  # Step 4. Terminate the instance. All steps beyond the first should route to here on
  # failure in order to prevent accruing costs for a stray EC2 instance. (Step 3, the SSM
  # command, is omitted here since it varies between automation documents.)
  ssm-cleanup = {
    name   = "terminateInstance"
    action = "aws:changeInstanceState"
    isEnd  = true

    inputs = {
      DesiredState = "terminated"
      InstanceIds  = "{{ runInstance.InstanceIds }}"
    }
  }
}

# Create a random ID in order to force Terraform to recreate automation documents.
resource "random_id" "automation" {
  byte_length = 4

  # This keeper needs to be bumped whenever an automation document changes.
  keepers = {
    version = 1
  }
}

# Create an automation document that loads a D7 backup from the backups bucket into the
# database.
resource "aws_ssm_document" "d7_load_database" {
  name          = "WebCMS-${local.env-title}-D7LoadDatabase-${random_id.automation.hex}"
  document_type = "Automation"

  content = jsonencode({
    schemaVersion = "0.3"
    description   = <<-EOF
      # Load D7 Database Automation

      This automation loads a copy of the latest D7 database dump into the `webcms_d7` DB.
      The dump must be present in the DB backups bucket (`s3://${aws_s3_bucket.backups.bucket}`)
      and be saved as a gzip-compressed `.sql` file.

      ## Parameters

      * `BackupName` - the path to the database dump in S3.
      * `InstanceAMI` - this parameter exists due to a limitation in Systems Manager. Leave
        it blank in order to use the latest Amazon Linux 2 image when automation runs.
    EOF

    # Run this document as our SSM service role
    assumeRole = aws_iam_role.ssm_automation_role.arn

    parameters = {
      BackupName = {
        type        = "String"
        description = "Name of the DB in the backups bucket (s3://${aws_s3_bucket.backups.bucket})"
      }

      # Systems Manager won't let us directly reference the AMI parameter in our document,
      # so we have to use this default value to have SSM read it.
      InstanceAMI = {
        type        = "String"
        description = "Amazon Linux 2 AMI to run on the instance. Defaults to the latest."
        default     = "{{ssm:/aws/service/ami-amazon-linux-latest/amzn2-ami-hvm-x86_64-gp2}}"
      }
    }

    mainSteps = [
      # Use the shared steps to spawn the instance
      local.ssm-run-instance,
      local.ssm-instance-warmup,

      # Run our import script on the instance
      {
        name   = "runImportScript"
        action = "aws:runCommand"

        # Wait up to 8 hours
        timeoutSeconds = 8 * 60 * 60

        # Terminate the instance on failure
        onFailure = "step:terminateInstance"

        inputs = {
          DocumentName = "AWS-RunShellScript"
          InstanceIds  = "{{ runInstance.InstanceIds }}"

          # Send automation logs to CloudWatch
          CloudWatchOutputConfig = {
            CloudWatchLogGroupName  = aws_cloudwatch_log_group.ssm_d7_automation.name
            CloudWatchOutputEnabled = true
          }

          # Script steps:
          # 1. Install necessary CLI tools
          # 2. Download the backup from S3 and decompress it
          # 3. Obtain the D7 login credentials
          # 4. Load the dump into MySQL through the RDS proxy
          Parameters = {
            executionTimeout = tostring(8 * 60 * 60)
            workingDirectory = "/tmp"
            commands         = <<-EOF
              set -euo pipefail

              yum install -y awscli jq mariadb

              echo "Downloading {{ BackupName }} from S3"

              aws s3 cp s3://${aws_s3_bucket.backups.bucket}/{{ BackupName }} ./d7.sql.gz
              gunzip ./d7.sql.gz

              credentials="$(
                aws --region=${var.aws-region} \
                secretsmanager get-secret-value \
                  --secret-id ${aws_secretsmanager_secret.db_app_d7_credentials.id} |
                  jq -r .SecretString
              )"

              username="$(jq -r .username <<<"$credentials")"
              password="$(jq -r .password <<<"$credentials")"

              echo "Loading {{ BackupName }} into MySQL"

              mysql \
                --host=${aws_db_proxy.proxy.endpoint} \
                --user="$username" \
                --password="$password" \
                webcms_d7 \
                <./d7.sql
            EOF
          }
        }
      },

      # Clean up the instance
      local.ssm-cleanup
    ]
  })

  tags = local.common-tags

  lifecycle {
    create_before_destroy = true
  }
}

# Create an automation document that loads a D8 backup from the backups bucket into the
# database.
resource "aws_ssm_document" "d8_load_database" {
  name          = "WebCMS-${local.env-title}-D8Load-Database-${random_id.automation.hex}"
  document_type = "Automation"

  content = jsonencode({
    schemaVersion = "0.3"
    description   = <<-EOF
      # Load D8 Database Automation

      This automation loads a copy of a D8 database dump into the `webcms` DB.
      The dump must be present in the DB backups bucket (s3://${aws_s3_bucket.backups.bucket})
      and be saved as a gzip-compressed `.sql` file.

      ## Parameters

      * `BackupName` - the path to the database dump in S3.
      * `InstanceAMI` - this parameter exists due to a limitation in Systems Manager. Leave
        it blank in order to use the latest Amazon Linux 2 image when automation runs.
    EOF

    # Run this document as our SSM service role
    assumeRole = aws_iam_role.ssm_automation_role.arn

    parameters = {
      BackupName = {
        type        = "String"
        description = "Name of the DB in the backups bucket (s3://${aws_s3_bucket.backups.bucket})"
      }

      # Systems Manager won't let us directly reference the AMI parameter in our document,
      # so we have to use this default value to have SSM read it.
      InstanceAMI = {
        type        = "String"
        description = "Amazon Linux 2 AMI to run on the instance. Defaults to the latest."
        default     = "{{ssm:/aws/service/ami-amazon-linux-latest/amzn2-ami-hvm-x86_64-gp2}}"
      }
    }

    mainSteps = [
      # Use the shared steps to spawn the instance
      local.ssm-run-instance,
      local.ssm-instance-warmup,

      # Run our import script on the instance
      {
        name   = "runImportScript"
        action = "aws:runCommand"

        # Wait up to 8 hours
        timeoutSeconds = 8 * 60 * 60

        # Terminate the instance on failure
        onFailure = "step:terminateInstance"

        inputs = {
          DocumentName = "AWS-RunShellScript"
          InstanceIds  = "{{ runInstance.InstanceIds }}"

          # Send automation logs to CloudWatch
          CloudWatchOutputConfig = {
            CloudWatchLogGroupName  = aws_cloudwatch_log_group.ssm_d8_automation.name
            CloudWatchOutputEnabled = true
          }

          # Script steps:
          # 1. Install necessary CLI tools
          # 2. Download the backup from S3 and decompress it
          # 3. Obtain the D7 login credentials
          # 4. Load the dump into MySQL through the RDS proxy
          Parameters = {
            executionTimeout = tostring(8 * 60 * 60)
            workingDirectory = "/tmp"
            commands         = <<-EOF
              set -euo pipefail

              yum install -y awscli jq mariadb

              echo "Downloading {{ BackupName }} from S3"

              aws s3 cp s3://${aws_s3_bucket.backups.bucket}/{{ BackupName }} ./d8.sql.gz
              gunzip ./d8.sql.gz

              credentials="$(
                aws --region=${var.aws-region} \
                secretsmanager get-secret-value \
                  --secret-id ${aws_secretsmanager_secret.db_app_credentials.id} |
                  jq -r .SecretString
              )"

              username="$(jq -r .username <<<"$credentials")"
              password="$(jq -r .password <<<"$credentials")"

              echo "Loading {{ BackupName }} into MySQL"

              mysql \
                --host=${aws_db_proxy.proxy.endpoint} \
                --user="$username" \
                --password="$password" \
                webcms \
                <./d8.sql
            EOF
          }
        }
      },

      # Clean up the instance
      local.ssm-cleanup
    ]
  })

  tags = local.common-tags

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_ssm_document" "d8_dump_database" {
  name          = "WebCMS-${local.env-title}-D8DumpDatabase-${random_id.automation.hex}"
  document_type = "Automation"

  content = jsonencode({
    schemaVersion = "0.3"
    description   = <<-EOF
      # Dump D8 Database Automation

      This automation dumps the D8 database and saves it into the DB backups bucket
      (`s3://${aws_s3_bucket.backups.bucket}`) as `webcms-YYYY-MM-DD-HHMM.sql.gz`.

      ## Parameters
      * `InstanceAMI` - this parameter exists due to a limitation in Systems Manager. Leave
        it blank in order to use the latest Amazon Linux 2 image when automation runs.
    EOF

    assumeRole = aws_iam_role.ssm_automation_role.arn

    parameters = {
      # See comments in the D7 automation for why this exists
      InstanceAMI = {
        type        = "String"
        description = "Amazon Linux 2 AMI to run on the instance. Defaults to the latest."
        default     = "{{ssm:/aws/service/ami-amazon-linux-latest/amzn2-ami-hvm-x86_64-gp2}}"
      }
    }

    mainSteps = [
      # Spawn the instance
      local.ssm-run-instance,
      local.ssm-instance-warmup,

      # Create and upload the DB dump
      {
        name   = "runExportScript"
        action = "aws:runCommand"

        timeoutSeconds = 8 * 60 * 60
        onFailure      = "step:terminateInstance"

        inputs = {
          DocumentName = "AWS-RunShellScript"
          InstanceIds  = "{{ runInstance.InstanceIds }}"

          CloudWatchOutputConfig = {
            CloudWatchLogGroupName  = aws_cloudwatch_log_group.ssm_d8_automation.name
            CloudWatchOutputEnabled = true
          }

          # Script steps:
          # 1. Install necessary CLI tools
          # 2. Obtain D8 DB credentials
          # 3. Dump the database
          # 4. Compress the database
          # 5. Upload the compressed file to S3
          Parameters = {
            executionTimeout = tostring(8 * 60 * 60)
            workingDirectory = "/tmp"
            commands         = <<-EOF
              set -euo pipefail

              yum install -y awscli jq mariadb

              credentials="$(
                aws --region=${var.aws-region} \
                secretsmanager get-secret-value \
                  --secret-id ${aws_secretsmanager_secret.db_app_credentials.id} |
                  jq -r .SecretString
              )"

              username="$(jq -r .username <<<"$credentials")"
              password="$(jq -r .password <<<"$credentials")"

              dump_name="webcms-$(date +%Y-%m-%d-%H%M).sql"
              archive_name="$dump_name.gz"

              echo "Dumping database as $dump_name"

              mysqldump \
                --host=${aws_db_proxy.proxy.endpoint} \
                --user="$username" \
                --password="$password" \
                webcms \
                >"$dump_name"

              echo "Compressing $dump_name to $archive_name"
              gzip --verbose "$dump_name"

              echo "Uploading $archive_name"
              aws s3 cp "$archive_name" "s3://${aws_s3_bucket.backups.bucket}/$archive_name"
            EOF
          }
        }
      },

      # Clean up the instance
      local.ssm-cleanup
    ]
  })

  tags = local.common-tags

  lifecycle {
    create_before_destroy = true
  }
}
