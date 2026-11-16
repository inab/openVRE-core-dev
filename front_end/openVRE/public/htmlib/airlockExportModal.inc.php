<?php
// Modal to request the export of files through the Airlock (see assets/pages/scripts/airlock-export.js).
// Included from workspace/index.php for users that can export.
?>
<div class="modal fade bs-modal" id="modalExport" tabindex="-1" role="basic" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
				<h4 class="modal-title">Request export through the Airlock</h4>
			</div>
			<div class="modal-body">
				<div class="alert alert-danger display-hide" id="export-error"></div>
				<div class="alert alert-success display-hide" id="export-success"></div>

				<!-- step 1: confirm the files to be moved to the Airlock -->
				<div id="export-step-confirm">
					<p>Direct downloads are not allowed for this data. The following files will be copied to the
						Airlock, where they are verified and can be submitted for an egress review:</p>
					<ul id="export-file-list"></ul>
				</div>

				<!-- step 2: transfer and verification in progress -->
				<div id="export-step-progress" class="display-hide">
					<p><i class="fa fa-spinner fa-spin"></i> <span id="export-progress-msg">Transferring and verifying the files in the Airlock...</span></p>
					<p class="font-grey-cascade">You can close this window; the transfer will continue and you can create the submission
						later in the <a href="<?php echo htmlspecialchars($GLOBALS['airlockUrl']); ?>" target="_blank">Airlock</a>.</p>
				</div>

				<!-- step 3: egress submission form -->
				<form id="export-step-submission" class="display-hide" onsubmit="return false;">
					<p>The files are ready in the Airlock. Describe your export request so it can be reviewed.</p>
					<div class="row">
						<div class="col-md-12 form-group">
							<label class="control-label">Title *</label>
							<input type="text" class="form-control" name="title" required maxlength="200">
						</div>
						<div class="col-md-12 form-group">
							<label class="control-label">Description *</label>
							<textarea class="form-control" name="description" rows="3" required></textarea>
						</div>
						<div class="col-md-12 form-group">
							<label class="control-label">Release purpose *</label>
							<input type="text" class="form-control" name="purpose" required placeholder="e.g. Aggregated results for publication">
						</div>
						<div class="col-md-12 form-group">
							<label class="control-label">Data minimisation statement *</label>
							<textarea class="form-control" name="minimization" rows="2" required placeholder="Explain why the exported data is the minimum needed"></textarea>
						</div>
					</div>
					<h5 class="bold">Disclosure risk declarations</h5>
					<div class="row">
							<div class="col-md-6 form-group">
								<label class="control-label">Contains direct identifiers *</label>
								<select class="form-control" name="declarations[containsDirectIdentifiers]" required>
									<option value="">-- Select --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Contains personal data *</label>
								<select class="form-control" name="declarations[containsPersonalData]" required>
									<option value="">-- Select --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Contains free text *</label>
								<select class="form-control" name="declarations[containsFreeText]" required>
									<option value="">-- Select --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Contains images or media *</label>
								<select class="form-control" name="declarations[containsImagesOrMedia]" required>
									<option value="">-- Select --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Contains code *</label>
								<select class="form-control" name="declarations[containsCode]" required>
									<option value="">-- Select --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Contains models or parameters *</label>
								<select class="form-control" name="declarations[containsModelOrParameters]" required>
									<option value="">-- Select --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Contains genomic or biomedical data</label>
								<select class="form-control" name="declarations[containsGenomicOrBiomedicalData]">
									<option value="">-- Not answered --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
							<div class="col-md-6 form-group">
								<label class="control-label">Small counts or outliers risk</label>
								<select class="form-control" name="declarations[smallCountOrOutlierRisk]">
									<option value="">-- Not answered --</option>
									<option value="no">No</option>
									<option value="yes">Yes</option>
									<option value="unknown">Unknown</option>
									<option value="not_applicable">Not applicable</option>
								</select>
							</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn dark btn-outline" data-dismiss="modal">Close</button>
				<a class="btn default display-hide" id="btn-export-airlock" href="<?php echo htmlspecialchars($GLOBALS['airlockUrl']); ?>" target="_blank">
					Continue in Airlock <i class="fa fa-external-link"></i></a>
				<button type="button" class="btn green" id="btn-export-move">Send to Airlock <i class="fa fa-paper-plane"></i></button>
				<button type="button" class="btn green display-hide" id="btn-export-submit">Create submission <i class="fa fa-check"></i></button>
			</div>
		</div>
	</div>
</div>
