<section class="catlaq-expo">
    <div class="catlaq-expo__header">
        <h2><?php esc_html_e( 'Expo Booths', 'catlaq-online-expo' ); ?></h2>
        <p><?php esc_html_e( 'Live showcase of premium partners and upcoming sessions.', 'catlaq-online-expo' ); ?></p>
    </div>

    <?php if ( empty( $booths ) ) : ?>
        <p class="catlaq-expo__empty"><?php esc_html_e( 'No booths have been published yet.', 'catlaq-online-expo' ); ?></p>
    <?php else : ?>
        <div class="catlaq-expo-grid">
            <?php foreach ( $booths as $booth ) : ?>
                <article class="catlaq-expo-card sponsorship-<?php echo esc_attr( $booth['sponsorship_level'] ); ?>">
                    <header>
                        <p class="catlaq-expo-card__sponsor"><?php echo esc_html( ucfirst( $booth['sponsorship_level'] ) ); ?></p>
                        <h3><?php echo esc_html( $booth['title'] ); ?></h3>
                    </header>
                    <p><?php echo wp_kses_post( wp_trim_words( $booth['description'], 30 ) ); ?></p>

                    <?php if ( ! empty( $booth['analytics'] ) ) : ?>
                        <ul class="catlaq-expo-card__metrics">
                            <?php foreach ( $booth['analytics'] as $label => $value ) : ?>
                                <li><strong><?php echo esc_html( $value ); ?></strong> <?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( ! empty( $booth['sessions'] ) ) : ?>
                        <div class="catlaq-expo-card__sessions">
                            <h4><?php esc_html_e( 'Live Sessions', 'catlaq-online-expo' ); ?></h4>
                            <?php echo do_shortcode( '[catlaq_expo_sessions booth_id="' . (int) $booth['id'] . '"]' ); ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
