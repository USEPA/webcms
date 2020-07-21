# About this directory

If you are using a service that _absolutely_ must use TLS (and cannot be disabled or at
least left unverified), use this directory. This directory contains a certificate authority (CA) generated using the [`mkcert`](https://github.com/FiloSottile/mkcert) tool.

## Creating a new cert

For each service that needs to use TLS, run the following shell command. The parameter `<domain>` in this example is the name of a service in `docker-compose.yml`. This is used as a hostname internally in the Docker Compose network, and is thus the hostname we need to pass to `mkcert`.

```sh
# Assuming you are in the services/tls directory
$ CAROOT="$PWD" mkcert <domain>
```

## Trusting the `mkcert` certificate authority

The file `rootCA.pem` is the file you need to copy into the relevant service's Docker image in order to trust the TLS connection from one service to another. This is only needed if the service consumes TLS connections from a service; you want to create a new cert if you're the service that needs to be TLS-protected.

Assuming you have copied `rootCA.pem` into the relevant services' build directory, then this pair of `Dockerfile` instructions like the following should suffice in order to trust the `mkcert` CA:

```dockerfile
COPY mkcert.pem /etc/ssl/certs
RUN update-ca-certificates --fresh
```
