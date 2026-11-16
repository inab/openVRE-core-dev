// Export of workspace files through the Airlock, for users that cannot
// download data. Used by the #modalExport modal (htmlib/airlockExportModal.inc.php).

var exportFileIds = [];
var exportId = null;
var exportPollTimer = null;
var EXPORT_POLL_MS = 3000;

// export states, as returned by workspace.php (AirlockExportState)
var EXPORT_STATE = Object.freeze({
	MOVE_REQUESTED: 'MOVE_REQUESTED',
	READY: 'READY',
	SUBMITTED: 'SUBMITTED'
});

// steps of the export modal
var EXPORT_STEP = Object.freeze({
	CONFIRM: 'confirm',
	PROGRESS: 'progress',
	SUBMISSION: 'submission',
	DONE: 'done'
});

function escapeExportHtml(text) {
	return $('<div>').text(text).html();
}

function exportFileName(fileId) {
	for (var i in allFiles) {
		if (allFiles[i].fileId == fileId) return allFiles[i].fileName;
	}
	return fileId;
}

function requestExport(fileId) {
	openExportModal([decodeURIComponent(fileId)]);
}

function requestExportSelected() {
	var ids = [];
	for (var i in allFiles) {
		if (allFiles[i].checked && allFiles[i].fileId) ids.push(allFiles[i].fileId);
	}
	if (!ids.length) return;
	openExportModal(ids);
}

function setExportStep(step) {
	$('#export-step-confirm').toggle(step === EXPORT_STEP.CONFIRM);
	$('#export-step-progress').toggle(step === EXPORT_STEP.PROGRESS);
	$('#export-step-submission').toggle(step === EXPORT_STEP.SUBMISSION);
	$('#btn-export-move').toggle(step === EXPORT_STEP.CONFIRM);
	$('#btn-export-submit').toggle(step === EXPORT_STEP.SUBMISSION);
	$('#btn-export-airlock').toggle(step !== EXPORT_STEP.CONFIRM);
}

function showExportError(msg) {
	$('#export-error').html(escapeExportHtml(msg)).show();
}

function openExportModal(fileIds) {
	exportFileIds = fileIds;
	exportId = null;
	clearTimeout(exportPollTimer);

	$('#export-error, #export-success').hide().html('');
	$('#export-file-list').html($.map(fileIds, function (id) {
		return '<li>' + escapeExportHtml(exportFileName(id)) + '</li>';
	}).join(''));
	$('#export-step-submission')[0].reset();
	$('#btn-export-move').prop('disabled', false).html('Send to Airlock <i class="fa fa-paper-plane"></i>');
	$('#btn-export-submit').prop('disabled', false).html('Create submission <i class="fa fa-check"></i>');
	setExportStep(EXPORT_STEP.CONFIRM);

	$('#modalExport').modal({ show: 'true' });
}

function pollExportStatus() {
	$.ajax({
		type: "POST",
		url: baseURL + "workspace/workspace.php",
		data: { op: 'exportStatus', id: exportId },
		dataType: 'json',
		success: function (data) {
			if (data.error) {
				showExportError(data.msg);
				return;
			}
			handleExportState(data.export);
		},
		error: function () {
			showExportError('Cannot retrieve the export status. Check it later in the Airlock.');
		}
	});
}

function handleExportState(exp) {
	switch (exp.state) {
		case EXPORT_STATE.MOVE_REQUESTED:
			setExportStep(EXPORT_STEP.PROGRESS);
			exportPollTimer = setTimeout(pollExportStatus, EXPORT_POLL_MS);
			break;
		case EXPORT_STATE.READY:
			setExportStep(EXPORT_STEP.SUBMISSION);
			break;
		case EXPORT_STATE.SUBMITTED:
			setExportStep(EXPORT_STEP.DONE);
			$('#export-success').html('Your export request has been submitted for review. ' +
				'<a href="' + escapeExportHtml(exp.airlockUrl) + '" target="_blank">View your submission in the Airlock <i class="fa fa-external-link"></i></a>').show();
			break;
		default:
			setExportStep(EXPORT_STEP.DONE);
			showExportError(exp.error || 'The export failed.');
	}
}

$(document).ready(function () {
	$('#modalExport').on('hidden.bs.modal', function () {
		clearTimeout(exportPollTimer);
	});

	$('#btn-export-move').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).html('Sending...');
		$('#export-error').hide();

		$.ajax({
			type: "POST",
			url: baseURL + "workspace/workspace.php",
			data: { op: 'exportFile', fn: exportFileIds },
			dataType: 'json',
			success: function (data) {
				if (data.error) {
					showExportError(data.msg);
					$btn.prop('disabled', false).html('Send to Airlock <i class="fa fa-paper-plane"></i>');
					return;
				}
				exportId = data.export.id;
				handleExportState(data.export);
			},
			error: function () {
				showExportError('The export request could not be sent. Please, try again later.');
				$btn.prop('disabled', false).html('Send to Airlock <i class="fa fa-paper-plane"></i>');
			}
		});
	});

	$('#btn-export-submit').on('click', function () {
		var form = $('#export-step-submission')[0];
		if (!form.checkValidity()) {
			form.reportValidity();
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true).html('Submitting...');
		$('#export-error').hide();

		$.ajax({
			type: "POST",
			url: baseURL + "workspace/workspace.php?op=exportSubmit&id=" + encodeURIComponent(exportId),
			data: $(form).serialize(),
			dataType: 'json',
			success: function (data) {
				$btn.prop('disabled', false).html('Create submission <i class="fa fa-check"></i>');
				if (data.error) {
					showExportError(data.msg);
					return;
				}
				handleExportState(data.export);
			},
			error: function () {
				$btn.prop('disabled', false).html('Create submission <i class="fa fa-check"></i>');
				showExportError('The submission could not be created. Please, try again or continue in the Airlock.');
			}
		});
	});
});
