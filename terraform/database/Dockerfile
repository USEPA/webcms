FROM hashicorp/terraform:0.14.7

WORKDIR /app

ENV TF_IN_AUTOMATION=1 TF_INPUT=0

COPY .terraform.lock.hcl providers.tf ./
RUN terraform init -backend=false
COPY *.tf ./

# Perform a build-time check to ensure we didn't do something dumb
RUN terraform fmt -diff -check && terraform validate

ENTRYPOINT \
  set -exu \
  && terraform init \
    -backend-config="bucket=$BACKEND_STORAGE" \
    -backend-config="dynamodb_table=$BACKEND_LOCKS" \
  && terraform plan -out plan \
  && terraform apply plan
