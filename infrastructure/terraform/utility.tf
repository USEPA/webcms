# Use the latest Amazon Linux 2 AMI to avoid old images having security issues
data "aws_ssm_parameter" "utility-ami" {
  name = "/aws/service/ami-amazon-linux-latest/amzn2-ami-hvm-x86_64-gp2"
}

data "template_cloudinit_config" "utility" {
  base64_encode = true
  gzip          = true

  part {
    content_type = "text/x-shellscript"
    content      = <<-USERDATA
    #!/bin/bash
    set -euo pipefail

    # Run custom bootstrap script (if present)
    ${var.server-extra-bootstrap}

    USERDATA
  }

  part {
    filename     = "init.cfg"
    content_type = "text/cloud-config"
    content      = <<-EOF
    #cloud-config
    packages:
      - mariadb
    write_files:
      - path: /usr/local/bin/webcms-env-info
        permissions: 0755
        content: |
          #!/bin/bash

          cat <<INFO
          MySQL: ${aws_rds_cluster.db.endpoint}

          D8 user: webcms
          D8 name: webcms
          D8 password: https://console.aws.amazon.com/secretsmanager/home?region=${data.aws_region.current.name}#/secret?name=${urlencode(aws_secretsmanager_secret.db_app_credentials.name)}

          D7 user: webcms_d7
          D7 name: webcms_d7
          D7 password: https://console.aws.amazon.com/secretsmanager/home?region=${data.aws_region.current.name}#/secret?name=${urlencode(aws_secretsmanager_secret.db_app_d7_credentials.name)}

          Memcached: ${aws_elasticache_cluster.cache.configuration_endpoint}

          Elasticsearch: https://${aws_elasticsearch_domain.es.endpoint}

          S3: s3://${aws_s3_bucket.uploads.bucket}
          INFO
      - path: /usr/local/bin/webcms-sql-dump
        permissions: 0755
        content: |
          #!/bin/bash

          set -euo pipefail

          # Tables we only want the structure of most of these are cache-related
          structure_tables=(
            cache_bootstrap
            cache_config
            cache_container
            cache_default
            cache_discovery
            cache_discovery_migration
            cache_dynamic_page_cache
            cache_entity
            cache_group_permission
            cache_groupmenu
            cache_menu
            cache_migrate
            cache_page
            cache_render
            cache_rest
            cache_signal
            cache_toolbar
            cache_ultimate_cron_logger
            watchdog
          )

          # Name of the SQL export: includes environment name and the time the dump started
          filename="epad8-${var.site-env-name}-$(date +%Y-%m-%d-%H%M%S).sql"

          echo "Beginning dump"
          echo ""
          echo "You will be asked for the MySQL password twice. Please have it ready."
          echo "Console link: https://console.aws.amazon.com/secretsmanager/home?region=${data.aws_region.current.name}#/secret?name=${urlencode(aws_secretsmanager_secret.db_app_credentials.name)}"

          # Create the file, clearing it out if it already exists
          echo > "$filename"

          echo "Dumping structure of $${#structure_tables[@]} tables..."
          mysqldump -u webcms -p -h ${aws_rds_cluster.db.endpoint} --no-data webcms "$${structure_tables[@]}" >> "$filename"

          # Convert table names to --ignore-table flags for the next mysqldump command
          for key in "$${!structure_tables[@]}"; do
            table="$${structure_tables[$key]}"
            structure_tables[$key]="--ignore-table=webcms.$table"
          done

          echo "Dumping content of other tables"
          mysqldump -u webcms -p -h ${aws_rds_cluster.db.endpoint} webcms "$${structure_tables[@]}" >> "$filename"

          echo "Compressing dump"
          gzip "$filename"

          echo "Complete. Dump saved as $filename.gz"
          echo ""
          echo "To upload to S3, run this command:"
          echo aws s3 cp "$filename.gz" "s3://${aws_s3_bucket.uploads.bucket}/$filename.gz"
    EOF
  }
}

resource "aws_launch_template" "utility" {
  name = "webcms-launch-template-${local.env-suffix}-utility"

  image_id      = data.aws_ssm_parameter.utility-ami.value
  user_data     = data.template_cloudinit_config.utility.rendered
  instance_type = "t3a.micro"

  vpc_security_group_ids = [
    aws_security_group.utility.id,

    # Grant access from the utility server for administrative tasks
    aws_security_group.database_proxy_access.id,
    aws_security_group.cache_access.id,
    aws_security_group.search_access.id,
  ]

  monitoring {
    enabled = true
  }

  iam_instance_profile {
    name = aws_iam_instance_profile.utility_profile.name
  }

  block_device_mappings {
    device_name = "/dev/xvda"

    ebs {
      encrypted             = true
      kms_key_id            = var.encryption-at-rest-key
      volume_size           = 32
      volume_type           = "gp2"
      delete_on_termination = true
    }
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Utility"
  })
}

resource "aws_autoscaling_group" "utility" {
  name = "webcms-autoscaling-${local.env-suffix}-utility"

  max_size = 1
  min_size = 0

  max_instance_lifetime = 7 * 24 * 3600

  vpc_zone_identifier = aws_subnet.private.*.id

  launch_template {
    id      = aws_launch_template.utility.id
    version = "$Latest"
  }

  dynamic "tag" {
    for_each = merge(local.common-tags, { Name = "${local.name-prefix} Utility" })

    content {
      key                 = tag.key
      value               = tag.value
      propagate_at_launch = true
    }
  }
}
