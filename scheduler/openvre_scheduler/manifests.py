import json
from dataclasses import dataclass, field

from . import validation as v

USERDATA_VOLUME = "userdata"
ADMINDATA_VOLUME = "admindata"
AUDIT_CONTAINER = "audit-sidecar"
# Fixed path: a tool's own path (e.g. under /root) may not be reachable in the sidecar image.
AUDIT_WORKSPACE = "/audit-workspace"
# VRE protected data from third parties (DEFAULT_PROTECTED_PVC), at <prefix>/public: tools only ever read it.
PROTECTED_VOLUME = "protected"
PUBLIC_DIR = "public"
USERDATA_DIR = "userdata"
INTERACTIVE_LABEL = "openvre.io/interactive"
SESSION_LABEL = "openvre.io/session"
TEMPLATE_LABEL = "openvre.io/template"
MANAGED_BY = "openvre-scheduler"
USER_LABEL = "openvre.io/user"
SESSION_ANNOTATION = "openvre.io/session-id"
USER_ANNOTATION = "openvre.io/user-id"
CREATED_ANNOTATION = "openvre.io/created-at"
EXPIRES_ANNOTATION = "openvre.io/expires-at"
AUDIT_ANNOTATION = "openvre.io/audit-host-path"
TEMPLATE_VERSION_ANNOTATION = "openvre.io/template-version"
# On a session's Service: the openvre-gateway (Traefik) routes this path to it,
# adding these request headers (scheduler /gateway/routes reads them back).
GATEWAY_PATH_ANNOTATION = "openvre.io/gateway-path"
GATEWAY_HEADERS_ANNOTATION = "openvre.io/gateway-headers"
# On the session Deployment: the kinds the session created, so a stop only looks those up.
KINDS_ANNOTATION = "openvre.io/kinds"

# RStudio writes lots of session metadata under home; keep user files, drop internals.
DEFAULT_AUDIT_EXCLUDE_PREFIXES = ",".join([
    "sessions", "sources", "client-state", "monitored", "pcs", ".local", ".config",
    ".cache", ".rstudio", "bibliography-index", "presentation", "profiles-cache",
    "projects", "projects_settings", "rversion-settings", "tutorial", "ctx", "notebooks",
])

API_PATHS = {
    "ConfigMap": "/api/v1/namespaces/{ns}/configmaps",
    "Deployment": "/apis/apps/v1/namespaces/{ns}/deployments",
    "Service": "/api/v1/namespaces/{ns}/services",
}


@dataclass
class SessionSpec:
    """One validated session request: the frontend's context plus scheduler-chosen values."""
    name: str
    namespace: str
    session_id: str
    user_id: str
    tool_id: str
    tool_name: str
    project: str
    execution: str
    working_dir: str
    workspace_mount_path: str
    volume_mounts: list
    path_prefix: str
    external_base: str
    template: str
    template_version: str
    access_type: str
    image: str = ""
    port: int = 0
    created_at: str = ""
    expires_at: str = ""
    audit_host_path: str = ""
    access: dict = field(default_factory=dict)
    # Set from the verified user token (layer 2), empty when user tokens are off.
    owner: str = ""
    owner_sub: str = ""
    owner_name: str = ""
    owner_email: str = ""


# Node placement (shared with the batch Job template via {{ config.* }}) -------

def parse_node_selector(raw):
    out = {}
    for part in (raw or "").split(","):
        part = part.strip()
        if "=" not in part:
            continue
        k, val = (x.strip() for x in part.split("=", 1))
        if k:
            out[k] = val
    return out


def parse_tolerations(raw):
    out = []
    for part in (raw or "").split(","):
        part = part.strip()
        if not part:
            continue
        effect = "NoSchedule"
        body = part
        if ":" in part:
            body, effect = part.rsplit(":", 1)
            effect = effect.strip() or "NoSchedule"
        if "=" in body:
            key, value = body.split("=", 1)
            out.append({"key": key.strip(), "operator": "Equal", "value": value.strip(), "effect": effect})
        else:
            out.append({"key": body.strip(), "operator": "Exists", "effect": effect})
    return out


def tolerations(settings):
    eviction = [
        {"key": k, "operator": "Exists", "effect": "NoExecute", "tolerationSeconds": settings.eviction_toleration_seconds}
        for k in ("node.kubernetes.io/not-ready", "node.kubernetes.io/unreachable")
    ]
    return parse_tolerations(settings.tolerations) + eviction


def affinity(settings):
    """Prefer one node while it is Ready (OPENVRE_PREFERRED_HOSTNAME); None: no affinity."""
    if not settings.preferred_hostname:
        return None
    return {"nodeAffinity": {"preferredDuringSchedulingIgnoredDuringExecution": [{
        "weight": 100,
        "preference": {"matchExpressions": [{
            "key": "kubernetes.io/hostname", "operator": "In", "values": [settings.preferred_hostname]}]},
    }]}}


# Session building blocks ----------------------------------------------------

OWNER_LABEL = "openvre.io/owner"


def labels(spec):
    out = {
        "app": spec.name,
        INTERACTIVE_LABEL: "true",
        "app.kubernetes.io/managed-by": MANAGED_BY,
        SESSION_LABEL: spec.name,
        USER_LABEL: v.label_value(spec.user_id),
        TEMPLATE_LABEL: spec.template,
    }
    if spec.owner:
        out[OWNER_LABEL] = spec.owner
    return out


def annotations(spec):
    out = {
        SESSION_ANNOTATION: spec.session_id,
        USER_ANNOTATION: spec.user_id,
        CREATED_ANNOTATION: spec.created_at,
        TEMPLATE_VERSION_ANNOTATION: spec.template_version,
    }
    if spec.expires_at:
        out[EXPIRES_ANNOTATION] = spec.expires_at
    if spec.audit_host_path:
        out[AUDIT_ANNOTATION] = spec.audit_host_path
    return out


def protected_mounts(settings):
    """The protected-data PVC at <prefix>/public, always read-only. Empty when DEFAULT_PROTECTED_PVC is not set."""
    if not settings.protected_pvc:
        return []
    return [{"name": PROTECTED_VOLUME, "mountPath": f"{settings.shared_data_prefix.rstrip('/')}/{PUBLIC_DIR}",
             "readOnly": True}]


def protected_volumes(settings):
    if not settings.protected_pvc:
        return []
    return [{"name": PROTECTED_VOLUME, "persistentVolumeClaim": {"claimName": settings.protected_pvc, "readOnly": True}}]


def user_folder(path, settings):
    """'userdata/<user>' for a path under <prefix>/userdata/<user>/..., which is all a batch Job mounts."""
    rel = v.subpath(v.path_under(path, f"{settings.shared_data_prefix.rstrip('/')}/{USERDATA_DIR}", "workdir"),
                    settings.shared_data_prefix).split("/")
    v.require(len(rel) >= 3, f"workdir must be inside a user's folder: <prefix>/{USERDATA_DIR}/<user>/...")
    return f"{rel[0]}/{rel[1]}"


def userdata_mounts(spec, settings):
    """Mounts of the userData PVC (volume "userdata"): the tool's folders, plus
    the protected-data PVC read-only. Paths were checked in sessions.py."""
    mounts, seen = [], set()
    for vm in spec.volume_mounts:
        sub = v.subpath(vm["host_path"], settings.shared_data_prefix)
        key = (sub, vm["container_path"])
        if key in seen:
            continue
        seen.add(key)
        m = {"name": USERDATA_VOLUME, "mountPath": vm["container_path"]}
        if sub:
            m["subPath"] = sub
        if vm.get("read_only"):
            m["readOnly"] = True
        mounts.append(m)
    if not mounts:
        sub = v.subpath(spec.working_dir, settings.shared_data_prefix)
        m = {"name": USERDATA_VOLUME, "mountPath": spec.workspace_mount_path}
        if sub:
            m["subPath"] = sub
        mounts.append(m)
    taken = {m["mountPath"] for m in mounts}
    return mounts + [m for m in protected_mounts(settings) if m["mountPath"] not in taken]


def readiness_probe(port, path):
    """'Running' should mean the app answers, not just that the process started."""
    handler = {"httpGet": {"path": path, "port": port}} if path else {"tcpSocket": {"port": port}}
    return {**handler, "initialDelaySeconds": 3, "periodSeconds": 5, "timeoutSeconds": 3, "failureThreshold": 3}


def audit_blocks(spec, settings, mounts):
    """(containers, volumes) for the audit sidecar, or ([], []) when audit is off."""
    if not (settings.audit_enabled and spec.audit_host_path):
        return [], []
    admin_pvc = settings.admindata_pvc or settings.userdata_pvc
    separate_admin_pvc = admin_pvc != settings.userdata_pvc
    prefix = settings.audit_base if separate_admin_pvc else settings.shared_data_prefix
    sub = v.subpath(spec.audit_host_path, prefix)
    if not sub:
        raise ValueError("audit session subPath is empty")
    watched = next((m for m in mounts if m["mountPath"] == spec.workspace_mount_path), mounts[0])
    container = {
        "name": AUDIT_CONTAINER,
        "image": settings.audit_image,
        "env": [
            {"name": "WATCH_PATH", "value": AUDIT_WORKSPACE},
            {"name": "WORKING_DIR", "value": spec.working_dir},
            {"name": "EXECUTION_ID", "value": spec.name},
            {"name": "SESSION_ID", "value": spec.session_id},
            {"name": "USER_ID", "value": spec.user_id},
            {"name": "INTERVAL_SEC", "value": settings.audit_interval_sec},
            {"name": "EXCLUDE_REL_PREFIXES", "value": DEFAULT_AUDIT_EXCLUDE_PREFIXES},
        ],
        # The workspace read-only (watch) + adminData read-write (audit files). The tool container never sees adminData.
        "volumeMounts": [
            {**watched, "mountPath": AUDIT_WORKSPACE, "readOnly": True},
            {"name": ADMINDATA_VOLUME, "mountPath": "/audit-root", "subPath": sub},
        ],
        "securityContext": {
            "runAsNonRoot": True,
            "runAsUser": settings.audit_run_as_uid,
            "runAsGroup": settings.audit_run_as_gid,
            "allowPrivilegeEscalation": False,
            "capabilities": {"drop": ["ALL"]},
        },
        "resources": {"requests": {"cpu": "10m", "memory": "64Mi"}, "limits": {"cpu": "200m", "memory": "256Mi"}},
    }
    volume = {"name": ADMINDATA_VOLUME, "persistentVolumeClaim": {"claimName": admin_pvc}}
    return [container], [volume]


def proxy_headers(spec, settings, values):
    path = spec.path_prefix.rstrip("/")
    proto = "https" if settings.external_base.startswith("https://") else "http"
    data = {"X-Forwarded-Proto": proto}
    mode = values.get("proxy_root_mode", "")
    if mode == "x_forwarded_prefix":
        data["X-Forwarded-Prefix"] = path
    elif mode == "custom_header" and values.get("proxy_root_header"):
        data[values["proxy_root_header"]] = path
    for k, val in (values.get("proxy_headers") or {}).items():
        data[k] = val.replace("{{PATH_PREFIX}}", path)
    return data


def gateway_annotations(spec, settings, values):
    """Annotations on the session Service that tell the gateway how to route it."""
    return {
        GATEWAY_PATH_ANNOTATION: spec.path_prefix.rstrip("/"),
        GATEWAY_HEADERS_ANNOTATION: json.dumps(proxy_headers(spec, settings, values), sort_keys=True),
    }


def building_blocks(spec, settings, values):
    """Every {{ session.* }} value for one session."""
    mounts = userdata_mounts(spec, settings)
    audit_containers, audit_volumes = audit_blocks(spec, settings, mounts)
    executable = values.get("executable", "")
    pull = [{"name": n} for n in settings.image_pull_secrets]
    pull += [s for s in (values.get("image_pull_secrets") or []) if s["name"] not in settings.image_pull_secrets]
    lbl = labels(spec)
    return {
        "name": spec.name,
        "path": spec.path_prefix,
        "url": spec.path_prefix + "/",
        "namespace": spec.namespace,
        "user_id": spec.user_id,
        "labels": lbl,
        "pod_labels": dict(lbl),
        "annotations": annotations(spec),
        "mounts": mounts,
        "audit_containers": audit_containers,
        "audit_volumes": audit_volumes,
        "readiness_probe": readiness_probe(values["port"], values.get("readiness_path", "")),
        "command": ["sh", "-lc", executable] if executable else None,
        "pull_secrets": pull,
        "gateway_annotations": gateway_annotations(spec, settings, values),
    }
