FROM hashicorp/terraform:0.14.5

WORKDIR /app

COPY .terraform.lock.hcl ./
COPY *.tf ./
RUN terraform init -backend=false
