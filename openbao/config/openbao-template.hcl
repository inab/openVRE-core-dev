ui = true

api_addr = "http://BAO_HOSTNAME:8200"
cluster_addr = "http://BAO_HOSTNAME:8201"

listener "tcp" {
  address     = "0.0.0.0:8200"
  tls_disable = true
}

storage "raft" {
  path = "/openbao/BAO_DATA"

  node_id = "BAO_HOSTNAME"
}