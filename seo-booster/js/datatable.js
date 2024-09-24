/*
Separated code controlling datatables
*/
jQuery(document).ready(function($) {

	var otable = jQuery('#datatable').DataTable({
		'processing': true,
		'serverSide': true,
		'lengthMenu': [[10, 25, 50, 100], [10, 25, 50, 100]],
		'searching': true,
		'order': [[ 5, 'desc' ]],
		'ajax': {
			'url' : ajaxurl+'?action=fn_my_ajaxified_dataloader_ajax',
			'dataType' : "json",
			'contentType' : "application/json; charset=utf-8",
			data: function ( d ) {
				d.showkws = jQuery("input[name='showkws']:checked").val() ,
				d.hideinternal = jQuery("input:checkbox[name='hideinternal']:checked").val(),
				d._ajax_nonce = seobooster_datatable.nonce
			}
		}
	});

	jQuery("#hideinternal").click(function(event){
		internal = jQuery("input:checkbox[name='hideinternal']:checked").val();
		//console.log('internal '+internal);
		otable.ajax.reload();
	});

	jQuery("#keywordsfilter input").click(function(event){
		//console.log('filter '+jQuery("input[name='showkws']:checked").val());
		otable.ajax.reload();
	});
});