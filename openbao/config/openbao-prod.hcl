
ui = true

cluster_name = "openvre-openbao"

# Public/client address of this OpenBao node.
# This hostname MUST match the certificate SAN.
api_addr = "https://openbao.example.com:8200"

# Internal Raft cluster address.
cluster_addr = "https://172.21.0.18:8201"


listener "tcp" {
  address         = "0.0.0.0:8200"
  cluster_address = "0.0.0.0:8201"

  tls_cert_file = "/etc/ssl/certs/bao.crt"
  tls_key_file  = "/etc/ssl/certs/bao.key"

  tls_min_version = "tls13"
}


storage "raft" {
  path    = "/openbao/data"
  node_id = "bao-server"
}