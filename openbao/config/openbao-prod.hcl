
ui = true

api_addr = "https://bao-server:8200"
cluster_addr = "https://bao-server:8201"

listener "tcp" {
  address       = "0.0.0.0:8200"
  
  tls_cert_file = "/etc/ssl/certs/vault.crt"
  tls_key_file  = "/etc/ssl/certs/vault.key"
}

storage "raft" {
  path = "/openbao/data"
  node_id = "bao-server"
}
