<?php
/**
 * @var array  $templates
 * @var array  $recent
 * @var array  $signatures
 * @var string $json_value
 */

$template_options = '';
foreach ( $templates as $template ) {
    $template_key   = esc_attr( $template['key'] );
    $template_label = esc_html( $template['label'] ?? $template['key'] );
    $template_desc  = esc_html( $template['description'] ?? '' );
    $template_options .= sprintf(
        '<option value="%s">%s%s</option>',
        $template_key,
        $template_label,
        $template_desc ? ' – ' . $template_desc : ''
    );
}
?>
<div class="wrap catlaq-documents-portal">
    <h1><?php esc_html_e( 'Documents Portal', 'catlaq-online-expo' ); ?></h1>

    <p><?php esc_html_e( 'List available templates, generate documents, and track signature workflow.', 'catlaq-online-expo' ); ?></p>

    <div class="catlaq-documents-portal__grid">
        <div class="catlaq-documents-portal__column">
            <h2><?php esc_html_e( 'Generate Document', 'catlaq-online-expo' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'catlaq_documents_portal', 'catlaq_documents_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="room_id"><?php esc_html_e( 'Agreement room ID', 'catlaq-online-expo' ); ?></label></th>
                        <td><input type="number" name="room_id" id="room_id" class="regular-text" value="<?php echo isset( $_POST['room_id'] ) ? esc_attr( wp_unslash( $_POST['room_id'] ) ) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="template_key"><?php esc_html_e( 'Template', 'catlaq-online-expo' ); ?></label></th>
                        <td>
                            <select name="template_key" id="template_key" required>
                                <option value=""><?php esc_html_e( 'Select template…', 'catlaq-online-expo' ); ?></option>
                                <?php echo $template_options; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="document_context"><?php esc_html_e( 'Context (JSON)', 'catlaq-online-expo' ); ?></label></th>
                        <td>
                            <textarea name="document_context" id="document_context" class="large-text code" rows="6" placeholder='{"line_items":[],"incoterms":"FOB"}'><?php echo esc_textarea( $json_value ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Optional overrides for auto fields.', 'catlaq-online-expo' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Generate document', 'catlaq-online-expo' ); ?></button></p>
            </form>
        </div>

        <div class="catlaq-documents-portal__column">
            <h2><?php esc_html_e( 'Available Templates', 'catlaq-online-expo' ); ?></h2>
            <?php if ( empty( $templates ) ) : ?>
                <p><?php esc_html_e( 'No templates accessible for your role.', 'catlaq-online-expo' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Key', 'catlaq-online-expo' ); ?></th>
                            <th><?php esc_html_e( 'Label', 'catlaq-online-expo' ); ?></th>
                            <th><?php esc_html_e( 'Category', 'catlaq-online-expo' ); ?></th>
                            <th><?php esc_html_e( 'Auto fields', 'catlaq-online-expo' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $templates as $template ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $template['key'] ); ?></code></td>
                            <td><?php echo esc_html( $template['label'] ?? '' ); ?></td>
                            <td><?php echo esc_html( ucfirst( $template['category'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( implode( ', ', (array) ( $template['auto_fields'] ?? [] ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <h2><?php esc_html_e( 'Recent Documents', 'catlaq-online-expo' ); ?></h2>
    <?php if ( empty( $recent ) ) : ?>
        <p><?php esc_html_e( 'No documents generated yet.', 'catlaq-online-expo' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Room', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Template', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'PDF', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'HTML', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'catlaq-online-expo' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $recent as $doc ) : ?>
                <tr>
                    <td><?php echo esc_html( $doc['id'] ); ?></td>
                    <td><?php echo esc_html( $doc['room_id'] ); ?></td>
                    <td><?php echo esc_html( $doc['template_key'] ); ?></td>
                    <td><?php echo esc_html( ucfirst( $doc['signature_status'] ?? 'draft' ) ); ?></td>
                    <td>
                        <?php if ( ! empty( $doc['pdf_url'] ?? $doc['pdf_path'] ?? $doc['file_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $doc['pdf_url'] ?? $doc['pdf_path'] ?? $doc['file_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download', 'catlaq-online-expo' ); ?></a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! empty( $doc['html_url'] ?? $doc['html_path'] ) ) : ?>
                            <a href="<?php echo esc_url( $doc['html_url'] ?? $doc['html_path'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'catlaq-online-expo' ); ?></a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $doc['created_at'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<hr>

<div class="catlaq-documents-signatures">
    <h2><?php esc_html_e( 'Request Signature', 'catlaq-online-expo' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'catlaq_documents_signature', 'catlaq_documents_signature_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="signature_document_id"><?php esc_html_e( 'Document ID', 'catlaq-online-expo' ); ?></label></th>
                <td><input type="number" name="signature_document_id" id="signature_document_id" class="small-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="signature_user_id"><?php esc_html_e( 'Signer User ID', 'catlaq-online-expo' ); ?></label></th>
                <td><input type="number" name="signature_user_id" id="signature_user_id" class="small-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="signature_email"><?php esc_html_e( 'Signer Email', 'catlaq-online-expo' ); ?></label></th>
                <td><input type="email" name="signature_email" id="signature_email" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="signature_role"><?php esc_html_e( 'Role', 'catlaq-online-expo' ); ?></label></th>
                <td>
                    <select name="signature_role" id="signature_role">
                        <option value="buyer"><?php esc_html_e( 'Buyer', 'catlaq-online-expo' ); ?></option>
                        <option value="seller"><?php esc_html_e( 'Seller', 'catlaq-online-expo' ); ?></option>
                        <option value="broker"><?php esc_html_e( 'Broker', 'catlaq-online-expo' ); ?></option>
                        <option value="admin"><?php esc_html_e( 'Admin', 'catlaq-online-expo' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <p><button type="submit" class="button"><?php esc_html_e( 'Send request', 'catlaq-online-expo' ); ?></button></p>
    </form>

    <h2><?php esc_html_e( 'Recent Signatures', 'catlaq-online-expo' ); ?></h2>
    <?php if ( empty( $signatures ) ) : ?>
        <p><?php esc_html_e( 'No signature activity yet.', 'catlaq-online-expo' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Document', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Signer', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Signed At', 'catlaq-online-expo' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'catlaq-online-expo' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $signatures as $signature ) : ?>
                <tr>
                    <td><?php echo esc_html( $signature['id'] ); ?></td>
                    <td><?php echo esc_html( $signature['document_id'] ); ?></td>
                    <td><?php echo esc_html( $signature['signer_email'] ?: $signature['signer_id'] ); ?></td>
                    <td><?php echo esc_html( ucfirst( $signature['role'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( ucfirst( $signature['status'] ?? 'pending' ) ); ?></td>
                    <td><?php echo esc_html( $signature['signed_at'] ?? '—' ); ?></td>
                    <td>
                        <form method="post">
                            <?php wp_nonce_field( 'catlaq_documents_signature_complete', 'catlaq_documents_signature_complete_nonce' ); ?>
                            <input type="hidden" name="complete_signature_id" value="<?php echo esc_attr( $signature['id'] ); ?>">
                            <input type="hidden" name="complete_signature_status" value="signed">
                            <input type="hidden" name="complete_signature_note" value="">
                            <button type="submit" class="button button-small"><?php esc_html_e( 'Mark signed', 'catlaq-online-expo' ); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
