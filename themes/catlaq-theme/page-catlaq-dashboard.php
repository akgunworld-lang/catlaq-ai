<?php
/**
 * Template Name: Catlaq Dashboard
 * Description: Membership-aware layout with collapsible Digital Expo sidebars.
 *
 * @package CatlaqAI\Theme
 */

get_header();

$categories = get_terms(
    [
        'taxonomy'   => 'catlaq_product_category',
        'hide_empty' => false,
        'number'     => 25,
    ]
);

$current_user = wp_get_current_user();
?>

<section class="catlaq-dashboard" id="catlaq-dashboard">
	<aside class="catlaq-dashboard__sidebar catlaq-dashboard__sidebar--left" aria-label="<?php esc_attr_e( 'Expo Categories', 'catlaq-ai' ); ?>">
		<button class="catlaq-dashboard__toggle" data-catlaq-toggle="left" aria-expanded="false">
			<span><?php esc_html_e( 'Categories', 'catlaq-ai' ); ?></span>
			<span class="catlaq-dashboard__toggle-icon" aria-hidden="true"></span>
		</button>
		<div class="catlaq-dashboard__sidebar-content">
			<h3><?php esc_html_e( 'Browse Digital Expo', 'catlaq-ai' ); ?></h3>
			<?php if ( is_wp_error( $categories ) || empty( $categories ) ) : ?>
				<p><?php esc_html_e( 'Booth categories will appear once products are published.', 'catlaq-ai' ); ?></p>
			<?php else : ?>
				<ul class="catlaq-dashboard__categories">
					<?php foreach ( $categories as $term ) : ?>
						<li>
							<a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
								<span><?php echo esc_html( $term->name ); ?></span>
								<small><?php echo absint( $term->count ); ?></small>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php echo do_shortcode( '[catlaq_expo_category_filter]' ); ?>
		</div>
	</aside>

	<main class="catlaq-dashboard__content">
		<header class="catlaq-dashboard__header">
			<h1><?php echo wp_kses_post( get_the_title() ); ?></h1>
			<p><?php esc_html_e( 'Control your entire Catlaq journey from one responsive workspace.', 'catlaq-ai' ); ?></p>
		</header>

		<div class="catlaq-dashboard__widgets">
			<?php echo do_shortcode( '[catlaq_membership_overview]' ); ?>
			<?php echo do_shortcode( '[catlaq_auth_portal show_login="false" show_register="true"]' ); ?>
		</div>

		<div class="catlaq-dashboard__page-content">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>

			<section class="catlaq-dashboard__plans">
				<h2><?php esc_html_e( 'Membership Plans', 'catlaq-ai' ); ?></h2>
				<?php echo do_shortcode( '[catlaq_membership_plans]' ); ?>
			</section>

			<section class="catlaq-dashboard__policies">
				<h2><?php esc_html_e( 'Catlaq Policies', 'catlaq-ai' ); ?></h2>
				<div class="catlaq-dashboard__policy-grid">
					<?php
					echo do_shortcode( '[catlaq_policy type="privacy"]' );
					echo do_shortcode( '[catlaq_policy type="terms"]' );
					echo do_shortcode( '[catlaq_policy type="refund"]' );
					?>
				</div>
			</section>
		</div>
	</main>

	<aside class="catlaq-dashboard__sidebar catlaq-dashboard__sidebar--right" aria-label="<?php esc_attr_e( 'Member Profile', 'catlaq-ai' ); ?>">
		<button class="catlaq-dashboard__toggle" data-catlaq-toggle="right" aria-expanded="false">
			<span><?php esc_html_e( 'Profile & Tools', 'catlaq-ai' ); ?></span>
			<span class="catlaq-dashboard__toggle-icon" aria-hidden="true"></span>
		</button>
		<div class="catlaq-dashboard__sidebar-content">
			<h3><?php esc_html_e( 'Member Snapshot', 'catlaq-ai' ); ?></h3>
			<?php if ( is_user_logged_in() ) : ?>
				<ul class="catlaq-dashboard__profile-meta">
					<li><strong><?php esc_html_e( 'Name:', 'catlaq-ai' ); ?></strong> <?php echo esc_html( $current_user->display_name ); ?></li>
					<li><strong><?php esc_html_e( 'Email:', 'catlaq-ai' ); ?></strong> <?php echo esc_html( $current_user->user_email ); ?></li>
					<li><strong><?php esc_html_e( 'Username:', 'catlaq-ai' ); ?></strong> <?php echo esc_html( $current_user->user_login ); ?></li>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'Sign in to see your Expo credentials and quotas.', 'catlaq-ai' ); ?></p>
			<?php endif; ?>

			<div class="catlaq-dashboard__card">
				<h4><?php esc_html_e( 'Quick Actions', 'catlaq-ai' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/digital-expo' ) ); ?>"><?php esc_html_e( 'Visit Digital Expo', 'catlaq-ai' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/rfq' ) ); ?>"><?php esc_html_e( 'Submit an RFQ', 'catlaq-ai' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>"><?php esc_html_e( 'Contact Catlaq', 'catlaq-ai' ); ?></a></li>
				</ul>
			</div>

			<?php echo do_shortcode( '[catlaq_membership_overview title="' . esc_attr__( 'Quota Status', 'catlaq-ai' ) . '"]' ); ?>
		</div>
	</aside>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var buttons = document.querySelectorAll('[data-catlaq-toggle]');
	buttons.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = btn.getAttribute('data-catlaq-toggle');
			var panel = document.querySelector('.catlaq-dashboard__sidebar--' + target);
			if (!panel) {
				return;
			}
			panel.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
		});
	});
});
</script>

<?php
get_footer();
