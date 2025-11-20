<?php
/**
 * Catlaq AI Control Center.
 *
 * @var array  $manifest
 * @var string $model_path
 * @var string $model_hash
 * @var string $secret_hash
 * @var array  $logs
 * @var string $message
 * @var array|null $membership_plan
 * @var array $membership_quotas
 * @var string $provider
 * @var string $endpoint
 * @var string $model_name
 * @var string $coach_question
 * @var string $coach_output
 * @var string $coach_error
 * @var string $bot_question
 * @var string $bot_output
 * @var string $bot_error
 */
?>

<div class="wrap catlaq-ai-center">
	<h1><?php esc_html_e( 'Catlaq AI Control Center', 'catlaq-online-expo' ); ?></h1>

	<?php if ( ! empty( $message ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
	<?php endif; ?>

	<div class="card">
		<h2><?php esc_html_e( 'Model Configuration', 'catlaq-online-expo' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'catlaq_ai_save_model', 'catlaq_ai_model_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="catlaq_ai_model_path"><?php esc_html_e( 'Model Path', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" name="catlaq_ai_model_path" id="catlaq_ai_model_path" value="<?php echo esc_attr( $model_path ); ?>" />
						<p class="description"><?php esc_html_e( 'Absolute path inside wp-content/catlaq-ai/models/. Upload large models via SFTP.', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="catlaq_ai_provider"><?php esc_html_e( 'Provider', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<select name="catlaq_ai_provider" id="catlaq_ai_provider">
							<option value="local_http" <?php selected( $provider, 'local_http' ); ?>><?php esc_html_e( 'Local HTTP runtime (Ollama / llama.cpp server)', 'catlaq-online-expo' ); ?></option>
							<option value="disabled" <?php selected( $provider, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'catlaq-online-expo' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Select how Catlaq connects to the language model.', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="catlaq_ai_endpoint"><?php esc_html_e( 'HTTP Endpoint', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" name="catlaq_ai_endpoint" id="catlaq_ai_endpoint" value="<?php echo esc_attr( $endpoint ); ?>" />
						<p class="description"><?php esc_html_e( 'Example for Ollama: http://127.0.0.1:11434/api/generate', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="catlaq_ai_model_name"><?php esc_html_e( 'Model Name/ID', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="catlaq_ai_model_name" id="catlaq_ai_model_name" value="<?php echo esc_attr( $model_name ); ?>" />
						<p class="description"><?php esc_html_e( 'Name expected by the runtime (e.g. mistral, mixtral-8x7b).', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="catlaq_ai_model_hash"><?php esc_html_e( 'Model Hash', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" name="catlaq_ai_model_hash" id="catlaq_ai_model_hash" value="<?php echo esc_attr( $model_hash ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional SHA256 hash for integrity verification.', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'AI Secret Fingerprint', 'catlaq-online-expo' ); ?></th>
					<td>
						<code><?php echo esc_html( $secret_hash ?: 'n/a' ); ?></code>
						<p class="description"><?php esc_html_e( 'Rotate via WP-CLI: wp catlaq ai rotate-key', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Model Settings', 'catlaq-online-expo' ) ); ?>
		</form>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Manifest Overview', 'catlaq-online-expo' ); ?></h2>
		<p><strong><?php echo esc_html( $manifest['orchestrator']['name'] ?? '' ); ?></strong> — <?php echo esc_html( $manifest['orchestrator']['persona'] ?? '' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Agent', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Scope', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Inputs', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Output', 'catlaq-online-expo' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( (array) ( $manifest['agents'] ?? [] ) as $slug => $agent ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $agent['label'] ?? $slug ); ?></strong><br /><code><?php echo esc_html( $slug ); ?></code></td>
					<td><?php echo esc_html( $agent['scope'] ?? '' ); ?></td>
					<td><?php echo esc_html( implode( ', ', (array) ( $agent['inputs'] ?? [] ) ) ); ?></td>
					<td><?php echo esc_html( implode( ', ', (array) ( $agent['output_json'] ?? [] ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( ! empty( $membership_plan ) ) : ?>
		<div class="card">
			<h2><?php esc_html_e( 'Membership Coach Snapshot', 'catlaq-online-expo' ); ?></h2>
			<p>
				<strong><?php echo esc_html( $membership_plan['label'] ?? '' ); ?></strong>
				<?php if ( ! empty( $membership_plan['slug'] ) ) : ?>
					<code><?php echo esc_html( $membership_plan['slug'] ); ?></code>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $membership_plan['features'] ) ) : ?>
				<ul class="ul-disc">
					<?php foreach ( $membership_plan['features'] as $key => $feature ) : ?>
						<li><?php echo esc_html( is_string( $feature ) ? $feature : sprintf( '%s: %s', $key, wp_json_encode( $feature ) ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Metric', 'catlaq-online-expo' ); ?></th>
						<th><?php esc_html_e( 'Used', 'catlaq-online-expo' ); ?></th>
						<th><?php esc_html_e( 'Limit', 'catlaq-online-expo' ); ?></th>
						<th><?php esc_html_e( 'Remaining', 'catlaq-online-expo' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $membership_quotas ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No quota data available.', 'catlaq-online-expo' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $membership_quotas as $quota ) : ?>
						<tr>
							<td><?php echo esc_html( $quota['metric'] ?? '' ); ?></td>
							<td><?php echo esc_html( $quota['used'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $quota['limit'] ?? '—' ); ?></td>
							<td><?php echo esc_html( isset( $quota['remaining'] ) ? $quota['remaining'] : __( 'Unlimited', 'catlaq-online-expo' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Quota data reflects Membership Coach metrics. AI agents use this snapshot to enforce fair usage.', 'catlaq-online-expo' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="card">
		<h2><?php esc_html_e( 'Ask Membership Coach', 'catlaq-online-expo' ); ?></h2>
		<?php if ( ! empty( $coach_error ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $coach_error ); ?></p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'catlaq_ai_membership_coach', 'catlaq_ai_membership_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="catlaq_ai_membership_question"><?php esc_html_e( 'Scenario / Question', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<textarea name="catlaq_ai_membership_question" id="catlaq_ai_membership_question" rows="4" class="large-text"><?php echo esc_textarea( $coach_question ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional: describe what the member is trying to achieve so the coach can respond.', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Generate Advice', 'catlaq-online-expo' ) ); ?>
		</form>
	<?php if ( ! empty( $coach_output ) ) : ?>
			<h3><?php esc_html_e( 'Coach Response', 'catlaq-online-expo' ); ?></h3>
			<div class="notice notice-info"><pre><?php echo esc_html( $coach_output ); ?></pre></div>
	<?php endif; ?>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Catlaq Admin Chatbot', 'catlaq-online-expo' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Ask the AI about Catlaq operations, deployments, or upcoming tasks. Responses are logged for auditing.', 'catlaq-online-expo' ); ?></p>
		<?php if ( ! empty( $bot_error ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $bot_error ); ?></p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'catlaq_ai_admin_bot', 'catlaq_ai_admin_bot_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="catlaq_ai_admin_bot_question"><?php esc_html_e( 'Request', 'catlaq-online-expo' ); ?></label></th>
					<td>
						<textarea name="catlaq_ai_admin_bot_question" id="catlaq_ai_admin_bot_question" rows="4" class="large-text"><?php echo esc_textarea( $bot_question ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Describe what you want the AI to review (deploy plan, modules to audit, etc.).', 'catlaq-online-expo' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Ask Catlaq AI', 'catlaq-online-expo' ) ); ?>
		</form>
		<?php if ( ! empty( $bot_output ) ) : ?>
			<h3><?php esc_html_e( 'AI Response', 'catlaq-online-expo' ); ?></h3>
			<div class="notice notice-info"><pre><?php echo esc_html( $bot_output ); ?></pre></div>
		<?php endif; ?>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Recent AI Logs', 'catlaq-online-expo' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Agent', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'User', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Context', 'catlaq-online-expo' ); ?></th>
					<th><?php esc_html_e( 'Payload', 'catlaq-online-expo' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No AI logs captured yet.', 'catlaq-online-expo' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['created_at'] ?? '' ); ?></td>
						<td><?php echo esc_html( $log['agent'] ?? '' ); ?></td>
						<td>
							<?php
							if ( ! empty( $log['user_id'] ) ) {
								printf( '#%d', (int) $log['user_id'] );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td><?php echo esc_html( $log['context'] ?? '' ); ?></td>
						<td>
							<code><?php echo esc_html( wp_json_encode( $log['payload'] ?? [] ) ); ?></code>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'For key rotation and detailed telemetry export use WP-CLI commands.', 'catlaq-online-expo' ); ?></p>
	</div>
</div>
