<?php
require __DIR__ . "/../../config/bootstrap.php";
redirectOutside();

$siteId = $_GET['site'] ?? null;

if (!$siteId) {
    throw new Exception("No data archive site specified.");
}

$site = $GLOBALS['sitesCol']->findOne([
    '_id' => $siteId,
    'type' => 1
]);

if (!$site) {
    throw new Exception("Data archive site not found.");
}

?>

<?php require "../htmlib/header.inc.php"; ?>

<body class="page-header-fixed page-sidebar-closed-hide-logo page-content-white page-container-bg-solid page-sidebar-fixed">
  <div class="page-wrapper">

    <?php require "../htmlib/top.inc.php"; ?>
    <?php require "../htmlib/menu.inc.php"; ?>

    <!-- BEGIN CONTENT -->
    <div class="page-content-wrapper">
      <!-- BEGIN CONTENT BODY -->
      <div class="page-content">

        <!-- BEGIN PAGE HEADER-->
        <!-- BEGIN PAGE BAR -->
        <div class="page-bar">
          <ul class="page-breadcrumb">
            <li>
              <a href="home/">Home</a>
              <i class="fa fa-circle"></i>
            </li>
            <li>
              <span>Get Data</span>
              <i class="fa fa-circle"></i>
            </li>
            <li>
              <span>From Storage</span>
              <i class="fa fa-circle"></i>
            </li>
            <li>
              <span><?php echo htmlspecialchars($site['name'] ?? 'Storage Site'); ?></span>
            </li>
          </ul>
        </div>
        <!-- END PAGE BAR -->
        <!-- BEGIN PAGE TITLE-->
        <h1 class="page-title">
          <a href="javascript:;" target="_blank"><img src="assets/layouts/layout/img/icon.png" width=100></a>
          Storage Browser - <?php echo htmlspecialchars($site['name'] ?? 'Container'); ?>
        </h1>
        <!-- END PAGE TITLE-->
        <!-- END PAGE HEADER-->

        <?php
        if (isset($_SESSION['errorData'])) {
          foreach ($_SESSION['errorData'] as $subTitle => $txts) {
            if (count($txts) == 0) {
              unset($_SESSION['errorData'][$subTitle]);
            }
          }
        }
        if (isset($_SESSION['errorData']) && $_SESSION['errorData']) {
          if (isset($_SESSION['errorData']['Info'])) {
            ?><div class="alert alert-info"><?php
          } else {
            ?><div class="alert alert-warning"><?php
          }
          foreach ($_SESSION['errorData'] as $subTitle => $txts) {
            print "$subTitle<br/>";
            foreach ($txts as $txt) {
              print "<div style=\"margin-left:20px;\">$txt</div>";
            }
          }
          unset($_SESSION['errorData']);
          ?></div><?php
        }
        ?>

        <div class="row">
          <div class="col-md-12">
            <div class="portlet light portlet-fit bordered">
              <div class="portlet-title">
                <div class="caption">
                  <i class="icon-share font-red-sunglo hide"></i>
                  <span class="caption-subject font-dark bold uppercase">Browse Storage Containers</span>
                </div>
              </div>

              <div class="row storage-toolbar" style="margin: 0 15px 15px 15px;">
                <div class="col-md-6">
                  <label class="storage-select-label">Bucket</label>
                  <select id="containerDropdown" class="form-control storage-select" style="display:none;"></select>
                </div>
              </div>

              <div class="table-responsive storage-table-wrap">
                <table class="table table-striped table-hover table-bordered storage-tree-table">
                  <thead>
                    <tr>
                      <th class="storage-name-col">Filename</th>
                      <th class="storage-size-col">Size</th>
                      <th class="storage-actions-col">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="repository-body">
                    <tr>
                      <td colspan="3">Loading...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <!-- END PAGE CONTENT -->

      </div>
      <!-- END CONTENT BODY -->
    </div>
    <!-- END CONTENT WRAPPER -->

  </div>
  <!-- END PAGE WRAPPER -->

  <!-- VIEW JSON PART -->
  <div class="modal fade bs-modal" id="modalAnalysis" tabindex="-1" role="basic" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
          <h4 class="modal-title">Execution Summary</h4>
        </div>
        <div class="modal-body table-responsive"></div>
        <div class="modal-footer">
          <button type="button" class="btn dark btn-outline" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <style type="text/css">
    .storage-toolbar {
      margin-top: 10px;
    }

    .storage-select-label {
      display: block;
      margin-bottom: 6px;
      font-size: 12px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #5b6471;
      font-weight: 600;
    }

    .storage-select {
      width: 100%;
      border: 1px solid #dfe3e8;
      border-radius: 6px;
      background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
      box-shadow: inset 0 1px 2px rgba(16, 24, 40, 0.03);
      color: #1f2937;
      padding: 8px 12px;
      min-height: 40px;
    }

    .storage-table-wrap {
      border: 1px solid #edf1f5;
      border-radius: 8px;
      overflow: hidden;
      background: #fff;
    }

    .storage-tree-table {
      margin-bottom: 0;
    }

    .storage-tree-table thead > tr > th {
      background: #f8fafc;
      color: #4b5563;
      font-weight: 600;
      padding: 12px 14px;
      border-bottom: 1px solid #edf1f5;
    }

    .storage-name-col {
      width: 60%;
    }

    .storage-size-col {
      width: 18%;
    }

    .storage-actions-col {
      width: 12%;
      text-align: center;
    }

    #repository-body td:first-child {
      padding-left: 12px;
    }

    #repository-body td {
      padding-top: 10px;
      padding-bottom: 10px;
      vertical-align: middle;
      font-size: 13px;
      border-color: #edf1f5;
    }

    #repository-body tr:hover {
      background-color: #f8fafc;
    }

    #repository-body .dir-row td:first-child,
    #repository-body .file-row td:first-child {
      display: flex;
      align-items: center;
      gap: 8px;
      min-height: 32px;
    }

    .folder-icon {
      color: #f0ad4e;
      font-size: 15px;
      min-width: 14px;
    }

    .file-icon {
      color: #4c87b9;
      font-size: 14px;
      min-width: 14px;
    }

    .folder-name,
    .file-row td:first-child span:last-child {
      display: inline-block;
      line-height: 1.4;
      color: #1f2937;
    }

    .folder-toggle {
      border: 1px solid #dfe3e8;
      background: #fff;
      border-radius: 4px;
      width: 22px;
      height: 22px;
      padding: 0;
      margin-right: 4px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #6b7280;
      box-shadow: none;
    }

    .folder-toggle i {
      transition: transform .15s ease-in-out;
      font-size: 12px;
    }

    .folder-toggle.open i {
      transform: rotate(90deg);
    }

    .folder-toggle[disabled] {
      opacity: 0.35;
      cursor: default;
    }

    .dir-action-btn {
      padding: 0 5px;
      font-size: 10px;
      line-height: 1;
      min-width: 22px;
      height: 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
      color: #667085;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
    }

    .dir-action-btn[disabled] {
      opacity: 0.55;
      cursor: default;
    }

    .actions-container {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 6px;
    }

    .storage-size-cell {
      white-space: nowrap;
      color: #5f6c7b;
    }

    .file-indent {
      width: 14px;
      display: inline-block;
    }
  </style>

  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script>
  // Inject session variables from PHP into JS
  window.currentUserId = "<?php echo $_SESSION['User']['id']; ?>";
</script>
<script src="/assets/pages/scripts/openstack.js"></script>
<script>
window.objStorageConfig = {
    site: <?php echo json_encode((string)$siteId); ?>,
    url: '<?php echo $GLOBALS['BASEURL']; ?>applib/objStorage.php'
};
window.objectStorageSite = window.objStorageConfig.site;
</script>

  <?php
  require "../htmlib/footer.inc.php";
  require "../htmlib/js.inc.php";
  ?>
