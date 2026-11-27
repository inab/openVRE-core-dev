var baseURL = $('#base-url0').val();

var current_block = 0;
var feedback_from_file = [];

function showValidation(op) {
	var block = op.value;
	$("#summary" + current_block).fadeOut(300);
	$("#alert-message" + current_block).fadeOut(300);
	$('#feedback-file' + current_block).fadeOut(300);
	$("#formInputs" + current_block).fadeOut(300, function () {
		$("#formInputs" + block).fadeIn(300);
		$("#summary" + block).fadeIn(300);
		$("#alert-message" + block).fadeIn(300);
		if (feedback_from_file.indexOf(parseInt(block)) != -1) $('#feedback-file' + block).fadeIn(300);
		current_block = block;
	});

}

function formBlockState(state, block, input_type, id) {
	if (state == 'disabled') {
		$(block + id).fadeOut();
		$(block + id + " " + input_type).prop('disabled', true);
	} else {
		$(block + id).fadeIn();
		$(block + id + " " + input_type).prop('disabled', false);
	}
}

// All data type options rendered server-side, cached per form on first use.
var allDataTypeOptions = {};

function customfromFormat(op, id) {
	$('#refGenome' + id).prop("disabled", true);
	$('#refGenomeTR' + id).hide();

	var select = $('#data_type_sel' + id);
	if (!allDataTypeOptions[id]) {
		allDataTypeOptions[id] = select.find('option[value!=""]').clone();
	}

	// Keep only the data types associated with the selected format.
	var current = select.val();
	var matching = allDataTypeOptions[id].filter(function () {
		return ($(this).data('formats') || []).indexOf(op) !== -1;
	}).clone();

	select.find('option[value!=""]').remove();
	select.append(matching);
	select.val(matching.filter('[value="' + current + '"]').length ? current : '');

	var hasDataTypes = matching.length > 0;
	select.prop("disabled", !hasDataTypes).trigger('change.select2');
	$('#dataType' + id).toggle(hasDataTypes);
	$('#dataTypeWarning' + id).toggle(!!op && !hasDataTypes);

	customfromDataType(select.val(), id);
}

function customfromDataType(op, id) {
	// Reset optional blocks first.
	$('.paired' + id).prop("disabled", true);
	$('#pairedTR' + id).hide();
	$('.sorted' + id).prop("disabled", true);
	$('#sortedTR' + id).hide();
	showHideSortInfo(0, id);

	if (!op) {
		return;
	}

	// BAM-specific metadata options are controlled by selected file format.
	var selectedFormat = ($('#format' + id).val() || '').toUpperCase();
	if (selectedFormat === 'BAM') {
		$('.paired' + id).prop("disabled", false);
		$('#pairedTR' + id).show();
		$('.sorted' + id).prop("disabled", false);
		$('#sortedTR' + id).show();
	}
}


function showHideSortInfo(op, id) {

	if (op == 1) {
		$("#sortInfo" + id).fadeIn();
	} else {
		$("#sortInfo" + id).fadeOut();
	}

}

function checkIfAllValidated() {
	$('#myModal1').modal('show');
}

Array.prototype.remove = function () {
	var what, a = arguments, L = a.length, ax;
	while (L && this.length) {
		what = a[--L];
		while ((ax = this.indexOf(what)) !== -1) {
			this.splice(ax, 1);
		}
	}
	return this;
};


//var titleFeedbackBox = ['ERROR', 'SUMMARY', 'SUCCESS', 'INFO'];
var titleFeedbackBox = ['ERROR', 'SUCCESS', 'SUMMARY', 'INFO'];
//var stateLabel = ['ERROR', 'READY', 'VALIDATED', 'PROCESSING'];
var stateLabel = ['ERROR', 'VALIDATED', 'READY', 'PROCESSING'];

function showProcessValidation(obj, id) {

	feedback_from_file.push(id);

	// show message
	$('#feedback-file' + id + ' h4').html(titleFeedbackBox[obj.state]);
	$('#feedback-file' + id + ' span').html(obj.msg);
	for (var k in fileMessageColor) $('#feedback-file' + id).removeClass(fileMessageColor[k]);
	$('#feedback-file' + id).addClass(fileMessageColor[obj.state]);
	$('#feedback-file' + id).show();

	// change state
	for (var k in fileStateColor) $('#file' + id + '-state').removeClass(fileStateColor[k]);
	$('#file' + id + '-state').addClass(fileStateColor[obj.state]);
	$('#file' + id + '-state').html(stateLabel[obj.state]);


	console.log(obj.state);

	switch (obj.state) {
		case 0: // enable send button
			$('#formInputs' + id + ' .disable-form').fadeOut();
			$('#formInputs' + id + ' .btn-send-data').html(
				//'<input type="button" class="btn green snd-metadata-btn" value="SEND METADATA" onclick="sendMetadata(' + id  + ', 1);" style="position:relative;z-index:20;" >'	
				'<input type="submit" class="btn green snd-metadata-btn" value="SEND METADATA AGAIN" style="position:relative;z-index:20;" >'
			);
			$("#op" + id).val('1');
			break;
		case 2: // change send metadata button for validate button
			$('#formInputs' + id + ' .btn-send-data').html(
				//'<input type="button" class="btn green val-metadata-btn" value="VALIDATE METADATA" onclick="sendMetadata(' + id  + ', 2);" style="position:relative;z-index:20;" >'	
				'<input type="submit" class="btn green val-metadata-btn" value="ACCEPT CHANGES" style="position:relative;z-index:20;" >'
			);
			$("#op" + id).val('2');
			$('#formInputs' + id + ' .disable-form').fadeIn();
			break;
		case 1: // disable all buttons and form
			$('#formInputs' + id + ' .val-metadata-btn').fadeOut(200);
			$('#formInputs' + id + ' .snd-metadata-btn').fadeOut(200);
			$('#formInputs' + id + ' .disable-form').fadeIn();
			$("#op" + id).val('1');
			totalNumBlocks--;
			break;
		case 3: // disable all buttons and form
			$('#formInputs' + id + ' .val-metadata-btn').fadeOut(200);
			$('#formInputs' + id + ' .disable-form').fadeIn();
			$("#op" + id).val('1');
			totalNumBlocks--;
			break;

	}

	if (totalNumBlocks == 0) {
		$('#bottom-no-validated-files').hide();
		$('#bottom-validated-files').show();
	}

	arrayFiles.remove(parseInt(id));

	if ((obj.state != 2) && (obj.state != 0)) {

		if (arrayFiles.length > 0) {

			var newIDX = arrayFiles[0];

			$("input[name=idx][value=" + newIDX + "]").prop('checked', true);

			showValidation(document.getElementsByName("idx")[newIDX]);

		} else {

			location.href = baseURL + 'workspace/';

		}

	}

}


var ValidateForm = function () {

	var handleForm = function () {

		$.each($('.uploadFiles'), function () {

			//$('.uploadFiles').validate({
			$(this).validate({
				errorElement: 'span', //default input error message container
				errorClass: 'help-block', // default input error message class
				focusInvalid: false, // do not focus the last invalid input
				ignore: [],
				rules: {
					data_type: {
						required: true
					},
					format: {
						required: true
					},

					refGenome: {
						required: true
					}

				},

				invalidHandler: function (event, validator) { //display error alert on form submit
					$('.err-nd', $('#uploadFiles' + formSelected)).show();
					$('.warn-nd', $('#uploadFiles' + formSelected)).hide();
				},

				highlight: function (element) { // hightlight error inputs
					$(element)
						.closest('.form-group').addClass('has-error'); // set error class to the control group
				},

				success: function (label, e) {
					$(e).parent().removeClass('has-error');
					/*$(e).parent().parent().removeClass('has-error');
					$(e).parent().parent().parent().removeClass('has-error');*/
				},

				errorPlacement: function (error, element) {

					error.insertAfter(element);
				},

				submitHandler: function (form) {
					if ($("#op").val() == 1) {
						$('#formInputs' + formSelected + ' .snd-metadata-btn').prop('disabled', true);
						$('#formInputs' + formSelected + ' .snd-metadata-btn').val('SENDING METADATA...');
					} else {
						$('#formInputs' + formSelected + ' .val-metadata-btn').prop('disabled', true);
						$('#formInputs' + formSelected + ' .val-metadata-btn').val('VALIDATING METADATA...');
					}

					var data = $('#uploadFiles' + formSelected).serialize();
					data = data.replace(/%5B/g, "[");
					data = data.replace(/%5D/g, "]");

					$.ajax({
						type: "POST",
						url: baseURL + "applib/processValidation.php",
						data: data,
						success: function (data) {
							d = data.replace(/(\r\n|\n|\r|\t)/gm, "");
							var json = JSON.parse(d);
							showProcessValidation(json, formSelected);
						}
					});

				}
			});

		});

	}

	return {
		//main function to initiate the module
		init: function () {
			handleForm();
		}

	};

}();



var totalNumBlocks = 0;

var formSelected = 0;

var arrayFiles = [];

jQuery(document).ready(function () {
	// force to load first form properly
	$('#formInputs0').fadeIn();
	$('.formInputs').each(function (index) {
		var val = $(this).find('.formatSelector option:selected').val();
		customfromFormat(val, index);
		totalNumBlocks++;
	});

	$('#myModal1').on('click', '.btn-modal-ok', function (e) {
		location.href = baseURL + 'workspace/';
	});

	/*$(".snd-metadata-btn").click(function() {
		
		formSelected = $(this).attr("id");

	});*/

	for (i = 0; i < $("#numFiles").val(); i++) arrayFiles.push(i);


	ValidateForm.init();


	$('.snd-metadata-btn').on('click', function () {  // capture the click   

		var id = $(this).attr("id").substr(3, 5);

		formSelected = id;
		//console.log($('#uploadFiles' + id));

		/*		$("#data_type_sel" + id).rules("add", { required:true });
				$("#refGenome" + id).rules("add", { required:true });*/

		//$('#uploadFiles' + id).valid();

		$('#uploadFiles' + id).submit();


		//console.log($("#data_type_sel" + id));

		//	$('#uploadFiles').submit();

	});

	$(".data-type-selector").select2({
		placeholder: "Select data type",
		width: '100%',
		minimumResultsForSearch: 1
	});

	$(".file-type-selector").select2({
		placeholder: "Select file type",
		width: '100%',
		minimumResultsForSearch: 1
	});

});
