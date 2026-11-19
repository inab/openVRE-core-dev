FROM openbao/openbao:2.4.0
USER root
RUN apk add --no-cache jq
COPY openbao-dev-entrypoint.sh /usr/local/bin/openbao-dev-entrypoint.sh

RUN chmod +x /usr/local/bin/openbao-dev-entrypoint.sh

USER openbao

RUN export GENERATED_POLICY="/tmp/jwt-user-policies.hcl"
ENTRYPOINT ["/usr/local/bin/openbao-dev-entrypoint.sh"]