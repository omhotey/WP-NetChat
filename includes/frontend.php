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

    wp_enqueue_style( 'netchat-front', NETCHAT_URL . 'assets/css/style.css', array(), NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/style.css') );
    wp_enqueue_script( 'netchat-front', NETCHAT_URL . 'assets/js/front.js', array(), NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/js/front.js'), true );

    $c = netchat_get_config();
    wp_localize_script( 'netchat-front', 'netchat', array(
        'speed'     => intval( $c['animation'] ),
        'hintDelay' => ( intval( $c['hint_delay'] ?? 2 ) ) * 1000,
        'buttonScrollPercent' => (int)$c['button_scroll_percent'],
        'hintScrollPercent' => (int)$c['hint_scroll_percent'],
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
    $at_top = '' !== $c['position']['top'];
    $at_left = '' !== $c['position']['left'];
    if ( $at_top ) $pos[] = 'top:' . intval( $c['position']['top'] ) . 'px';
    if ( '' !== $c['position']['left'] ) $pos[] = 'left:' . intval( $c['position']['left'] ) . 'px';
    elseif ( '' !== $c['position']['right'] ) $pos[] = 'right:' . intval( $c['position']['right'] ) . 'px';
    else $pos[] = 'right:20px';
    if ( ! $at_top ) {
        $pos[] = 'bottom:' . intval( '' !== $c['position']['bottom'] ? $c['position']['bottom'] : 20 ) . 'px';
    }

    $style = implode( ';', $pos );
    $size = intval( $c['size'] );
    $color = $c['color'];
    $has_form = ! empty($c['leads']['enabled']);
    $form_first = $has_form && $c['launcher_mode']==='form';
    $form_label = function_exists('netchat_service_ready') && netchat_service_ready() ? nc_t('Describe your task','Описать задачу') : $c['leads']['title'];
    $service_attrs = function_exists('netchat_service_ready') && netchat_service_ready() ? ' data-netchat-service="" data-netchat-cta="widget"' : '';

    // Visibility classes
    $cls = ( $at_left ? ' nc-at-left' : '' ) . ( $at_top ? ' nc-at-top' : '' );
    if((int)$c['button_scroll_percent']>0)$cls.=' nc-scroll-wait';
    if($form_first)$cls.=' nc-form-first';
    elseif($has_form)$cls.=' nc-form-menu';
    if ( empty( $c['desktop'] ) && ! empty( $c['mobile'] ) ) $cls .= ' nc-hide-desktop';
    elseif ( ! empty( $c['desktop'] ) && empty( $c['mobile'] ) ) $cls .= ' nc-hide-mobile';
    elseif ( empty( $c['desktop'] ) && empty( $c['mobile'] ) ) $cls .= ' nc-hide-all';

    // Build messenger buttons
    $bubble_html = '';
    foreach ( $order as $key ) {
        if($form_first)continue;
        if ( empty( $c['enabled'][ $key ] ) || empty( $c['links'][ $key ] ) ) continue;
        $name = $m[ $key ]['name'] ?? ucfirst( $key );
        $url  = netchat_validate_url( $c['links'][ $key ], $key );
        if ( '' === $url ) continue;
        $target = in_array( $key, array( 'email', 'phone' ), true ) ? '' : 'target="_blank" rel="noopener noreferrer"';
        $protocols = wp_allowed_protocols();
        if ( 'viber' === $key ) $protocols[] = 'viber';
        $href = esc_url( $url, $protocols );
        if ( '' === $href ) continue;
        $bubble_html .= '<a href="' . $href . '" ' . $target . ' class="nc-btn nc-' . esc_attr( $key ) . '" data-nc-tooltip="' . esc_attr( $name ) . '" title="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $name ) . '" tabindex="-1">';
        $bubble_html .= netchat_svg_icon( $key );
        $bubble_html .= '</a>';
    }
    if($has_form && !$form_first){
        $icon='<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h5"/></svg>';
        $bubble_html='<button type="button" class="nc-btn nc-form nc-menu-form" data-netchat-open="form"'.$service_attrs.' aria-label="'.esc_attr($form_label).'" tabindex="-1" style="background:var(--nc-color)">'.$icon.'</button><div class="nc-menu-channels">'.$bubble_html.'</div>';
    }
    // The form is a channel even when every messenger is disabled.
    if ( empty( $bubble_html ) && !$form_first ) return;
    ?>
    <div id="netchat" class="nc-wrap<?php echo esc_attr( $cls ); ?>" style="<?php echo esc_attr( $style ); ?>;--nc-size:<?php echo esc_attr( $size ); ?>px;--nc-icon:<?php echo esc_attr( round( $size * 0.5 ) ); ?>px;--nc-color:<?php echo esc_attr( $color ); ?>;--nc-duration:<?php echo esc_attr( max( 0, intval( $c['animation'] ) ) ); ?>ms">
        <div class="nc-col">
            <div class="nc-bubble" id="nc-bubble" aria-hidden="true">
                <?php echo $bubble_html; // phpcs:ignore ?>
            </div>
            <div class="nc-main-wrap">
                <button type="button" class="nc-btn nc-main<?php if($form_first)echo ' nc-form'; ?>" id="nc-toggle"<?php if((int)$c['button_scroll_percent']>0)echo ' hidden'; ?> aria-label="<?php echo $form_first?esc_attr($form_label):esc_attr__( 'Open contact menu', 'netchat' ); ?>" aria-controls="<?php echo $form_first?'nc-leads-modal':'nc-bubble'; ?>" aria-expanded="false"<?php if($form_first)echo ' aria-haspopup="dialog" data-netchat-open="form"'.$service_attrs; ?>>
                    <?php if($form_first): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h5"/></svg><?php else: ?><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="18" r="2"/></svg><?php endif; ?>
                </button>
                <?php if ( ! empty( $c['hint'] ) && ! empty( $c['hint_text'] ) ) : ?>
                <div class="nc-hint" id="nc-hint" hidden><div class="nc-hint-content"><?php echo wp_kses_post( $c['hint_text'] ); ?></div><button type="button" class="nc-hint-close" aria-label="<?php echo esc_attr(nc_t('Dismiss greeting','Скрыть приветствие')); ?>">&times;</button></div>
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
