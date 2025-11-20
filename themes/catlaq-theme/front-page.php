<?php
/**
 * Front Page template for Catlaq.
 *
 * @package CatlaqAI\Theme
 */

get_header();
?>

<main class="catlaq-landing">
	<section class="catlaq-hero">
		<div class="catlaq-hero__content">
			<p class="catlaq-badge"><?php esc_html_e( 'Commission-Free Digital Expo', 'catlaq-ai' ); ?></p>
			<h1><?php esc_html_e( 'Catlaq Digital Expo', 'catlaq-ai' ); ?></h1>
			<p><?php esc_html_e( 'Members showcase products, run agreements, and manage logistics with AI protection—Catlaq never takes a trade commission.', 'catlaq-ai' ); ?></p>
			<div class="catlaq-hero__actions">
				<a class="catlaq-cta catlaq-cta--solid" href="<?php echo esc_url( home_url( '/catlaq-access' ) ); ?>">
					<?php esc_html_e( 'Start Membership', 'catlaq-ai' ); ?>
				</a>
				<a class="catlaq-cta catlaq-cta--ghost" href="<?php echo esc_url( home_url( '/catlaq-dashboard' ) ); ?>">
					<?php esc_html_e( 'View Dashboard Tour', 'catlaq-ai' ); ?>
				</a>
			</div>
		</div>
		<div class="catlaq-hero__panel">
			<div class="catlaq-hero__card">
				<h3><?php esc_html_e( 'Live Expo Metrics', 'catlaq-ai' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'AI Agents monitoring agreements 24/7', 'catlaq-ai' ); ?></li>
					<li><?php esc_html_e( 'Digital booths ready for RFQs', 'catlaq-ai' ); ?></li>
					<li><?php esc_html_e( 'No platform commissions—ever', 'catlaq-ai' ); ?></li>
				</ul>
			</div>
		</div>
	</section>

	<section class="catlaq-section catlaq-section--split">
		<div class="catlaq-section__text">
			<h2><?php esc_html_e( 'Showcase at the Digital Expo', 'catlaq-ai' ); ?></h2>
			<p><?php esc_html_e( 'Publish products, manage RFQs, and invite buyers into secure agreement rooms. Catlaq’s Expo modules keep everything synchronized.', 'catlaq-ai' ); ?></p>
		</div>
		<div class="catlaq-section__content">
			<?php echo do_shortcode( '[catlaq_expo_booths]' ); ?>
		</div>
	</section>

    <section class="catlaq-section" id="catlaq-membership-tracks">
        <div class="catlaq-section__text">
            <h2><?php esc_html_e( 'Membership Plans', 'catlaq-ai' ); ?></h2>
            <p><?php esc_html_e( 'Choose the plan that fits your buyer, seller, or broker workflow. Upgrade anytime without trade commissions.', 'catlaq-ai' ); ?></p>
        </div>
        <?php echo do_shortcode( '[catlaq_membership_plans]' ); ?>
	</section>

	<section class="catlaq-section catlaq-section--dark">
		<div class="catlaq-section__text">
			<h2><?php esc_html_e( 'Activity Stream', 'catlaq-ai' ); ?></h2>
			<p><?php esc_html_e( 'Keep an eye on Expo announcements, member activity, and AI recommendations.', 'catlaq-ai' ); ?></p>
		</div>
		<?php echo do_shortcode( '[catlaq_engagement_feed]' ); ?>
	</section>
</main>

<?php
get_footer();
