<div class="wrap">
    <h1>Catlaq Settings</h1>
    <form method="post">
        <?php wp_nonce_field( 'catlaq_save_settings', 'catlaq_settings_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="environment">Environment</label></th>
                <td>
                    <select id="environment" name="environment">
                        <option value="development" <?php selected( $settings['environment'], 'development' ); ?>>Development</option>
                        <option value="staging" <?php selected( $settings['environment'], 'staging' ); ?>>Staging</option>
                        <option value="production" <?php selected( $settings['environment'], 'production' ); ?>>Production</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ai_provider">AI Provider</label></th>
                <td>
                    <select id="ai_provider" name="ai_provider">
                        <option value="local" <?php selected( $settings['ai_provider'], 'local' ); ?>>Local (JSON)</option>
                        <option value="remote" <?php selected( $settings['ai_provider'], 'remote' ); ?>>Remote API</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="escrow_api_key">Escrow API Key</label></th>
                <td>
                    <input type="text" id="escrow_api_key" name="escrow_api_key" value="<?php echo esc_attr( $settings['escrow_api_key'] ); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="payment_provider">Payment Provider</label></th>
                <td>
                    <select id="payment_provider" name="payment_provider">
                        <option value="mock" <?php selected( $settings['payment_provider'], 'mock' ); ?>>Mock / Sandbox</option>
                        <option value="stripe" <?php selected( $settings['payment_provider'], 'stripe' ); ?>>Stripe</option>
                        <option value="checkout" <?php selected( $settings['payment_provider'], 'checkout' ); ?>>Checkout.com</option>
                        <option value="worldfirst" <?php selected( $settings['payment_provider'], 'worldfirst' ); ?>>WorldFirst</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="payment_webhook_secret">Webhook Secret</label></th>
                <td>
                    <input type="text" id="payment_webhook_secret" name="payment_webhook_secret" value="<?php echo esc_attr( $settings['payment_webhook_secret'] ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Webhook imzalar?n? do?rulamak i?in kullan?l?r.', 'catlaq-online-expo' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="worldfirst_partner_id"><?php esc_html_e( 'WorldFirst Partner ID', 'catlaq-online-expo' ); ?></label></th>
                <td>
                    <input type="text" id="worldfirst_partner_id" name="worldfirst_partner_id" value="<?php echo esc_attr( $settings['worldfirst_partner_id'] ?? '' ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Enter the Partner ID assigned by your WorldFirst account manager.', 'catlaq-online-expo' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="worldfirst_api_key"><?php esc_html_e( 'WorldFirst API Key', 'catlaq-online-expo' ); ?></label></th>
                <td>
                    <input type="text" id="worldfirst_api_key" name="worldfirst_api_key" value="<?php echo esc_attr( $settings['worldfirst_api_key'] ?? '' ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Used for generating WorldFirst payment links for membership invoices.', 'catlaq-online-expo' ); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
