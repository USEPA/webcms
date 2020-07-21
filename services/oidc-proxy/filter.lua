--[[
  ABOUT

  This filter modifies the contents of the OIDC discovery document sent back by the mock
  OIDC server in order to comply with our SimpleSAMLphp OpenID module's expectations.
  Specifically, the module _requires_ that all OIDC endpoints are behind TLS, not just the
  initial conversation with the issuer.

  As a result, we must modify the discovery document such that all of the URLs are behind
  this TLS proxy.

  An OIDC discovery document (see links below) is a JSON object that describe how to
  connect to the given OIDC identity provider. We don't include any hardcoded list of
  keys to rewrite as it may change from version to version of the OIDC mock server;
  instead we assume that any top-level "<key>": "<url>" pair is in need of rewriting to
  use TLS.

  There is a single exception to the above rule: the key "authorization_endpoint" is the
  end user's login link, and as a result, it must instead be rewritten to use the forwarded
  port from docker-compose.yml.

  USEFUL LINKS

  - Swagger.io on the discovery document:
    https://swagger.io/docs/specification/authentication/openid-connect-discovery/
  - The openid.net spec on discovery:
    https://openid.net/specs/openid-connect-discovery-1_0.html
]]

local cjson = require("cjson")

local function replace()
  -- Begin by decoding the discovery document
  body = cjson.decode(ngx.arg[1])

  -- Force the issuer to match the value in SimpleSAMLphp's authsources.php.
  body.issuer = "https://oidc.localhost:4443/"

  -- Walk the discovery document, replacing all top-level
  for key, value in pairs(body) do
    if type(value) == "string" then
      -- Replace the "http://localhost" prefix with the oidc.localhost alias: for some
      -- reason the OIDC server doesn't read the Host header (or we're not sending it
      -- correctly) and assumes it's being accessed as localhost.
      body[key] = string.gsub(value, "^http://localhost", "https://oidc.localhost:4443")
    end
  end

  ngx.arg[1] = cjson.encode(body)
end

local function handler(err)
  ngx.log(ngx.WARN, err)
end

xpcall(replace, handler)
