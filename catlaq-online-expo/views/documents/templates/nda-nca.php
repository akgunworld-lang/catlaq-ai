<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e( 'NDA & NCA Agreement', 'catlaq-online-expo' ); ?></title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; margin: 50px; color: #222; line-height: 1.7; }
        h1 { text-align: center; text-transform: uppercase; letter-spacing: 0.1em; }
        h2 { margin-top: 32px; }
        p { margin-bottom: 16px; }
        ul { margin-left: 20px; }
    </style>
</head>
<body>
    <h1><?php esc_html_e( 'Non-Disclosure & Non-Circumvention Agreement', 'catlaq-online-expo' ); ?></h1>
    <p>
        <?php
        printf(
            esc_html__( 'This agreement is entered into between %1$s and %2$s on %3$s.', 'catlaq-online-expo' ),
            esc_html( $payload['party_a'] ?? __( 'Party A', 'catlaq-online-expo' ) ),
            esc_html( $payload['party_b'] ?? __( 'Party B', 'catlaq-online-expo' ) ),
            esc_html( $auto_fields['effective_date'] ?? $generated['at'] ?? '' )
        );
        ?>
    </p>

    <h2><?php esc_html_e( 'Confidential Information', 'catlaq-online-expo' ); ?></h2>
    <p><?php esc_html_e( 'All materials shared within Catlaq agreement rooms, including documents, pricing, logistics data and AI summaries, are deemed confidential.', 'catlaq-online-expo' ); ?></p>

    <h2><?php esc_html_e( 'Non-Circumvention', 'catlaq-online-expo' ); ?></h2>
    <p><?php esc_html_e( 'The receiving party shall not bypass Catlaq or any broker/agent introduced through the platform for the purpose of entering into direct agreements with suppliers or buyers introduced in the course of these discussions.', 'catlaq-online-expo' ); ?></p>

    <h2><?php esc_html_e( 'Term', 'catlaq-online-expo' ); ?></h2>
    <p>
        <?php
        printf(
            esc_html__( 'This agreement remains in effect for %s months from the effective date.', 'catlaq-online-expo' ),
            esc_html( $auto_fields['duration'] ?? '24' )
        );
        ?>
    </p>

    <h2><?php esc_html_e( 'Signatures', 'catlaq-online-expo' ); ?></h2>
    <p><?php esc_html_e( 'By proceeding within the Catlaq platform, both parties acknowledge acceptance of this NDA & NCA.', 'catlaq-online-expo' ); ?></p>
</body>
</html>
