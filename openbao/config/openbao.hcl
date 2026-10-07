ui = true

api_addr = "http://bao-server:8200"
cluster_addr = "http://bao-server:8201"

listener "tcp" {
  address     = "0.0.0.0:8200"
  tls_disable = true
}

storage "raft" {
  path = "/openbao/data"

  node_id = "bao-server"
}