<?php
use Catlaq\Expo\Settings;
use Catlaq\Expo\Memberships;
use Catlaq\Expo\AI\Manifest as AI_Manifest;
use Catlaq\Expo\AI_Kernel;

$settings = Settings::get();
$endpoint = rest_url( 'catlaq/v1/status' );
$status   = rest_do_request( '/catlaq/v1/status' );
$status_data = [];
if ( ! $status->is_error() ) {
    $status_data = $status->get_data();
}

$memberships = new Memberships();
$plan        = $memberships->user_membership( get_current_user_id() );
$quotas      = [];
if ( $plan ) {
    foreach ( (array) ( $plan['quotas'] ?? [] ) as $metric => $limit ) {
        $quotas[] = $memberships->quota_status( get_current_user_id(), $metric, (int) $limit );
    }
}

$manifest = AI_Manifest::get();
$runtime  = AI_Kernel::instance()->runtime_config();
?>

<style>
	.catlaq-admin-dashboard .catlaq-widgets {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
		gap: 1rem;
		margin-top: 1rem;
	}
</style>

<div class="wrap catlaq-admin-dashboard">
	<h1><?php esc_html_e( 'Catlaq Admin Dashboard', 'catlaq-online-expo' ); ?></h1>

	<div class="catlaq-widgets">
		<div class="catlaq-widget card">
			<h2><?php esc_html_e( 'System Health', 'catlaq-online-expo' ); ?></h2>
			<p><strong><?php esc_html_e( 'Plugin Version:', 'catlaq-online-expo' ); ?></strong> <?php echo esc_html( CATLAQ_PLUGIN_VERSION ); ?></p>
			<p><strong><?php esc_html_e( 'Environment:', 'catlaq-online-expo' ); ?></strong> <?php echo esc_html( $settings['environment'] ?? 'development' ); ?></p>
			<p><strong><?php esc_html_e( 'Status Endpoint:', 'catlaq-online-expo' ); ?></strong> <code><?php echo esc_html( $endpoint ); ?></code></p>
			<?php if ( ! empty( $status_data ) ) : ?>
				<p><strong><?php esc_html_e( 'Missing Tables:', 'catlaq-online-expo' ); ?></strong> <?php echo empty( $status_data['missing_tables'] ) ? esc_html__( 'None', 'catlaq-online-expo' ) : esc_html( implode( ', ', $status_data['missing_tables'] ) ); ?></p>
			<?php endif; ?>
		</div>

		<div class="catlaq-widget card">
			<h2><?php esc_html_e( 'AI Runtime', 'catlaq-online-expo' ); ?></h2>
			<p><strong><?php esc_html_e( 'Provider:', 'catlaq-online-expo' ); ?></strong> <?php echo esc_html( $runtime['provider'] ?? 'disabled' ); ?></p>
			<p><strong><?php esc_html_e( 'Endpoint:', 'catlaq-online-expo' ); ?></strong> <code><?php echo esc_html( $runtime['endpoint'] ?? '' ); ?></code></p>
			<p><strong><?php esc_html_e( 'Model:', 'catlaq-online-expo' ); ?></strong> <?php echo esc_html( $runtime['model_name'] ?? '' ); ?></p>
			<p><strong><?php esc_html_e( 'Agents:', 'catlaq-online-expo' ); ?></strong> <?php echo esc_html( implode( ', ', array_keys( $manifest['agents'] ?? [] ) ) ); ?></p>
		</div>

		<div class="catlaq-widget card">
			<h2><?php esc_html_e( 'Membership Snapshot', 'catlaq-online-expo' ); ?></h2>
			<?php if ( $plan ) : ?>
				<p><strong><?php esc_html_e( 'Plan:', 'catlaq-online-expo' ); ?></strong> <?php echo esc_html( $plan['label'] ?? '' ); ?> <code><?php echo esc_html( $plan['slug'] ?? '' ); ?></code></p>
				<?php if ( ! empty( $quotas ) ) : ?>
					<ul>
						<?php foreach ( $quotas as $quota ) : ?>
							<li><?php echo esc_html( sprintf( '%s: %d / %s', $quota['metric'] ?? '', (int) ( $quota['used'] ?? 0 ), isset( $quota['limit'] ) ? (string) $quota['limit'] : __( 'Unlimited', 'catlaq-online-expo' ) ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'No membership plan assigned to your admin user.', 'catlaq-online-expo' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Recent AI Logs', 'catlaq-online-expo' ); ?></h2>
		<?php
		global $wpdb;
		$table = "{$wpdb->prefix}catlaq_ai_logs";
		$logs  = [];
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			$logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 5", ARRAY_A );
		}
		if ( empty( $logs ) ) :
			?>
			<p><?php esc_html_e( 'No AI log entries yet.', 'catlaq-online-expo' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Agent', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Context', 'catlaq-online-expo' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['created_at'] ?? '' ); ?></td>
						<td><?php echo esc_html( $log['agent'] ?? '' ); ?></td>
						<td><?php echo esc_html( $log['context'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

