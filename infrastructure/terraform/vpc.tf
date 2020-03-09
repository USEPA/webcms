data "aws_availability_zones" "available" {}

resource "aws_vpc" "main" {
  cidr_block = "10.0.0.0/16"

  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS VPC"
  }
}

# Enable private DNS (see dns.tf)
resource "aws_vpc_dhcp_options" "main" {
  domain_name         = "epa.local"
  domain_name_servers = ["AmazonProvidedDNS"]

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS DHCP"
  }
}

resource "aws_vpc_dhcp_options_association" "main" {
  vpc_id          = aws_vpc.main.id
  dhcp_options_id = aws_vpc_dhcp_options.main.id
}

# Create one public subnet for each availability zone that this VPC spans. Anything
# launched into the public subnets is assigned a public-facing IP automatically, so
# exercise extreme caution when placing resources here.
resource "aws_subnet" "public" {
  count = var.vpc-az-count

  cidr_block              = cidrsubnet(aws_vpc.main.cidr_block, 8, count.index)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  vpc_id                  = aws_vpc.main.id
  map_public_ip_on_launch = true

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Public ${count.index}"
  }
}

# We also create a private subnet for each availability zone spanned by this VPC. These
# subnets are where almost all application resources (servers, containers, and databases)
# should go, since it prevents the resource from being publicly accessible.
resource "aws_subnet" "private" {
  count = var.vpc-az-count

  cidr_block              = cidrsubnet(aws_vpc.main.cidr_block, 8, count.index + 128)
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  vpc_id                  = aws_vpc.main.id
  map_public_ip_on_launch = false

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Private ${count.index}"
  }
}

resource "aws_internet_gateway" "gateway" {
  vpc_id = aws_vpc.main.id

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Gateway"
  }
}

# We only need one route table since there is only a single internet gateway
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.gateway.id
  }

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Public Routes"
  }
}

resource "aws_route_table_association" "public_association" {
  count = var.vpc-az-count

  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

resource "aws_eip" "ip" {
  count = var.vpc-az-count

  vpc = true

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS EIP ${count.index}"
  }
}

resource "aws_nat_gateway" "nat" {
  count = var.vpc-az-count

  subnet_id     = aws_subnet.public[count.index].id
  allocation_id = aws_eip.ip[count.index].id

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Nat ${count.index}"
  }
}

# Create one private route table for each NAT gateway - since there is one per subnet, we
# have to replicate this resource unlike the public route table case.
resource "aws_route_table" "private" {
  count = var.vpc-az-count

  vpc_id = aws_vpc.main.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.nat[count.index].id
  }

  tags = {
    Application = "WebCMS"
    Name        = "WebCMS Private Routes-${count.index}"
  }
}

resource "aws_route_table_association" "private_association" {
  count = var.vpc-az-count

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private[count.index].id
}

# Add S3 into the VPC as a service gateway to lower traffic costs
data "aws_vpc_endpoint_service" "s3" {
  service = "s3"
}

resource "aws_vpc_endpoint" "s3" {
  vpc_id       = aws_vpc.main.id
  service_name = data.aws_vpc_endpoint_service.s3.service_name

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
}
