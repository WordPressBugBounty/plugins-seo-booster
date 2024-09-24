<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $current_user,$seobooster_fs;

	if ( seobooster_fs()->is_not_paying() ) {
		?>
		<section class="marketingbox" id="sbpmarketingbox">
			<h3 class="headline">SEO Booster PRO - 30 Day Trial <span>&#9829;</span></h3>
			<div class="innerbox">
				<div class="profeatures">
					<h3>Extra features in Premium</h3>
					<ul>
						<li><span></span><div>Get more details about the backlinks to your website.</div></li>
						<li><span></span><div>Backlinks are verified regularly for you.</div></li>
						<li><span></span><div>Google PageSpeed Insights audits.</div></li>
						<li><span></span><div>Access to quick and easy inline help.</div></li>
						<li><span></span><div>Keyword injection - Put the most popular keyword in the &lt;title&gt tag.</div></li>
						<li><span></span><div>Crawler visits - When and where search engine robots visit.</div></li>
						<li><span></span><div>Export keywords, backlinks and 404 errors to .csv files.</div></li>
						<li><span></span><div>Get premium support by the developers.</div></li>
						<li><span></span><div>You bribe your way out of seeing this message :-)</div></li>
					</ul>
				</div>
				<div class="faq">
					<?php
					echo '<a href="' . seobooster_fs()->get_trial_url() . '" class="button button-primary freetrial">' .
					__( 'Start 30 Day Trial', 'seo-booster' ) . '<span>Try the premium features</span></a>';
					__( 'See prices', 'seo-booster' ) . '</a>';
					?>
					<p><center><em>$6.99 per month. Annual price $39.99 for 1 site or $119.99 lifetime!</em></center></p>
					<ul>
						<li>We ask for your payment information to reduce fraud and provide a seamless subscription experience.</li>
						<li>CANCEL ANYTIME before the trial ends to avoid being charged.</li>
						<li>We will send you an email reminder BEFORE your trial ends.</li>
						<li>We accept Visa, Mastercard, American Express and PayPal.</li>
						<li>Upgrade, downgrade or cancel any time.</li>
					</ul>
				</div>
			</div>
		</section>
		<?php
	}
	?>
