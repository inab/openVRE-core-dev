<?php
require __DIR__ . "/../../config/bootstrap.php";

/*
 * User impersonation is NOT IMPLEMENTED.
 *
 * loadUser() only checks that the target user exists; it never switches the
 * session, so the admin stays logged in as themselves.
 *
 * Swapping $_SESSION['userId'] / 'internalUserId' / 'userType' would be easy,
 * but the session would keep the admin's OIDC token ($_SESSION['userToken'],
 * 'tokenInfo', 'allowedDatasetIds'). Since OpenBao policies are scoped to the
 * JWT identity, everything backed by Vault (EGA, linked accounts, Swift, HPC
 * and EGA jobs) would fail, and GA4GH dataset visas would be the admin's ones.
 *
 * A proper implementation needs Keycloak token exchange (impersonation) to get
 * a token for the target user, plus: storing the impersonator id to switch
 * back, session_regenerate_id(), a POST + CSRF-protected endpoint, a visible
 * banner while impersonating and audit logging of the actions performed.
 */

if (checkLoggedIn() && !checkAdmin()) {
    $_SESSION['errorData']['Error'][] = "Cannot impersonate a user. Permission denied.";
    die(0);
}

$_SESSION['errorData']['Error'][] = "User impersonation is not implemented.";
redirect("../home/redirect.php");
