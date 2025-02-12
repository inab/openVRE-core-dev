<?php


require __DIR__."/../../../config/bootstrap.php";
redirectOutside();

$inPaths = array();
$formats = array();

if (!is_array($_REQUEST['fn']))
	$_REQUEST['fn'][]=$_REQUEST['fn'];

foreach($_REQUEST['fn'] as $fn){
	$file['path'] = getAttr_fromGSFileId($fn,'path');
	$file['data_type'] = getAttr_fromGSFileId($fn,'data_type');
	$file['file_type'] = getAttr_fromGSFileId($fn,'file_type');

	if(!$file['path']) {
		$_SESSION['errorData']['VTK'][] = "ERROR: one of the requested files don't exist in our Data Base";
		header('Location: '.$GLOBALS['BASEURL'].'visualizers/error.php');
	}

	//$_SESSION['errorData']['VTK'][] = var_dump($file); 
	if(($file['file_type'] != 'OBJ') && ($file['file_type'] != 'VTK') && ($file['file_type'] != 'DCD')) {
		
		$_SESSION['errorData']['VTK'][] = "ERROR: incorrect format for VTK Viewer. Accepted file types are VTK, OBJ and DCD for trajectories.";
		header('Location: '.$GLOBALS['BASEURL'].'visualizers/error.php');
		//die();
		//echo "<script>window.close();</script>";
	}

	$file['fn'] = $fn;
	array_push($inPaths,$file);

	array_push($formats,$file['file_type']);
}

$vis_type = null;

if((count($_REQUEST['fn']) == 1) && (in_array('OBJ', $formats) || in_array('VTK', $formats))){
	$vis_type = 'single';
}

if((count($_REQUEST['fn']) > 1) && ((in_array('OBJ', $formats) || in_array('VTK', $formats)) && !in_array('DCD', $formats))){
	$vis_type = 'multiple';
}

if((count($_REQUEST['fn']) == 2) && ((in_array('OBJ', $formats) || in_array('VTK', $formats)) && in_array('DCD', $formats))){
	$vis_type = 'trajectory';
	
	$arr_traj = array();
	foreach($inPaths as $f) {
		if($f['file_type'] != 'DCD') $arr_traj[0] = $f;
		else $arr_traj[1] = $f;
	}

}

if((count($_REQUEST['fn']) > 2) && ((in_array('OBJ', $formats) || in_array('VKT', $formats)) && in_array('DCD', $formats))){
	$_SESSION['errorData']['NGL'][] = "ERROR: if you provide a trajectory, please provide a PDB or GRO file.";
	header('Location: '.$GLOBALS['BASEURL'].'visualizers/error.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Multiscale Simulations | Virtual Research Environment</title>
		<base href="<?php echo $GLOBALS['BASEURL']; ?>" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="stylesheet" href="visualizers/vkt/css/font-awesome.min.css" />
    <link rel="stylesheet" href="visualizers/vkt/css/main.css" />
    <link rel="stylesheet" href="visualizers/vkt/css/custom.css" />
    <link rel="icon" href="visualizers/vkt/assets/icon.png" sizes="32x32" />
</head>
<body>

		<!-- JQUERY -->
		<script src="assets/global/plugins/jquery.min.js" type="text/javascript"></script>

    <!-- NGL -->
		<?php //if($vis_type == 'trajectory') { ?>
	
		<script src="visualizers/vkt/js/ngl.last.js"></script>

		<?php // } else { ?>

    <!--<script src="visualizers/ngl/js/ngl.js"></script>-->

		<?php // }  ?>

    <script src="visualizers/vkt/js/lib/signals.min.js"></script>
    <script src="visualizers/vkt/js/lib/tether.min.js"></script>
    <script src="visualizers/vkt/js/lib/colorpicker.min.js"></script>
    <script src="visualizers/vkt/js/ui/ui.js"></script>
    <script src="visualizers/vkt/js/ui/ui.extra.js"></script>
    <script src="visualizers/vkt/js/ui/ui.ngl.js"></script>

		<?php if(($vis_type == 'single') && ($inPaths[0]["data_type"] != 'na_traj')){ ?>

		<script> var arrayModels = []; var hasModels = true; </script>

		<?php } else { ?>

		<script> var hasModels = false; </script>

		<?php }  ?>

		<script src="visualizers/ngl/js/gui.js"></script>

		<!--<script src="visualizers/ngl/js/ngl.full.min.js"></script>-->


    <script>
        NGL.cssDirectory = "visualizers/vkt/css/";
        
        var stage;
        document.addEventListener( "DOMContentLoaded", function(){
            stage = new NGL.Stage();
            NGL.StageWidget( stage );
					
<?php

						if(!isset($vis_type)) {
								$_SESSION['errorData']['NGL'][] = "ERROR: incorrect format for NGL Viewer. If you provided a DCD file, remember to provide a PDB file too.";
								header('Location: '.$GLOBALS['BASEURL'].'visualizers/error.php');
						}
						?>

						<?php if($vis_type == 'single') { ?>
						// one file

						<?php foreach($inPaths as $f) { ?>

						<?php

						if(pathinfo($f['path'])['extension']) $ext = pathinfo($f['path'])['extension'];
						else $ext = 'pdb';

						?>


						<?php if($f['data_type'] == 'na_traj') {	?>
	
							// input file as a trajectory
						stage.loadFile( "files/<?php echo $f["path"]; ?>", { defaultRepresentation: false, asTrajectory: true, ext: '<?php echo $ext; ?>'} )
							.then( function( o ){
								var tr = o.addTrajectory();
              	tr.trajectory.player.timeout = 100;
								//tr.trajectory.player.interpolateType = "linear";
              	tr.trajectory.player.play();
								o.addRepresentation( "cartoon", { colorScheme: 'residueindex'  } );
								o.addRepresentation( "base", { colorScheme: 'residueindex'  } );
								o.addRepresentation( "ball+stick", { sele: "hetero and not ( water or ion )" } );
								stage.centerView();
							} );

						<?php } else { ?>

							// input file without trajectory
							stage.loadFile( "files/<?php echo $f["path"]; ?>", { defaultRepresentation: false, ext: '<?php echo $ext; ?>' } )
							.then( function( o ){
								o.addRepresentation( "cartoon", { colorScheme: 'residueindex' } );
								o.addRepresentation( "base", { colorScheme: 'residueindex' } );
								o.addRepresentation( "distance", { color: "grey", labelColor: "black"	} );
								//o.setSelection("/0");
								o.addRepresentation( "ball+stick", { sele: "hetero and not ( water or ion )", radius: 0.25 } );
								stage.centerView();
							} );

						<?php } ?>

						<?php } ?>

						<?php } ?>
								
		
						<?php if($vis_type == 'multiple') { ?>
						// multiple files
	
						<?php foreach($inPaths as $f) { ?>

							<?php

						if(pathinfo($f['path'])['extension']) $ext = pathinfo($f['path'])['extension'];
						else $ext = 'obj';

						?>

							<?php if($f['data_type'] == 'na_traj') {	?>

								// input file as a trajectory
								stage.loadFile( "files/<?php echo $f["path"]; ?>", { defaultRepresentation: false, asTrajectory: true, ext: '<?php echo $ext; ?>' } )
								.then( function( o ){
									var tr = o.addTrajectory();
              		tr.trajectory.player.timeout = 100;
              		//tr.trajectory.player.interpolateType = "linear";
              		tr.trajectory.player.play();
									o.addRepresentation( "cartoon", { colorScheme: 'residueindex'  } );
									o.addRepresentation( "base", { colorScheme: 'residueindex'  } );
									o.addRepresentation( "ball+stick", { sele: "hetero and not ( water or ion )" } );
									stage.centerView();
								} );
	
							<?php } else { ?>

								// input file without trajectory
								stage.loadFile( "files/<?php echo $f["path"]; ?>", { defaultRepresentation: false, firstModelOnly: true, ext: '<?php echo $ext; ?>' } )
								.then( function( o ){
									o.addRepresentation( "cartoon", { colorScheme: 'residueindex'   } );
									o.addRepresentation( "base", { colorScheme: 'residueindex'  } );
									o.addRepresentation( "distance", { color: "grey", labelColor: "black"	} );
									o.addRepresentation( "ball+stick", { sele: "hetero and not ( water or ion )", radius: 0.25 } );
									stage.centerView();
								} );

							<?php } ?>

						<?php } ?>

						<?php } ?>

						
						<?php if($vis_type == 'trajectory') { ?>
								// structure file + trajectory file
								//
							<?php

						if(pathinfo($f['path'])['extension']) $ext = pathinfo($f['path'])['extension'];
						else $ext = 'obj';

						?>

							// input file as a trajectory
							stage.loadFile( "files/<?php echo $arr_traj[0]["path"]; ?>", { defaultRepresentation: false, firstModelOnly: true/*, ext: '<?php echo $ext; ?>'*/ } )
							.then( function( o ){
								o.addRepresentation( "cartoon", { colorScheme: 'residueindex'  } );
								o.addRepresentation( "base", { colorScheme: 'residueindex'  } );
								o.addRepresentation( "ball+stick", { sele: "hetero and not ( water or ion )" } );
								/*var framesPromise = NGL.autoLoad( "files/<?php echo $arr_traj[1]["path"]; ?>");
				        var tr = o.addTrajectory( framesPromise );
								tr.trajectory.player.start = 1;
								console.log(tr.trajectory.player);*/
								$("#sidebar .Content").append('<div id="loading-trajectories" style="margin-left:10px;margin-top:10px;"><span class="fa fa-spinner fa-spin" ></span> Loading trajectories, please wait.</div>');
								var framesPromise = NGL.autoLoad( "files/<?php echo $arr_traj[1]["path"]; ?>")
                 .then( function( frames ){
                    var tr = o.addTrajectory( frames );
										tr.trajectory.player.play();
										$("#loading-trajectories").remove();
                 } );
								stage.centerView();
							} );
		
						<?php } ?>


				} );
    </script>
</body>
</html>


