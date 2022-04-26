FROM alpine:latest

RUN apk add --no-cache aws-cli curl jq

COPY transform.jq /etc/transform.jq
COPY entrypoint.sh /bin/entrypoint.sh

ENTRYPOINT [ "/bin/sh", "/bin/entrypoint.sh" ]
