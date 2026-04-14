<?php
/**
 * NetChat Frontend
 * Author: omhotey
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enqueue assets
 */
add_action( 'wp_enqueue_scripts', 'netchat_enqueue', 20 );
function netchat_enqueue() {
    if ( is_admin() || ! netchat_is_active() ) return;
    if ( in_array( $GLOBALS['pagenow'] ?? '', array( 'wp-login.php', 'wp-register.php', 'wp-activate.php' ), true ) ) return;

    wp_enqueue_style( 'netchat-front', NETCHAT_URL . 'assets/css/style.css', array(), NETCHAT_VER );
    wp_enqueue_script( 'netchat-front', NETCHAT_URL . 'assets/js/front.js', array(), NETCHAT_VER, true );

    $c = netchat_get_config();
    wp_localize_script( 'netchat-front', 'netchat', array(
        'speed'     => intval( $c['animation'] ),
        'hintDelay' => ( intval( $c['hint_delay'] ?? 2 ) ) * 1000,
        'color'     => $c['color'],
        'hintText'  => wp_kses_post( $c['hint_text'] ?? '' ),
    ) );
}

/**
 * Render widget
 */
add_action( 'wp_footer', 'netchat_render', 99 );
function netchat_render() {
    if ( ! netchat_is_active() ) return;
    $c = netchat_get_config();
    $m = netchat_messengers();
    $order = array_filter( array_map( 'trim', explode( ',', $c['order'] ) ) );

    // Position CSS
    $pos = array();
    if ( '' !== $c['position']['top'] ) $pos[] = 'top:' . intval( $c['position']['top'] ) . 'px';
    if ( '' !== $c['position']['left'] ) $pos[] = 'left:' . intval( $c['position']['left'] ) . 'px';
    elseif ( '' !== $c['position']['right'] ) $pos[] = 'right:' . intval( $c['position']['right'] ) . 'px';
    else $pos[] = 'right:20px';
    if ( '' !== $c['position']['bottom'] ) $pos[] = 'bottom:' . intval( $c['position']['bottom'] ) . 'px';
    else $pos[] = 'bottom:20px'; // Default bottom spacing

    $style = implode( ';', $pos );
    $size = intval( $c['size'] );
    $color = $c['color'];

    // Visibility classes
    $cls = '';
    if ( empty( $c['desktop'] ) && ! empty( $c['mobile'] ) ) $cls .= ' nc-hide-desktop';
    elseif ( ! empty( $c['desktop'] ) && empty( $c['mobile'] ) ) $cls .= ' nc-hide-mobile';
    elseif ( empty( $c['desktop'] ) && empty( $c['mobile'] ) ) $cls .= ' nc-hide-all';

    // Build messenger buttons
    $bubble_html = '';
    foreach ( $order as $key ) {
        if ( empty( $c['enabled'][ $key ] ) || empty( $c['links'][ $key ] ) ) continue;
        $name = $m[ $key ]['name'] ?? ucfirst( $key );
        $url  = $c['links'][ $key ];
        $target = in_array( $key, array( 'email', 'phone' ), true ) ? '' : 'target="_blank" rel="noopener noreferrer"';
        $bubble_html .= '<a href="' . esc_url( $url ) . '" ' . $target . ' class="nc-btn nc-' . esc_attr( $key ) . '" data-nc-tooltip="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $name ) . '" role="menuitem" tabindex="-1">';
        $bubble_html .= netchat_svg_icon( $key );
        $bubble_html .= '</a>';
    }

    // Don't render if no active messengers
    if ( empty( $bubble_html ) ) return;
    ?>
    <div id="netchat" class="nc-wrap<?php echo esc_attr( $cls ); ?>" style="<?php echo esc_attr( $style ); ?>;--nc-size:<?php echo esc_attr( $size ); ?>px;--nc-icon:<?php echo esc_attr( round( $size * 0.5 ) ); ?>px;--nc-color:<?php echo esc_attr( $color ); ?>">
        <div class="nc-col">
            <div class="nc-bubble" id="nc-bubble" role="menu" aria-hidden="true">
                <?php echo $bubble_html; // phpcs:ignore ?>
            </div>
            <div class="nc-main-wrap">
                <button type="button" class="nc-btn nc-main" id="nc-toggle" aria-label="<?php echo esc_attr__( 'Open contact menu', 'netchat' ); ?>" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="18" r="2"/></svg>
                </button>
                <?php if ( ! empty( $c['hint'] ) && ! empty( $c['hint_text'] ) ) : ?>
                <div class="nc-hint" id="nc-hint"><?php echo wp_kses_post( $c['hint_text'] ); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Inline size CSS
 */
add_action( 'wp_enqueue_scripts', 'netchat_inline_css', 30 );
function netchat_inline_css() {
    if ( ! netchat_is_active() ) return;
    $c = netchat_get_config();
    $size = intval( $c['size'] );
    $icon = round( $size * 0.5 );
    // Only override if theme is interfering - use CSS variables by default
    wp_add_inline_style( 'netchat-front', ":root{--nc-size:{$size}px;--nc-icon:{$icon}px}" );
}
