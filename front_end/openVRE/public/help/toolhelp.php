<?php

require __DIR__ . "/../../config/bootstrap.php";
redirectOutside();


// get help section
$help = (isset($_REQUEST['sec']) ? $_REQUEST['sec'] : "help");
// get tool id
if (! isset($_REQUEST['tool'])) {
	$_SESSION['errorData']['error'][] = "Cannot find hep page. 'tool' parameter not received";
	redirect($GLOBALS['BASEURL'] . "help/tools.php");
}
$tool = $_REQUEST['tool'];


// fetch user
$user = getUserById($_SESSION['User']['_id']);
if (isset($user["ToolsDev"])) $tdev = $user["ToolsDev"];
else $tdev = array();

if ((isset($_SESSION['User'])
		&& ($user['Status'] == UserStatus::Active->value)
		&& (allowedRoles($user['Type'], $GLOBALS['TOOLDEV']))
		&& (in_array($tool, $tdev))) ||
	(isset($_SESSION['User'])
		&& ($user['Status'] == UserStatus::Active->value)
		&& ($user['Type'] == UserType::Admin->value)
	)
) $developer = true;
else $developer = false;

// fetch help from db

$Parsedown = new Parsedown();
$Parsedown->setBreaksEnabled(true);

$page            = $GLOBALS['helpsCol']->findOne(array('tool' => $tool, 'help' => $help));
$markdowncontent = $page['content'];
$htmlcontent     = $Parsedown->text($markdowncontent);

// fetch tool object

$toolData = $GLOBALS['toolsCol']->findOne(array('_id' => $tool));

?>

<?php require "../htmlib/header.inc.php"; ?>

<body class="page-header-fixed page-sidebar-closed-hide-logo page-content-white page-container-bg-solid page-sidebar-fixed">
	<div class="page-wrapper">

		<?php require "../htmlib/top.inc.php"; ?>
		<?php require "../htmlib/menu.inc.php"; ?>

		<!-- BEGIN CONTENT -->
		<div class="page-content-wrapper">
			<!-- BEGIN CONTENT BODY -->
			<div class="page-content" id="body-help">
				<!-- BEGIN PAGE HEADER-->
				<!-- BEGIN PAGE BAR -->
				<div class="page-bar">
					<ul class="page-breadcrumb">
						<li>
							<a href="/home/">Home</a>
							<i class="fa fa-circle"></i>
						</li>
						<li>
							<span>Help</span>
							<i class="fa fa-circle"></i>
						</li>
						<li>
							<a href="help/tools.php">Tools</a>
							<i class="fa fa-circle"></i>
						</li>
						<li>
							<a href="help/toolhelp.php?tool=<?php echo $toolData["_id"]; ?>&sec=help"><?php echo $toolData["name"]; ?></a>
						</li>
					</ul>
				</div>
				<!-- END PAGE BAR -->

				<!-- BEGIN PAGE TITLE-->
				<h1 class="page-title"> <span id="tit-static"><?php echo $page['title']; ?></span>
					<?php if ($developer) { ?>
						<input type="text" value="<?php echo $page['title']; ?>" id="input-tit" style="display:none;width:100%;" />
						<button type="button" id="bt-edit" class="btn green" style="margin-left:20px;">EDIT PAGE</button>
					<?php } ?>
				</h1>
				<!-- END PAGE TITLE-->

				<!-- END PAGE HEADER-->

				<div id="html-content-help"><?php echo $htmlcontent; ?></div>
				<input type="hidden" value="<?php echo $developer; ?>" id="developer" />
				<input type="hidden" id="base-url" value="<?php echo $GLOBALS['BASEURL']; ?>" />

				<?php if ($developer) { ?>
					<form id="help-content" action="javascript:;" style="display:none;">
						<input type="hidden" value="<?php echo $page['title']; ?>" name="title" id="title" />
						<input type="hidden" value="<?php echo $help; ?>" name="help" id="help" />
						<input type="hidden" value="<?php echo $tool; ?>" name="tool" id="tool" />
						<textarea name="content" id="editor"><?php echo $markdowncontent; ?></textarea>
						<button type="submit" class="btn green" style="margin-top:10px;">SAVE</button>
						<button type="button" id="cancel-edit" class="btn default" style="margin:10px 0 0 5px;">CANCEL</button>
					</form>
				<?php } ?>
			</div>
			<!-- END CONTENT BODY -->
		</div>
		<!-- END CONTENT -->



		<?php

		require "../htmlib/footer.inc.php";
		require "../htmlib/js.inc.php";

		?>