<div class="wrap">
    <h1>Catlaq Onboarding Wizard</h1>
    <?php
    $steps      = \Catlaq\Expo\Onboarding::steps();
    $total      = count( $steps );
    $current    = min( (int) $step, $total - 1 );
    $label      = $steps[ $current ] ?? '';
    ?>
    <p>Current step: <strong><?php echo esc_html( $current + 1 ); ?> / <?php echo esc_html( $total ); ?> - <?php echo esc_html( $label ); ?></strong></p>
    <form method="post" data-catlaq-onboarding data-user-id="<?php echo get_current_user_id(); ?>">
        <?php wp_nonce_field( 'catlaq_onboarding', 'catlaq_onboarding_nonce' ); ?>
        <p>
            <button class="button button-primary" name="advance_step" value="1">Advance Step</button>
            <button class="button" name="reset_step" value="1">Reset</button>
        </p>
    </form>
    <ol>
        <?php foreach ( $steps as $index => $name ) : ?>
            <li <?php echo $index <= $step ? 'style="font-weight:bold;"' : ''; ?>>
                <?php echo esc_html( $name ); ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <div class="catlaq-onboarding-status" data-catlaq-onboarding-status></div>
</div>

