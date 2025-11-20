<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e( 'Packing List', 'catlaq-online-expo' ); ?></title>
    <style>
        body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; margin: 32px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #f9f9f9; }
    </style>
</head>
<body>
    <h1><?php esc_html_e( 'Packing List', 'catlaq-online-expo' ); ?></h1>
    <p>
        <?php esc_html_e( 'Shipment Reference:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $payload['shipment_ref'] ?? '' ); ?><br>
        <?php esc_html_e( 'Destination:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $payload['destination'] ?? '' ); ?>
    </p>

    <table>
        <thead>
            <tr>
                <th><?php esc_html_e( 'Package #', 'catlaq-online-expo' ); ?></th>
                <th><?php esc_html_e( 'Description', 'catlaq-online-expo' ); ?></th>
                <th><?php esc_html_e( 'Dimensions (cm)', 'catlaq-online-expo' ); ?></th>
                <th><?php esc_html_e( 'Weight (kg)', 'catlaq-online-expo' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) ( $payload['packages'] ?? [] ) as $index => $package ) : ?>
            <tr>
                <td><?php echo esc_html( $index + 1 ); ?></td>
                <td><?php echo esc_html( $package['description'] ?? '' ); ?></td>
                <td><?php echo esc_html( $package['dimensions'] ?? '' ); ?></td>
                <td><?php echo esc_html( $package['weight'] ?? '' ); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <?php esc_html_e( 'Total Weight:', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $auto_fields['weight'] ?? $payload['total_weight'] ?? '' ); ?><br>
        <?php esc_html_e( 'Total Volume (CBM):', 'catlaq-online-expo' ); ?>
        <?php echo esc_html( $auto_fields['desi'] ?? $payload['volume_cbm'] ?? '' ); ?>
    </p>
</body>
</html>
