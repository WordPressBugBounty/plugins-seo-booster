/* globals Beacon:true; sbbeacondata:true, jQuery:true */

jQuery(document).on("click", function () {
	jQuery(".sugglist").hide();
});


/**
 * Enables plugin background updates
 *
 * @author	Lars Koudal
 * @since	v0.0.1
 * @version	v1.0.0	Friday, March 18th, 2022.	
 * @version	v1.0.1	Tuesday, March 22nd, 2022.
 * @global
 * @return	void
 */
 function sbp_enable_background_updates(e) {
  jQuery(this).attr('disabled');
  jQuery('#sbp-enable-background-updates a.button').addClass('disabled');
  jQuery('#sbp-enable-background-updates .dismiss-this').addClass('disabled');
  jQuery('.wrap').prepend('<div class="secning-loading-popup"><p>Please wait<span class="spinner is-active"></span></p></div>');
  jQuery(".secnin-loading-popup").toggle();
  var nonce = jQuery('#sbp-enable-background-updates-nonce').val();
  jQuery.ajax({
    type: 'POST',
    url: ajaxurl,
    data: {
      action: 'sbp_enable_background_updates',
      nonce: nonce
    },
    success: function (data) {
      location.reload();
    },
    error: function (xhr, textStatus, error) {
      console.log(xhr.statusText);
      console.log(textStatus);
      console.log(error);
    }
  });
}




function seobooster_freemius_opt_in( element ) {
	var nonce  = jQuery( '#seobooster-freemius-opt-nonce' ).val(); // Nonce.
	var choice = jQuery( element ).data( 'opt' ); // Choice.

	jQuery.ajax( {
		type: 'POST',
		url: ajaxurl,
		async: true,
		data: {
			action: 'seobooster_freemius_opt_in',
			opt_nonce: nonce,
			choice: choice
		},
		success: function( data ) {
			location.reload();
		},
		error: function( xhr, textStatus, error ) {
			console.log( xhr.statusText );
			console.log( textStatus );
			console.log( error );
		}
	} );
}




jQuery(document).ready(function() {




	jQuery('a.quickhelp').attr('title', 'You have to allow Helpscout beacon to load to get help.');

	jQuery('.sbp-dismiss-review-notice, .sbp-review-notice .notice-dismiss').on('click', function() {
		if ( ! jQuery(this).hasClass('sbp-reviewlink') ) {
			event.preventDefault();
		}
		jQuery.post( sbbeacondata.ajaxurl, {
			action: 'sbp_dismiss_review',
			nonce: sbbeacondata.nonce
		});
		jQuery('.sbp-review-notice').slideUp().remove();
	});


	jQuery('.suggtitle').click(function(event){
		event.stopPropagation();

		var $this = jQuery(this).parent().find('.sugglist');

		jQuery(".suggcont .suggestions .sugglist").not($this).hide();
		$this.slideToggle();

	});
	jQuery(".suggtitle").on("click", function (event) {
		event.stopPropagation();
	});


// ****** Add new keyword AJAX
jQuery( "#sb2_autolink_add" ).submit( function( event ) {
	event.preventDefault();
		jQuery('#sb2_autolink_add #submit').prop('disabled',true); // Disable button

		jQuery('.kwaddspinner').show(); // show the spinner
		jQuery("#sb2_autolink_add form #newkeyword").removeClass('hasError').prop('disabled', true);
		jQuery("#sb2_autolink_add form #targeturl").removeClass('hasError').prop('disabled', true);

		jQuery.post(
			sbbeacondata.ajaxurl, {
				'dataType': 'json',
				'action'						: 'ajax_add_keyword',
				'add-keyword-nonce' : jQuery('#_ajax_sb2_add_keyword_nonce').val(),
				'newkeyword'				: jQuery('#sb2_autolink_add form #newkeyword').val(),
				'targeturl'					: jQuery('#sb2_autolink_add form #targeturl').val()
			},
			function(response) {
			// malformed url
			jQuery('#sb2_autolink_add #submit').prop('disabled',false); // Reneable the button

			jQuery('.kwaddspinner').hide(); // Hide the spinner

			jQuery('#addkwresponse').html(response.answer); // Show the response from server


			if (response.newrow) {
				jQuery(response.newrow).appendTo(jQuery(".seo-booster_page_sb2_autolink #urls-filter .wp-list-table"));
			}

			if (response.success) {
				jQuery("#sb2_autolink_add_form #newkeyword").prop('disabled', false).val('');
				jQuery("#sb2_autolink_add_form #targeturl").prop('disabled', false).val('');
			}

			if (response.error==='malurl') {
				jQuery('#addkwresponse').addClass('hasError');
				jQuery('#sb2_autolink_add form #targeturl').addClass('hasError').prop('disabled', false);
				jQuery("#sb2_autolink_add form #newkeyword").prop('disabled', false);
			}

			if (response.error==='kwused') {
				jQuery('#addkwresponse').addClass('hasError');
				jQuery('#sb2_autolink_add form #newkeyword').addClass('hasError').prop('disabled', false).focus();
				jQuery("#sb2_autolink_add form #targeturl").prop('disabled', false);
			}

		}
		);

	});



	// Changes the css and visual appearance of some settings in SEO Booster 2 if the "Use Dynamic Tagging" feature is turned on.
	jQuery('#seobooster_dynamic_tagging').on('click', function() {
		if (jQuery(this).prop('checked')) {
			jQuery('.taggingrelated').each( function( i, elem ) {
				jQuery(elem).removeClass('muted');
			});
		}
		else {
			jQuery('.taggingrelated').each( function( i, elem ) {
				jQuery(elem).addClass('muted');
			});
		}
	});


	// todo - check if any images needs to be lazy loaded before running the script
	// todo - any lazy load library included with WP?
	jQuery("img.lazy").lazyload();

});
