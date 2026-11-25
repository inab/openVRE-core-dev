
var baseURL = $('#base-url').val();

function loadToolButtons(tool) {
	$.ajax({
		type: "POST",
		url: baseURL + "applib/getHomeButtons.php",
		data: "tool=" + tool, 
		success: function(data) {
	
			var cont_btns = '';

			$.each(JSON.parse(data), function(k,v) {
				cont_btns += '<a href="tools/' + tool + '/front/input.php?op=' + k + '"  class="cbp-l-inline-view btn blue btn-outline">' + v + '</a>';
			});		

			$("#btns-exc-tool").html(cont_btns);
		}
	});
}




$(document).ready(function() {

	//console.log($("#ref-genome-jbrowse"));

/*	$("#ref-genome-jbrowse").change(function() {

		console.log($(this).val());

		

	});	*/

});

