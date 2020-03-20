# Use the latest Amazon Linux 2 AMI to avoid old images having security issues
data "aws_ssm_parameter" "utility-ami" {
  name = "/aws/service/ami-amazon-linux-latest/amzn2-ami-hvm-x86_64-gp2"
}

data "template_cloudinit_config" "utility" {
  base64_encode = true
  gzip          = true

  part {
    filename     = "init.cfg"
    content_type = "text/cloud-config"
    content      = <<-EOF
    #cloud-config
    packages:
      - mariadb
    EOF
  }
}

# Create a utility server if requested
resource "aws_instance" "utility" {
  ami                         = data.aws_ssm_parameter.utility-ami.value
  associate_public_ip_address = false
  instance_type               = "t3a.micro"
  subnet_id                   = aws_subnet.private[0].id
  iam_instance_profile        = aws_iam_instance_profile.bastion_profile.name
  user_data_base64            = data.template_cloudinit_config.utility.rendered

  vpc_security_group_ids = [
    aws_security_group.bastion.id,

    # Grant access from the utility server for administrative tasks
    aws_security_group.database_access.id,
    aws_security_group.cache_access.id,
    aws_security_group.search_access.id
  ]

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Bastion"
  }
}
