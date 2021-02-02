data "aws_availability_zones" "available" {}

# Conditionally create a VPC if there isn't an existing one provided
resource "aws_vpc" "main" {
  count = var.vpc-existing-vpc == null ? 1 : 0

  cidr_block = "10.0.0.0/16"

  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} VPC"
  })
}

# If we're creating a VPC, create a DHCP options set. This is needed for Fargate tasks.
resource "aws_vpc_dhcp_options" "options" {
  count = length(aws_vpc.main)

  # This is the default for VPCs
  domain_name         = "ec2.internal"
  domain_name_servers = ["AmazonProvidedDNS"]

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} DHCP"
  })
}

resource "aws_vpc_dhcp_options_association" "options" {
  count = length(aws_vpc.main)

  vpc_id          = aws_vpc.main[0].id
  dhcp_options_id = aws_vpc_dhcp_options.options[0].id
}

# If there was an existing VPC provided, read out its properties
data "aws_vpc" "existing" {
  count = var.vpc-existing-vpc != null ? 1 : 0

  id = var.vpc-existing-vpc
}

locals {
  # ID of the VPC in use - used to avoid copying/pasting this expression over and over again
  vpc-id = length(aws_vpc.main) == 1 ? aws_vpc.main[0].id : data.aws_vpc.existing[0].id

  # Save the local VPC's CIDR block (see security.tf for how this is used)
  vpc-cidr-block = length(aws_vpc.main) == 1 ? aws_vpc.main[0].cidr_block : data.aws_vpc.existing[0].cidr_block

  # This is the CIDR range used for subnetting: if there is an explicit vpc-subnet-block
  # variable, we use that - but fall back to the VPC's full CIDR block if it's not
  # present.
  vpc-subnet-block = var.vpc-subnet-block != null ? var.vpc-subnet-block : local.vpc-cidr-block
}

# Create one public subnet for each availability zone that this VPC spans. Anything
# launched into the public subnets is assigned a public-facing IP automatically, so
# exercise extreme caution when placing resources here.
resource "aws_subnet" "public" {
  count = var.vpc-az-count

  cidr_block              = cidrsubnet(local.vpc-subnet-block, var.vpc-subnet-bits, count.index)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  vpc_id                  = local.vpc-id
  map_public_ip_on_launch = true

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Public ${count.index}"
  })
}

# We also create a private subnet for each availability zone spanned by this VPC. These
# subnets are where almost all application resources (servers, containers, and databases)
# should go, since it prevents the resource from being publicly accessible.
resource "aws_subnet" "private" {
  count = var.vpc-az-count

  cidr_block              = cidrsubnet(local.vpc-subnet-block, var.vpc-subnet-bits, count.index + var.vpc-subnet-offset)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  vpc_id                  = local.vpc-id
  map_public_ip_on_launch = false

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Private ${count.index}"
  })
}

# As with VPCs, we create a new internet gateway if one hasn't been provided.
resource "aws_internet_gateway" "gateway" {
  count = var.vpc-existing-gateway == null ? 1 : 0

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Gateway"
  })
}

locals {
  # As with the vpc-id local, save this here to avoid needless repetition
  gateway-id = length(aws_internet_gateway.gateway) == 1 ? aws_internet_gateway.gateway[0].id : var.vpc-existing-gateway
}

# We only need one route table since there is only a single internet gateway
resource "aws_route_table" "public" {
  vpc_id = local.vpc-id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = local.gateway-id
  }

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Public Routes"
  })
}

resource "aws_route_table_association" "public_association" {
  count = var.vpc-az-count

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

resource "aws_eip" "ip" {
  count = var.vpc-az-count

  vpc = true

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} EIP ${count.index}"
  })
}

resource "aws_nat_gateway" "nat" {
  count = var.vpc-az-count

  subnet_id     = aws_subnet.public[count.index].id
  allocation_id = aws_eip.ip[count.index].id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Nat ${count.index}"
  })
}

# Create one private route table for each NAT gateway - since there is one per subnet, we
# have to replicate this resource unlike the public route table case.
resource "aws_route_table" "private" {
  count = var.vpc-az-count

  vpc_id = local.vpc-id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} Private Routes ${count.index}"
  })
}

resource "aws_route" "private_gateway" {
  count = length(aws_route_table.private)

  route_table_id = aws_route_table.private[count.index].id

  destination_cidr_block = "0.0.0.0/0"
  nat_gateway_id         = aws_nat_gateway.nat[count.index].id
}

resource "aws_route_table_association" "private_association" {
  count = var.vpc-az-count

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private[count.index].id
}

# Add S3 into the VPC as a service gateway to lower traffic costs
data "aws_vpc_endpoint_service" "s3" {
  service      = "s3"
  service_type = "Gateway"
}

resource "aws_vpc_endpoint" "s3" {
  vpc_id            = local.vpc-id
  service_name      = data.aws_vpc_endpoint_service.s3.service_name
  vpc_endpoint_type = "Gateway"

  route_table_ids = concat(aws_route_table.private.*.id, [aws_route_table.public.id])

  # Don't block access to S3 at the endpoint level: instead, rely on IAM and bucket policies
  policy = jsonencode({
    Version = "2008-10-17",
    Statement = [
      {
        Action    = "*",
        Effect    = "Allow",
        Resource  = "*",
        Principal = "*"
      }
    ]
  })

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} S3 Gateway"
  })
}

locals {
  # Place Systems Manager endpoints into the VPC in order to further secure the connection
  ssm-endpoints = var.vpc-create-interfaces ? ["ssm", "ec2", "ec2messages", "ssmmessages"] : []
}

data "aws_vpc_endpoint_service" "ssm" {
  for_each = toset(local.ssm-endpoints)

  service = each.value
}

resource "aws_vpc_endpoint" "ssm" {
  for_each = toset(local.ssm-endpoints)

  vpc_id              = local.vpc-id
  service_name        = data.aws_vpc_endpoint_service.ssm[each.value].service_name
  vpc_endpoint_type   = "Interface"
  private_dns_enabled = true
  security_group_ids  = [aws_security_group.interface.id]
  subnet_ids          = aws_subnet.private.*.id

  tags = merge(local.common-tags, {
    Name = "${local.name-prefix} SSM Interface: ${each.value}"
  })
}
