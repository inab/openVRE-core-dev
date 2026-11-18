#!/bin/sh
# load-secrets.sh - in-container wrapper: turn X_FILE=/run/secrets/... into X.
#
# For every environment variable named <X>_FILE whose value is a path under
# /run/secrets/, export <X> with the file's content (trailing newlines are
# stripped, as the official images' file_env does), unset <X>_FILE, then
# exec "$@". Other *_FILE variables (e.g. KC_HTTPS_CERTIFICATE_FILE) are left
# alone. Exits non-zero if a file is missing/unreadable or <X> is already set.
#
# Mounted read-only at /usr/local/bin/load-secrets.sh and used as:
#   entrypoint: ["/bin/sh", "/usr/local/bin/load-secrets.sh", "<original entrypoint>"]
set -eu

_ls_path=""
for _ls_name in $(env | sed -n 's|^\([A-Za-z_][A-Za-z0-9_]*\)_FILE=/run/secrets/.*|\1|p'); do
    eval "_ls_path=\${${_ls_name}_FILE:-}"
    case "$_ls_path" in
        /run/secrets/*) ;;
        *) continue ;;
    esac
    if eval "[ -n \"\${${_ls_name}+x}\" ]"; then
        echo "load-secrets: both $_ls_name and ${_ls_name}_FILE are set" >&2
        exit 1
    fi
    if [ ! -f "$_ls_path" ] || [ ! -r "$_ls_path" ]; then
        echo "load-secrets: cannot read $_ls_path (for $_ls_name)" >&2
        exit 1
    fi
    _ls_value=$(cat "$_ls_path")
    export "$_ls_name=$_ls_value"
    unset "${_ls_name}_FILE"
done
unset _ls_name _ls_path _ls_value

exec "$@"
