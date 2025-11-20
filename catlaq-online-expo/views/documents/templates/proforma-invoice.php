<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html( sprintf( '%s / %s', __( 'Proforma Invoice', 'catlaq-online-expo' ), $payload['order_number'] ?? '' ) ); ?></title>
    <style>
        body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; margin: 40px; color: #1f1f1f; }
        h1 { text-transform: uppercase; letter-spacing: 0.08em; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f3f3f3; }
        .totals { text-align: right; font-weight: bold; }
        .meta { margin-top: 16px; color: #555; }
    </style>
</head>
<body>
    <h1><?php esc_html_e( 'Proforma Invoice', 'catlaq-online-expo' ); ?></h1>
    <p class="meta">
        <?php esc_html_e( 'Issued to:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $payload['buyer_name'] ?? '' ); ?><br>
        <?php esc_html_e( 'Seller:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $payload['seller_name'] ?? '' ); ?><br>
        <?php esc_html_e( 'Incoterms:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $auto_fields['incoterms'] ?? $payload['incoterms'] ?? 'FOB' ); ?><br>
        <?php esc_html_e( 'Currency:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $payload['currency'] ?? 'USD' ); ?>
    </p>

    <table>
        <thead>
            <tr>
                <th><?php esc_html_e( 'Description', 'catlaq-online-expo' ); ?></th>
                <th><?php esc_html_e( 'Qty', 'catlaq-online-expo' ); ?></th>
                <th><?php esc_html_e( 'Unit Price', 'catlaq-online-expo' ); ?></th>
                <th><?php esc_html_e( 'Line Total', 'catlaq-online-expo' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) ( $payload['line_items'] ?? [] ) as $item ) : ?>
            <tr>
                <td><?php echo esc_html( $item['description'] ?? '' ); ?></td>
                <td><?php echo esc_html( $item['quantity'] ?? '' ); ?></td>
                <td><?php echo esc_html( $item['unit_price'] ?? '' ); ?></td>
                <td><?php echo esc_html( $item['line_total'] ?? '' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="totals"><?php esc_html_e( 'Total Amount', 'catlaq-online-expo' ); ?></td>
                <td class="totals"><?php echo esc_html( $payload['total_amount'] ?? '' ); ?></td>
            </tr>
        </tfoot>
    </table>

    <p class="meta">
        <?php esc_html_e( 'Payment milestones:', 'catlaq-online-expo' ); ?><br>
        <?php
        foreach ( (array) ( $auto_fields['milestones'] ?? [] ) as $milestone ) {
            echo esc_html( sprintf( '%s - %s%%', $milestone['label'] ?? '', $milestone['percent'] ?? '' ) ) . '<br>';
        }
        ?>
    </p>
</body>
</html>
