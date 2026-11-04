#!/bin/bash

EGA_ENCRYPTED_FILES_MOUNTINGPOINT="/encrypted_files"
EGA_CLEAN_FILES_MOUNTINGPOINT="/clean_files"
EGA_OUTBOX_ENDPOINT="outbox.spain.ega-archive.org"
user_private_key_file="/tmp/ega_secret_key"

# Use the OpenBao CA certificate only if it was mounted into the container
BAO_CACERT_FILE="/tmp/ca.crt"
CURL_BAO_OPTS=(-sS --fail --header "X-Vault-Token: $BAO_TOKEN")
if [[ -f "$BAO_CACERT_FILE" && -s "$BAO_CACERT_FILE" ]]; then
        CURL_BAO_OPTS+=(--cacert "$BAO_CACERT_FILE")
fi

# ---- 1. Read EGA credentials from OpenBao (single request) ----
echo "Reading EGA secrets from OpenBao: $BAO_ADDR"
EGA_SECRET=$(curl "${CURL_BAO_OPTS[@]}" "$BAO_ADDR") || {
        echo "ERROR: cannot read EGA secrets from OpenBao"
        exit 1
}

EGA_USERNAME=$(jq -r '.data.data.EGA.username // empty' <<< "$EGA_SECRET")
USER_PRIVATE_KEY=$(jq -r '.data.data.EGA.privateKey // empty' <<< "$EGA_SECRET")
unset EGA_SECRET

[[ -n "$EGA_USERNAME" ]] || { echo "ERROR: EGA username not found in OpenBao secret"; exit 1; }
[[ -n "$USER_PRIVATE_KEY" ]] || { echo "ERROR: EGA private key not found in OpenBao secret"; exit 1; }
echo "EGA username: $EGA_USERNAME"

# ---- 2. Write and check the private key ----
# Whitespace is removed first: `base64` wraps lines at 76 chars, and pasting them
# into the single-line form field can turn the line breaks into spaces
printf '%s' "$USER_PRIVATE_KEY" | tr -d '[:space:]' | base64 --decode > "$user_private_key_file" 2>/dev/null || {
        echo "ERROR: EGA private key is not valid base64 (store it with: base64 -w0 ~/.ssh/id_ed25519)"
        exit 1
}
unset USER_PRIVATE_KEY
chmod 600 "$user_private_key_file"

# Only the key header is printed, never the key itself
echo "Private key header: $(head -1 "$user_private_key_file")"
if ssh-keygen -y -P "" -f "$user_private_key_file" > /dev/null 2>&1; then
        echo "Private key is a valid SSH key (no passphrase)"
else
        echo "ERROR: private key is not a valid SSH private key, or it has a passphrase"
        exit 1
fi

# ---- 3. Mount the encrypted EGA outbox with sshfs ----
USER_ID=$(id -u)
GROUP_ID=$(id -g)
echo -e "\nMounting remote EGA OUTBOX ($EGA_USERNAME@$EGA_OUTBOX_ENDPOINT) as uid=$USER_ID gid=$GROUP_ID..."

sshfs -p 2233 -o reconnect -o BatchMode=yes -o IdentityFile="$user_private_key_file" -o IdentitiesOnly=yes \
        -o allow_other -o default_permissions -o uid=$USER_ID -o gid=$GROUP_ID \
        -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
        "$EGA_USERNAME"@"$EGA_OUTBOX_ENDPOINT":./ "$EGA_ENCRYPTED_FILES_MOUNTINGPOINT" || {
        echo "ERROR: sshfs failed (exit code $?)"
        exit 1
}

if mountpoint -q "$EGA_ENCRYPTED_FILES_MOUNTINGPOINT"; then
        echo "$EGA_ENCRYPTED_FILES_MOUNTINGPOINT is mounted"
else
        echo "ERROR: $EGA_ENCRYPTED_FILES_MOUNTINGPOINT is not a mount point"
        exit 1
fi

echo "list $EGA_ENCRYPTED_FILES_MOUNTINGPOINT"
ls -al "$EGA_ENCRYPTED_FILES_MOUNTINGPOINT"

# ---- 4. Decrypt the outbox into /clean_files with crypt4ghfs ----
echo -e "\nSetting up crypt4ghfs..."

cryptfs_conf="/tmp/fs.conf"

cat > $cryptfs_conf <<EOL
[DEFAULT]
rootdir=${EGA_ENCRYPTED_FILES_MOUNTINGPOINT}
# Never use DEBUG: crypt4gh then prints the secret key in the log
log_level=INFO
include_crypt4gh_log=no
[CRYPT4GH]
seckey=${user_private_key_file}
[FUSE]
options=allow_other,default_permissions
EOL

chmod 600 $cryptfs_conf

LOG="/tmp/crypt4ghfs_log.txt"
touch "$LOG"

crypt4ghfs -f --conf "$cryptfs_conf" "$EGA_CLEAN_FILES_MOUNTINGPOINT" >> "$LOG" 2>&1 < /dev/null & disown

# /clean_files is already a tmpfs mount point (docker --tmpfs), so check
# that the top filesystem mounted on it is the crypt4ghfs FUSE mount.
# The mount table is read instead of calling statfs (stat -f, df), because
# statfs crashes crypt4ghfs v1.2.6 ('Crypt4ghFS' object has no attribute 'rootfd')
is_fuse_mounted() {
        local fstype
        fstype=$(awk -v m="$1" '$2 == m { t = $3 } END { print t }' /proc/self/mounts)
        [[ "$fstype" == fuse* ]]
}

# Wait up to 10 seconds for the mount
for _ in $(seq 1 10); do
        is_fuse_mounted "$EGA_CLEAN_FILES_MOUNTINGPOINT" && break
        sleep 1
done

if is_fuse_mounted "$EGA_CLEAN_FILES_MOUNTINGPOINT"; then
        echo "$EGA_CLEAN_FILES_MOUNTINGPOINT is mounted (crypt4ghfs)"
else
        echo "ERROR: $EGA_CLEAN_FILES_MOUNTINGPOINT is not a mount point. crypt4ghfs log:"
        # Filter key material, just in case
        grep -vE "Key blob|sk:|pk:" "$LOG"
        exit 2
fi

echo "list $EGA_CLEAN_FILES_MOUNTINGPOINT"
ls -la "$EGA_CLEAN_FILES_MOUNTINGPOINT"
