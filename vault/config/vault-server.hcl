
api_addr                = "https://localhost:8200"
cluster_addr            = "https://localhost:8201"
cluster_name            = "learn-vault-cluster"
disable_mlock           = true
ui                      = true

listener "tcp" {
address       = "[::]:8200"
tls_disable   = "false"
tls_cert_file = "/vault/certs/vault.crt"
tls_key_file  = "/vault/certs/vault.key"
}

backend "raft" {
path    = "/vault/file"
node_id = "learn-vault-server"
}
