import base64
from Crypto.PublicKey import RSA

# Base64url decode the modulus (n) and exponent (e)
n = "rcD2l0j_wrI0JLYjzDHq4LAQJx-pVFaPwfoAYQ-BUSQRaLpcMB7gNqBrUlM1768lREWr0AuY4565Xd3zZlwHYZZbVowz0kjt0xnhIOIJ9UTOLcAFKNliBWihs5UcZ5Cyw5dGfl1TLgKxYV8Eojdtf-7mTy6vZQfU-Je2zCtvy-PLu6Zr6yK4GJftMwbd3jLX143d9-OeiAjcHAzIbeUlNn1qh2UXNfDR7sHRE6BkFcKX1K00YbQTGVz9P2QuL0Xx3IwXlPnrkY3D8t3rHMyft35pdeRw5tZ6qXuKt-knyu2iogLF9qx9njL0oFxKYsjAW8r_u5s5tuVBrbvNwwMKKw"
e = "AQAB"

# Convert base64url to base64
n = base64.urlsafe_b64decode(n + '==')
e = base64.urlsafe_b64decode(e + '==')

# Create RSA key
rsa_key = RSA.construct((int.from_bytes(n, 'big'), int.from_bytes(e, 'big')))
pem_key = rsa_key.exportKey()

print(pem_key.decode())
