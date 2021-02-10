data "aws_availability_zones" "available" {}

resource "aws_vpc" "vpc" {
  cidr_block = "10.0.0.0/16"

  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = merge(var.tags, {
    Name = "WebCMS VPC (${var.environment})"
  })
}

# Fargate requires a VPC with DHCP options (without this, containers won't launch)
resource "aws_vpc_dhcp_options" "options" {
  domain_name         = "ec2.internal"
  domain_name_servers = ["AmazonProvidedDNS"]

  tags = merge(var.tags, {
    Name = "WebCMS DHCP (${var.environment})"
  })
}

resource "aws_vpc_dhcp_options_association" "options" {
  vpc_id          = aws_vpc.vpc.id
  dhcp_options_id = aws_vpc_dhcp_options.options.id
}

resource "aws_subnet" "public" {
  count = var.az_count

  cidr_block              = cidrsubnet(aws_vpc.vpc.cidr_block, 8, count.index)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  vpc_id                  = aws_vpc.vpc.id
  map_public_ip_on_launch = true

  tags = merge(var.tags, {
    Name = "WebCMS Public ${count.index} (${var.environment})"
  })
}

resource "aws_subnet" "private" {
  count = var.az_count

  cidr_block              = cidrsubnet(aws_vpc.vpc.cidr_block, 8, 10 + count.index)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  vpc_id                  = aws_vpc.vpc.id
  map_public_ip_on_launch = false

  tags = merge(var.tags, {
    Name = "WebCMS Private ${count.index} (${var.environment})"
  })
}

resource "aws_internet_gateway" "gateway" {
  vpc_id = aws_vpc.vpc.id
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.vpc.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.gateway.id
  }

  tags = merge(var.tags, {
    Name = "WebCMS Public (${var.environment})"
  })
}

resource "aws_route_table_association" "public" {
  count = var.az_count

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

resource "aws_eip" "ip" {
  count = var.az_count

  vpc = true

  tags = merge(var.tags, {
    Name = "WebCMS EIP ${count.index} (${var.environment})"
  })
}

resource "aws_nat_gateway" "nat" {
  count = var.az_count

  subnet_id     = aws_subnet.public[count.index].id
  allocation_id = aws_eip.ip[count.index].id

  tags = merge(var.tags, {
    Name = "WebCMS NAT ${count.index} (${var.environment})"
  })
}

resource "aws_route_table" "private" {
  count = var.az_count

  vpc_id = aws_vpc.vpc.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.nat[count.index].id
  }

  tags = merge(var.tags, {
    Name = "WebCMS Private ${count.index} (${var.environment})"
  })
}

resource "aws_route_table_association" "private" {
  count = var.az_count

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private[count.index].id
}

# Allow arbitrary S3 actions to pass through the gateway; this just means that IAM and the
# target S3 bucket itself will need to handle authorization.
data "aws_iam_policy_document" "s3_gateway" {
  version = "2008-10-17"

  statement {
    effect    = "Allow"
    actions   = ["*"]
    resources = ["*"]

    principals {
      type        = "*"
      identifiers = ["*"]
    }
  }
}

data "aws_vpc_endpoint_service" "s3" {
  service      = "s3"
  service_type = "Gateway"
}

resource "aws_vpc_endpoint" "s3" {
  vpc_id       = aws_vpc.vpc.id
  service_name = data.aws_vpc_endpoint_service.s3.service_name

  route_table_ids = concat(aws_route_table.private.*.id, [aws_route_table.public.id])

  policy = data.aws_iam_policy_document.s3_gateway.json

  tags = merge(var.tags, {
    Name = "WebCMS S3 (${var.environment})"
  })
}
