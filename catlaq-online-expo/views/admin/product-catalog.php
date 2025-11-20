<?php
/**
 * @var array $products
 * @var array $companies
 */

$field_value = static function ( string $key, string $default = '' ): string {
    return isset( $_POST[ $key ] )
        ? esc_attr( wp_unslash( is_scalar( $_POST[ $key ] ) ? $_POST[ $key ] : $default ) )
        : esc_attr( $default );
};

$array_value = static function ( string $key, int $index ): string {
    if ( empty( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
        return '';
    }

    return esc_attr( wp_unslash( $_POST[ $key ][ $index ] ?? '' ) );
};

$variant_rows = max( 3, isset( $_POST['variant_label'] ) && is_array( $_POST['variant_label'] ) ? count( $_POST['variant_label'] ) : 0 );
$tier_rows    = max( 3, isset( $_POST['tier_label'] ) && is_array( $_POST['tier_label'] ) ? count( $_POST['tier_label'] ) : 0 );
?>
<div class="wrap catlaq-product-catalog">
    <h1><?php esc_html_e( 'Digital Expo Booths', 'catlaq-online-expo' ); ?></h1>

    <section class="catlaq-product-catalog__list">
        <h2><?php esc_html_e( 'Booth Overview', 'catlaq-online-expo' ); ?></h2>
        <?php if ( empty( $products ) ) : ?>
            <p><?php esc_html_e( 'No Expo booths yet. Use the form below to register your first showcase and products.', 'catlaq-online-expo' ); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Company', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Product', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'SKU', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Base Price', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Variants', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Updated', 'catlaq-online-expo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $products as $product ) : ?>
                    <tr>
                        <td><?php echo esc_html( $product['company_name'] ?? '—' ); ?></td>
                        <td><?php echo esc_html( $product['name'] ); ?></td>
                        <td><?php echo esc_html( $product['sku'] ?: '—' ); ?></td>
                        <td>
                            <?php
                            $price = floatval( $product['base_price'] );
                            echo esc_html(
                                $price > 0
                                    ? sprintf( '%s %s', number_format_i18n( $price, 2 ), strtoupper( $product['currency'] ?? 'USD' ) )
                                    : __( 'Not set', 'catlaq-online-expo' )
                            );
                            ?>
                        </td>
                        <td><?php echo esc_html( $product['variant_count'] ?? 0 ); ?></td>
                        <td><?php echo esc_html( ucfirst( $product['status'] ) ); ?></td>
                        <td><?php echo esc_html( $product['updated_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <hr class="wp-header-end" />

    <section class="catlaq-product-catalog__form">
        <h2><?php esc_html_e( 'Register Product', 'catlaq-online-expo' ); ?></h2>
        <p><?php esc_html_e( 'Catalog entries drive RFQs, expo booths and agreement templates. Capture as much structured data as possible.', 'catlaq-online-expo' ); ?></p>

        <form method="post">
            <?php wp_nonce_field( 'catlaq_product_catalog', 'catlaq_product_catalog_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="company_id"><?php esc_html_e( 'Owning company', 'catlaq-online-expo' ); ?></label></th>
                    <td>
                        <select name="company_id" id="company_id" required>
                            <option value=""><?php esc_html_e( 'Select company…', 'catlaq-online-expo' ); ?></option>
                            <?php foreach ( $companies as $id => $label ) : ?>
                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : '', $id ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( empty( $companies ) ) : ?>
                            <p class="description"><?php esc_html_e( 'No companies detected yet. Add one via the onboarding wizard before registering products.', 'catlaq-online-expo' ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_name"><?php esc_html_e( 'Product name', 'catlaq-online-expo' ); ?></label></th>
                    <td><input type="text" name="product_name" id="product_name" class="regular-text" required value="<?php echo $field_value( 'product_name' ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_sku"><?php esc_html_e( 'Master SKU', 'catlaq-online-expo' ); ?></label></th>
                    <td><input type="text" name="product_sku" id="product_sku" class="regular-text" value="<?php echo $field_value( 'product_sku' ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Unit & MOQ', 'catlaq-online-expo' ); ?></th>
                    <td>
                        <input type="text" name="product_unit" placeholder="<?php esc_attr_e( 'Unit (e.g. kg, pcs)', 'catlaq-online-expo' ); ?>" value="<?php echo $field_value( 'product_unit' ); ?>">
                        <input type="number" step="0.01" name="product_moq" placeholder="<?php esc_attr_e( 'Minimum order qty', 'catlaq-online-expo' ); ?>" value="<?php echo $field_value( 'product_moq' ); ?>">
                        <input type="number" name="product_lead_time" placeholder="<?php esc_attr_e( 'Lead time (days)', 'catlaq-online-expo' ); ?>" value="<?php echo $field_value( 'product_lead_time' ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Base price', 'catlaq-online-expo' ); ?></th>
                    <td>
                        <input type="number" step="0.01" name="product_base_price" value="<?php echo $field_value( 'product_base_price' ); ?>" placeholder="0.00">
                        <input type="text" name="product_currency" value="<?php echo $field_value( 'product_currency', 'USD' ); ?>" class="small-text" maxlength="3">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Status & Visibility', 'catlaq-online-expo' ); ?></th>
                    <td>
                        <select name="product_status">
                            <?php
                            $statuses = [
                                'draft'      => __( 'Draft', 'catlaq-online-expo' ),
                                'published'  => __( 'Published', 'catlaq-online-expo' ),
                                'archived'   => __( 'Archived', 'catlaq-online-expo' ),
                            ];
                            $current_status = $_POST['product_status'] ?? 'draft';
                            foreach ( $statuses as $value => $label ) :
                                ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="product_visibility">
                            <?php
                            $visibilities = [
                                'private' => __( 'Private (internal only)', 'catlaq-online-expo' ),
                                'catalog' => __( 'Digital Expo catalog (RFQ enabled)', 'catlaq-online-expo' ),
                                'expo'    => __( 'Expo spotlight', 'catlaq-online-expo' ),
                            ];
                            $current_visibility = $_POST['product_visibility'] ?? 'private';
                            foreach ( $visibilities as $value => $label ) :
                                ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_visibility, $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="linked_wp_post"><?php esc_html_e( 'Link existing product post', 'catlaq-online-expo' ); ?></label></th>
                    <td>
                        <input type="number" name="linked_wp_post" id="linked_wp_post" value="<?php echo $field_value( 'linked_wp_post' ); ?>" class="small-text">
                        <label><input type="checkbox" name="create_wp_post" value="1" <?php checked( ! empty( $_POST['create_wp_post'] ) ); ?>> <?php esc_html_e( 'Create Expo showcase post automatically', 'catlaq-online-expo' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Linking ensures Expo cards display pricing & imagery.', 'catlaq-online-expo' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_highlights"><?php esc_html_e( 'Highlights / Description', 'catlaq-online-expo' ); ?></label></th>
                    <td><textarea name="product_highlights" id="product_highlights" class="large-text" rows="3"><?php echo esc_textarea( wp_unslash( $_POST['product_highlights'] ?? '' ) ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="product_notes"><?php esc_html_e( 'Internal notes', 'catlaq-online-expo' ); ?></label></th>
                    <td><textarea name="product_notes" id="product_notes" class="large-text" rows="3"><?php echo esc_textarea( wp_unslash( $_POST['product_notes'] ?? '' ) ); ?></textarea></td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Variants', 'catlaq-online-expo' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Provide up to three variants at once. Attributes accept JSON (e.g. {"color":"Navy","size":"M"}).', 'catlaq-online-expo' ); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Label', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'SKU', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Attributes (JSON)', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Stock qty', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Unit price', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Currency', 'catlaq-online-expo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php for ( $i = 0; $i < $variant_rows; $i++ ) : ?>
                    <tr>
                        <td><input type="text" name="variant_label[]" value="<?php echo $array_value( 'variant_label', $i ); ?>" placeholder="<?php esc_attr_e( 'Variant label', 'catlaq-online-expo' ); ?>"></td>
                        <td><input type="text" name="variant_sku[]" value="<?php echo $array_value( 'variant_sku', $i ); ?>"></td>
                        <td><input type="text" name="variant_attributes[]" value="<?php echo $array_value( 'variant_attributes', $i ); ?>"></td>
                        <td>
                            <input type="number" step="0.01" name="variant_stock_qty[]" value="<?php echo $array_value( 'variant_stock_qty', $i ); ?>" class="small-text">
                            <input type="text" name="variant_stock_unit[]" value="<?php echo $array_value( 'variant_stock_unit', $i ); ?>" placeholder="<?php esc_attr_e( 'Unit', 'catlaq-online-expo' ); ?>" class="small-text">
                        </td>
                        <td><input type="number" step="0.01" name="variant_unit_price[]" value="<?php echo $array_value( 'variant_unit_price', $i ); ?>" class="small-text"></td>
                        <td><input type="text" name="variant_currency[]" value="<?php echo $array_value( 'variant_currency', $i ) ?: 'USD'; ?>" class="small-text" maxlength="3"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Price tiers', 'catlaq-online-expo' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Optional quantity breaks. Use the variant label column to tie pricing to a specific variant; leave blank to apply to the master product.', 'catlaq-online-expo' ); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Variant label', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Min qty', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Max qty', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Unit price', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Currency', 'catlaq-online-expo' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'catlaq-online-expo' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php for ( $i = 0; $i < $tier_rows; $i++ ) : ?>
                    <tr>
                        <td><input type="text" name="tier_label[]" value="<?php echo $array_value( 'tier_label', $i ); ?>"></td>
                        <td><input type="number" step="0.01" name="tier_min_qty[]" value="<?php echo $array_value( 'tier_min_qty', $i ); ?>" class="small-text"></td>
                        <td><input type="number" step="0.01" name="tier_max_qty[]" value="<?php echo $array_value( 'tier_max_qty', $i ); ?>" class="small-text"></td>
                        <td><input type="number" step="0.01" name="tier_unit_price[]" value="<?php echo $array_value( 'tier_unit_price', $i ); ?>" class="small-text"></td>
                        <td><input type="text" name="tier_currency[]" value="<?php echo $array_value( 'tier_currency', $i ) ?: 'USD'; ?>" class="small-text" maxlength="3"></td>
                        <td><input type="text" name="tier_notes[]" value="<?php echo $array_value( 'tier_notes', $i ); ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>

            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save product', 'catlaq-online-expo' ); ?></button></p>
        </form>
    </section>
</div>
