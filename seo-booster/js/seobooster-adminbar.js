/* global seobooster_adminbar:true, WinBox:true, jQuery:true */

function getUrlParameter(name) {
	name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
	var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
	var results = regex.exec(location.search);
	return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}


let winboxInstance = null;

function open_floating_window() {
	// Check if the Winbox instance already exists and is not closed
	if (winboxInstance && !winboxInstance.closed) {
		// Bring the existing Winbox to focus
		winboxInstance.focus();
	} else {

		winboxInstance = new WinBox("SEO Booster", {
			html: `<div class="sb-loader"></div>
        <style>
        .winbox {
         z-index: 2147483647 !important;
        }
        .statsrow {
        background:#f0f0f0;
        }
.statsrow td {
    padding: 2px 5px 2px 5px;
    padding-top: 2px;
    padding-right: 5px;
    padding-bottom: 2px;
    padding-left: 5px;
}
        table.sbdetails {
    font-size: 14px;
        }
			
.copyicon {
  width: 14px;
  margin-right: 5px;
  height: 14px;
  background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' width='16' height='16'%3E%3Cpath d='M0 6.75C0 5.784.784 5 1.75 5h1.5a.75.75 0 0 1 0 1.5h-1.5a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-1.5a.75.75 0 0 1 1.5 0v1.5A1.75 1.75 0 0 1 9.25 16h-7.5A1.75 1.75 0 0 1 0 14.25Z'%3E%3C/path%3E%3Cpath d='M5 1.75C5 .784 5.784 0 6.75 0h7.5C15.216 0 16 .784 16 1.75v7.5A1.75 1.75 0 0 1 14.25 11h-7.5A1.75 1.75 0 0 1 5 9.25Zm1.75-.25a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-7.5a.25.25 0 0 0-.25-.25Z'%3E%3C/path%3E%3C/svg%3E") no-repeat center center;
  background-size: contain;
  cursor: pointer;
  transition: all 0.350s ease-in-out;
  border: 2px solid transparent; /* Default state with transparent border */
  opacity: 0.2;
  float: right;
}
			
.copyicon:hover {
    opacity: 1;
}
			
.copyicon:active {
  filter: brightness(0.5); /* Darken icon on click */
  border:2px solid #00ffff;
}
			
.sbdetails .label.notfound {
	background-color: #fbf6e4;
    color: #363636;
    border-color: #a9a712;
    }
			
.sbdetails .label{
  display: inline-block;
  padding: 0.25em 0.5em;
  font-size: 0.75rem;
  font-weight: normal;
margin-right: 5px;
  line-height: 1;
  color: #363636;
  text-align: center;
  white-space: nowrap;
  vertical-align: baseline;
  border-radius: 4px;
  background-color: #f5f5f5;
  border: 1px solid transparent;
}
			
.query {
    font-family:monospace;
}
			
.winbox .wb-header { 
background:#2472b1;
}
			
.innerstats {
display:flex; flex-direction:row; justify-content:flex-end; align-items:center; flex-wrap:wrap;
margin-bottom: 10px;
}
			
.pos_details {
    display: flex;
    flex-wrap: wrap; /* Allows items to wrap onto multiple lines */
    justify-content: flex-start; /* Aligns items to the start of the container */
    align-items: flex-start; /* Aligns items at the start of the cross axis */
			
}
.pos_details span {
 flex: 1 1 auto; /* Allows items to grow and shrink, default size is auto */
    margin: 5px; /* Adjust the spacing between items as needed */
    min-width: 100px; /* Minimum width for items to maintain readability */
}
			
.query-container {
    display: flex;
    align-items: center;
    font-family: monospace;
    color: #000000;
}
.query-container:has(.copyicon[data-clipboard-text*="how to"]),
.query-container:has(.copyicon[data-clipboard-text*="who"]),
.query-container:has(.copyicon[data-clipboard-text*="hvad"]),
.query-container:has(.copyicon[data-clipboard-text*="hvorfor"]),
.query-container:has(.copyicon[data-clipboard-text*="hvordan"]) {
    background-color: #ebecf6;
    padding: 10px;
    border-radius: 4px;
    color: #333;
}
			
			
			
.sb-loader,
.sb-loader:before,
.sb-loader:after {
  border-radius: 50%;
  width: 2.5em;
  height: 2.5em;
  -webkit-animation-fill-mode: both;
  animation-fill-mode: both;
  -webkit-animation: load7 1.8s infinite ease-in-out;
  animation: load7 1.8s infinite ease-in-out;
}
.sb-loader {
    max-width:200px;
    color: #282828;
    font-size: 10px;
    margin: 80px auto;
    position: relative;
    text-indent: -9999em;
    -webkit-transform: translateZ(0);
    -ms-transform: translateZ(0);
    transform: translateZ(0);
    -webkit-animation-delay: -0.16s;
    animation-delay: -0.16s;
}
		.nokws {
		text-align:center;
		margin-top:20px;
		display:block;
		}
.sb-loader:before,
.sb-loader:after {
    content: '';
    position: absolute;
    top: 0;
}
.sb-loader:before {
    left: -3.5em;
    -webkit-animation-delay: -0.32s;
    animation-delay: -0.32s;
}
.sb-loader:after {
    left: 3.5em;
}
@-webkit-keyframes load7 {0%,80%,100% {box-shadow: 0 2.5em 0 -1.3em;}40% {box-shadow: 0 2.5em 0 0;}}
@keyframes load7 {0%,80%,100% {box-shadow: 0 2.5em 0 -1.3em;}40% {box-shadow: 0 2.5em 0 0;}}
        </style>`,
			onclose: function () {
				// Set the instance to null when the Winbox is closed
				winboxInstance = null;
			},
			top: 40,
			index: 9999999,
			right: 30,
			width: 540,
			minwidth: 380,
			bottom: 0,
			class: ["no-full", "no-max"],
			left: 10
		});

		// Make AJAX call to load data
		jQuery.ajax({
			url: seobooster_adminbar.ajax_url,
			type: 'POST',
			data: {
				action: 'sb_gsc_get_keywords',
				post_url: seobooster_adminbar.post_url,
				security: seobooster_adminbar.security
			},
			success: function (response) {
				jQuery('.winbox .wb-body .sb-loader').fadeOut();

				// $spinner.removeClass('is-active');
				if (response.success) {

					if (Array.isArray(response.data.keywords) && response.data.keywords.length > 0) {
						var html = `<table class="sbdetails wp-list-table widefat fixed striped table-view-list" cellspacing="0">
                    <thead>
                        <tr>
                            <th>${seobooster_adminbar.text.query}</th>
                            <th>${seobooster_adminbar.text.used}</th>
														<th>${seobooster_adminbar.text.stats}</th>
                        </tr>
                    </thead>
                    <tbody>`;

						response.data.keywords.forEach(function (keyword) {
							html += `<tr>
                                    <td><div class="query-container"><div class="copyicon" data-clipboard-text="${keyword.query}"></div>${keyword.query}</div></td>
                                    <td>`;
							if (keyword.position_details && keyword.position_details.length > 0) {
								html += `<div class="pos_details">`;
								html += `<span class="nolabel">${keyword.position_details}</span>`;
								html += `</div>`;
							} else {
								html += `<span class="nolabel notfound">&#8226; ${seobooster_adminbar.text.not_found}</span>`;
							}
							html += `</td>`;

							html += `<td><div class="innerstats">
							<span class="label">${seobooster_adminbar.text.clicks}: ${keyword.clicks}</span> - <span class="label">${seobooster_adminbar.text.impressions}: ${keyword.impressions}</span> - <span class="label">${seobooster_adminbar.text.ctr}: ${parseFloat(keyword.ctr).toFixed(2)}%</span> - <span class="label">${seobooster_adminbar.text.avg_position}: ${keyword.position}</span>
					</div></td></tr>`;

						});
						html += `</tbody></table>`;
					} else {
						// Handle the case where keywords array is not available or empty
						var html = `<div class="nokws">${seobooster_adminbar.text.no_keywords_data_available}</div>`;
					}

					jQuery('.winbox .wb-body .sb-loader').fadeOut('slow', function () {
						jQuery(this).remove(); // Remove the loader
						// Append the new content hidden, then fade it in
						var $newContent = jQuery(html).hide();
						jQuery('.winbox .wb-body').append($newContent);
						$newContent.fadeIn('slow'); // Fade in the new content specifically
					});

					// Load the clipboard.js library
					var clipboard = new ClipboardJS('.copyicon');

				} else {
					jQuery('.winbox .wb-body').html(`<div class="keyword-details">${seobooster_adminbar.text.error}: ${response.data.message}</div>`);
				}
			},
			error: function (xhr, status, error) {
				jQuery('.winbox .wb-body').html(`<div class="keyword-details">${seobooster_adminbar.text.error}: ${error}</div>`);
			}

		});
	}
}

jQuery(document).ready(function ($) {

	// Check if the Beaver Builder is active
	if ($('.fl-builder-bar-actions').length > 0) {
		var button = $('.fl-builder-seobooster-button');
		// var header = $('.site-header');
		button.addClass('fl-builder-button-silent');
		// remove the text inside the button
		button.text('');
		button.append(`<svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:bx="https://boxy-svg.com">
			<defs>
			<symbol id="symbol-0" viewBox="0 0 100 100">
			<path d="M 63.332 70.126 L 63.332 70.126 L 63.332 70.126 C 63.332 67.186 62.292 64.896 60.212 63.256 L 60.212 63.256 L 60.212 63.256 C 58.132 61.616 54.475 59.916 49.242 58.156 L 49.242 58.156 L 49.242 58.156 C 44.015 56.403 39.739 54.706 36.412 53.066 L 36.412 53.066 L 36.412 53.066 C 25.612 47.759 20.212 40.466 20.212 31.186 L 20.212 31.186 L 20.212 31.186 C 20.212 26.566 21.555 22.489 24.242 18.956 L 24.242 18.956 L 24.242 18.956 C 26.935 15.423 30.745 12.673 35.672 10.706 L 35.672 10.706 L 35.672 10.706 C 40.599 8.739 46.135 7.756 52.282 7.756 L 52.282 7.756 L 52.282 7.756 C 58.275 7.756 63.649 8.826 68.402 10.966 L 68.402 10.966 L 68.402 10.966 C 73.155 13.106 76.852 16.149 79.492 20.096 L 79.492 20.096 L 79.492 20.096 C 82.125 24.049 83.442 28.566 83.442 33.646 L 83.442 33.646 L 63.392 33.646 L 63.392 33.646 C 63.392 30.246 62.352 27.613 60.272 25.746 L 60.272 25.746 L 60.272 25.746 C 58.192 23.873 55.375 22.936 51.822 22.936 L 51.822 22.936 L 51.822 22.936 C 48.235 22.936 45.402 23.729 43.322 25.316 L 43.322 25.316 L 43.322 25.316 C 41.235 26.896 40.192 28.909 40.192 31.356 L 40.192 31.356 L 40.192 31.356 C 40.192 33.496 41.339 35.433 43.632 37.166 L 43.632 37.166 L 43.632 37.166 C 45.925 38.906 49.955 40.703 55.722 42.556 L 55.722 42.556 L 55.722 42.556 C 61.489 44.403 66.222 46.396 69.922 48.536 L 69.922 48.536 L 69.922 48.536 C 78.935 53.729 83.442 60.889 83.442 70.016 L 83.442 70.016 L 83.442 70.016 C 83.442 77.309 80.692 83.036 75.192 87.196 L 75.192 87.196 L 75.192 87.196 C 69.692 91.363 62.152 93.446 52.572 93.446 L 52.572 93.446 L 52.572 93.446 C 45.812 93.446 39.692 92.233 34.212 89.806 L 34.212 89.806 L 34.212 89.806 C 28.732 87.379 24.609 84.056 21.842 79.836 L 21.842 79.836 L 21.842 79.836 C 19.075 75.616 17.692 70.759 17.692 65.266 L 17.692 65.266 L 37.852 65.266 L 37.852 65.266 C 37.852 69.733 39.005 73.026 41.312 75.146 L 41.312 75.146 L 41.312 75.146 C 43.625 77.259 47.379 78.316 52.572 78.316 L 52.572 78.316 L 52.572 78.316 C 55.892 78.316 58.515 77.603 60.442 76.176 L 60.442 76.176 L 60.442 76.176 C 62.369 74.743 63.332 72.726 63.332 70.126 Z" transform="matrix(1, 0, 0, 1, 0, 0)" style="fill: rgb(130, 135, 140); white-space: pre;" id="s"/>
			</symbol>
			</defs>
			<use width="100" height="100" transform="matrix(4.947808, 0, 0, 4.947808, -20.354914, -11.482257)" xlink:href="#symbol-0"/>
			<path style="paint-order: stroke; fill: rgb(130, 135, 140);" d="M 349.355 16.098 C 333.687 49.355 248.938 171.838 248.938 171.838 C 248.938 171.838 228.3 199.676 236.116 203.927 C 247.584 210.168 267.795 206.135 284.389 206.805 C 309.456 207.816 329.639 205.313 341.68 205.786 C 341.68 205.786 359.942 201.1 363.11 211.672 C 365.18 218.581 354.131 230.067 354.131 230.067 L 105.339 481.212 L 213.627 310.542 C 213.627 310.542 221.796 293.779 216.787 287.127 C 210.653 278.986 186.557 281.117 186.557 281.117 C 186.557 281.117 140.259 279.657 117.109 279.939 C 108.054 280.05 99.5 279.319 99.082 272.877 C 98.532 264.365 100.711 262.353 110.047 252.866 C 188.089 173.584 349.355 16.098 349.355 16.098 Z"/>
			</svg>`);

		$('.fl-builder-seobooster-button svg').css({
			'height': '20px',
			'width': '20px'
		});

		button.on('click', function (e) {
			e.preventDefault();
			open_floating_window();
			$('#seobooster-floating-div').show();

		});
	}

	// Check if the GET parameter "seobooster_showdetails" is set to "1"
if (getUrlParameter('seobooster_showdetails') === '1') {
	open_floating_window();
}

	// Toggle floating div and fetch keyword details
	$(document).on('click', '.seobooster-details a', function (e) {
		e.preventDefault();
		open_floating_window();
	});

	// Close button functionality
	$(document).on('click', '.seobooster-close', function () {
		$('#seobooster-floating-div').hide();
	});
});
