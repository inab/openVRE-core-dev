<?php
// Rendered by the global exception handler (config/bootstrap.php).
// Must be self-contained: globals or session may not be available.
// Expects $errorId and $errorMessage to be defined by the caller.

$baseUrl  = $GLOBALS['BASEURL'] ?? '/';
$siteName = $GLOBALS['SITETITLE'] ?? 'openVRE';
$owner    = $GLOBALS['NAME'] ?? '';
// Log out through mod_auth_openidc so the OIDC session and Keycloak SSO are cleared too,
// otherwise the next login silently reuses them
$logoutUrl = isset($GLOBALS['URL'], $GLOBALS['URL_logout'])
    ? $GLOBALS['URL'] . 'redirect_uri?logout=' . $GLOBALS['URL_logout']
    : 'redirect_uri?logout=' . $baseUrl . 'logout.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo htmlspecialchars($siteName); ?> - Error</title>
    <base href="<?php echo htmlspecialchars($baseUrl); ?>" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
    <link href="assets/global/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/global/css/components.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/pages/css/login.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/layouts/layout/css/custom.min.css" rel="stylesheet" type="text/css" />
    <link rel="icon" href="assets/layouts/layout/img/icon.png" sizes="32x32" />
</head>

<body class=" login">
    <div class="logo">
        <a href="">
            <img src="assets/layouts/layout/img/logo-big.png" alt="" /> </a>
    </div>
    <div class="content">
        <h3 class="form-title font-green">Oups...</h3>
        <p>Something went wrong while processing your request.</p>
        <?php if (!empty($errorMessage)) { ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php } ?>
        <p>If the problem persists, please contact the platform administrators and give them this reference:
            <code><?php echo htmlspecialchars($errorId); ?></code>
        </p>
        <p>
            <a href="workspace/" class="btn green">Go to workspace</a>
            <a href="<?php echo htmlspecialchars($logoutUrl); ?>" class="btn default">Log out</a>
        </p>
    </div>
    <div class=" copyright "> &copy; <?php echo date("Y") . " " . htmlspecialchars($owner); ?></div>
    <script>
        // Forget the failing page so home/redirect.php does not send the user back into it
        document.cookie = "currentPage=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
    </script>
</body>

</html>
