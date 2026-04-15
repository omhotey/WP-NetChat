<?php
/**
 * NetChat Admin - Material Design 3
 * Author: omhotey
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'netchat_admin_menu' );
function netchat_admin_menu() {
    add_menu_page(
        'NetChat', 'NetChat', 'manage_options', 'netchat',
        'netchat_admin_page',
        'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iIzI1NjNlYiI+PHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyczQuNDggMTAgMTAgMTAgMTAtNC40OCAxMC0xMFMxNy41MiAyIDEyIDJ6bS0xIDE3LjkyVjE3aDJ2Mi45MmMuNTYtLjEzIDEuMDgtLjM1IDEuNTctLjY2TDE2IDIxLjA4QzE0Ljc5IDIxLjY3IDEzLjQzIDIyIDEyIDIycy0yLjc5LS4zMy0zLjktLjkybDEuNDMtMi41OGMuNDkuMzEgMS4wMS41MyAxLjU3LjY2ek0xMiAxNmMtMS4xIDAtMi0uOS0yLTJzLjktMiAyLTIgMiAuOSAyIDItLjkgMi0yIDJ6bS0zLjU1LTMuMDhDNC4zMiAxMC44NiA0IDkuNDcgNCA4YzAtMi4yMSAxLjc5LTQgNC00czQgMS43OSA0IDRjMCAxLjQ3LS4zMiAyLjg2LS45MSAzLjkyaC0yLjE4QzQuOTUgMTEuMTQgNC41IDEwLjE0IDQuNSA5YzAtMS42NSAxLjM1LTMgMy0zczMgMS4zNSAzIDNjMCAxLjE0LS40NSAyLjE0LTEuMTEgMi45MnoiLz48L3N2Zz4=',
        30
    );
}

add_action( 'admin_enqueue_scripts', 'netchat_admin_assets' );
function netchat_admin_assets( $hook ) {
    if ( 'toplevel_page_netchat' !== $hook ) return;
    wp_enqueue_style( 'netchat-admin', NETCHAT_URL . 'assets/css/admin.css', array(), NETCHAT_VER );
    wp_enqueue_script( 'netchat-admin', NETCHAT_URL . 'assets/js/admin.js', array(), NETCHAT_VER, true );
    wp_localize_script( 'netchat-admin', 'netchatAdmin', array(
        'restNonce' => wp_create_nonce( 'wp_rest' ),
        'restUrl'   => esc_url_raw( rest_url( 'netchat/v1/settings' ) ),
        'i18n_save_ok' => nc_t( 'Settings saved!', 'Настройки сохранены!' ),
        'i18n_save_err' => nc_t( 'Error saving settings', 'Ошибка сохранения' ),
    ) );
}

function netchat_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );

    $c = netchat_get_config();
    $m = netchat_messengers();

    $saved_order = array_filter( array_map( 'trim', explode( ',', $c['order'] ) ) );
    $all_keys = array_keys( $m );
    $order = array_values( array_unique( array_merge( $saved_order, $all_keys ) ) );
    $order = array_values( array_intersect( $order, $all_keys ) );
    $is_left = ! empty( $c['position']['left'] ) && ( $c['position']['left'] !== '' );
    $lang = $c['lang'] ?? 'ru';

    $names = array(
        'telegram'  => 'Telegram',
        'whatsapp'  => 'WhatsApp',
        'messenger' => 'Facebook',
        'max'       => 'MAX',
        'vk'        => nc_t( 'VKontakte', 'ВКонтакте' ),
        'viber'     => 'Viber',
        'email'     => 'Email',
        'phone'     => nc_t( 'Phone', 'Телефон' ),
    );
    $hints = array(
        'telegram'  => nc_t( 'username', 'username' ),
        'whatsapp'  => '+7 (000) 000-00-00',
        'messenger' => nc_t( 'username', 'username' ),
        'max'       => 'https://max.ru/u/f9LH...',
        'vk'        => nc_t( 'username', 'username' ),
        'viber'     => '+7 (000) 000-00-00',
        'email'     => nc_t( 'name@mail.com', 'name@mail.com' ),
        'phone'     => '+7 (000) 000-00-00',
    );

    $cards_html = '';
    foreach ( $order as $key ) {
        if ( ! isset( $m[ $key ] ) ) continue;
        $name    = $names[ $key ] ?? ucfirst( $key );
        $enabled = ! empty( $c['enabled'][ $key ] );
        $link    = $c['links'][ $key ] ?? '';
        $mc      = $m[ $key ]['color'] ?? '#64748b';
        $hint    = $hints[ $key ] ?? '';

        $cards_html .= '<div class="nc-messenger-card" data-key="' . esc_attr( $key ) . '" data-name="' . esc_attr( $name ) . '" data-color="' . esc_attr( $mc ) . '" data-hint="' . esc_attr( $hint ) . '">';
        $cards_html .= '  <div class="nc-messenger-head">';
        $cards_html .= '    <div class="nc-messenger-left">';
        $cards_html .= '      <div class="nc-messenger-icon" style="background:' . esc_attr( $mc ) . '">' . netchat_svg_icon( $key ) . '</div>';
        $cards_html .= '      <span class="nc-messenger-name">' . esc_html( $name ) . '</span>';
        $cards_html .= '    </div>';
        $cards_html .= '    <label class="nc-toggle"><input type="checkbox"' . checked( $enabled, true, false ) . '><span class="nc-slider"></span></label>';
        $cards_html .= '  </div>';
        $cards_html .= '  <div class="nc-messenger-input' . ( $enabled ? ' visible' : '' ) . '">';
        $cards_html .= '    <div class="nc-input-wrap">';
        $cards_html .= '      <input type="text" class="nc-input" data-real-value="' . esc_attr( $link ) . '" value="' . ( $enabled ? esc_attr( $link ) : '' ) . '" placeholder="' . esc_attr( $hint ) . '">';
        $cards_html .= '      <span class="nc-input-status"></span>';
        $cards_html .= '    </div>';
        $cards_html .= '  </div>';
        $cards_html .= '</div>';
    }

    $pos_right  = ( $c['position']['right']  !== '' ) ? intval( $c['position']['right'] )  : 20;
    $pos_bottom = ( $c['position']['bottom'] !== '' ) ? intval( $c['position']['bottom'] ) : 30;
    $show_greeting = ! empty( $c['hint'] );
    ?>
    <div class="netchat-standalone-page">
        <div id="netchat-notice-container"></div>

        <div class="netchat-layout">
            <!-- Left column: Messengers -->
            <div class="nc-col-left">
                <div class="nc-card">
                    <div class="nc-card-title">&#128172; <?php echo nc_t( 'MESSENGERS', 'МЕССЕНДЖЕРЫ' ); ?></div>
                    <div id="netchat-channels">
                        <?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            </div>

            <!-- Right column: Settings -->
            <div class="nc-col-right">

                <!-- Position -->
                <div class="nc-card nc-card-position">
                    <div class="nc-card-title">&#128205; <?php echo nc_t( 'POSITION', 'ПОЗИЦИЯ' ); ?></div>
                    <div class="nc-pos-buttons">
                        <button type="button" class="nc-pos-btn<?php echo $is_left ? ' nc-pos-active' : ''; ?>" data-value="left">&#8601; <?php echo nc_t( 'Bottom Left', 'Слева внизу' ); ?></button>
                        <button type="button" class="nc-pos-btn<?php echo ! $is_left ? ' nc-pos-active' : ''; ?>" data-value="right">&#8600; <?php echo nc_t( 'Bottom Right', 'Справа внизу' ); ?></button>
                    </div>
                    <div class="nc-pos-row">
                        <label><?php echo nc_t( 'Right', 'Справа' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-pos-right" value="<?php echo esc_attr( $pos_right ); ?>" min="0" step="1">
                            <span class="nc-unit">px</span>
                        </div>
                    </div>
                    <div class="nc-pos-row">
                        <label><?php echo nc_t( 'Bottom', 'Снизу' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-pos-bottom" value="<?php echo esc_attr( $pos_bottom ); ?>" min="0" step="1">
                            <span class="nc-unit">px</span>
                        </div>
                    </div>
                </div>

                <!-- Appearance -->
                <div class="nc-card nc-card-appearance">
                    <div class="nc-card-title">&#127912; <?php echo nc_t( 'APPEARANCE', 'ВНЕШНИЙ ВИД' ); ?></div>
                    <div class="nc-ap-row">
                        <label><?php echo nc_t( 'Size', 'Размер' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-opt-sz" value="<?php echo esc_attr( $c['size'] ); ?>" min="40" max="80" step="4">
                            <span class="nc-unit">px</span>
                        </div>
                        <span class="nc-ap-unit">40&ndash;80</span>
                    </div>
                    <div class="nc-ap-row">
                        <label><?php echo nc_t( 'Animation', 'Анимация' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-opt-sp" value="<?php echo esc_attr( $c['animation'] ); ?>" min="0" max="1000" step="50">
                            <span class="nc-unit">ms</span>
                        </div>
                    </div>
                    <div class="nc-ap-row">
                        <label><?php echo nc_t( 'Color', 'Цвет' ); ?></label>
                        <input type="color" id="nc-opt-cl" value="<?php echo esc_attr( $c['color'] ); ?>">
                    </div>
                </div>

                <!-- Greeting -->
                <div class="nc-card nc-card-greeting">
                    <div class="nc-card-title">&#128172; <?php echo nc_t( 'GREETING MESSAGE', 'ПРИВЕТСТВЕННОЕ СООБЩЕНИЕ' ); ?></div>
                    <div class="nc-greeting-toggle">
                        <label class="nc-toggle">
                            <input type="checkbox" id="nc-opt-h"<?php checked( $show_greeting ); ?>>
                            <span class="nc-slider"></span>
                        </label>
                        <span class="nc-greeting-label"><?php echo nc_t( 'Show message', 'Показывать сообщение' ); ?></span>
                    </div>
                    <div class="nc-greeting-fields<?php echo $show_greeting ? ' visible' : ' hidden'; ?>" id="nc-greeting-fields">
                        <div class="nc-editor-toolbar">
                            <button type="button" class="nc-editor-btn" data-action="bold" title="Bold"><b>B</b></button>
                            <button type="button" class="nc-editor-btn" data-action="italic" title="Italic"><i>I</i></button>
                            <button type="button" class="nc-editor-btn" data-action="break" title="Line break">&#8629;</button>
                            <button type="button" class="nc-editor-btn" data-action="link" title="Link">&#128279;</button>
                        </div>
                        <textarea class="nc-greeting-textarea" id="nc-opt-ht" rows="3" placeholder="<?php echo esc_attr( nc_t( 'e.g. Need help?', 'Например: Нужна помощь?' ) ); ?>"><?php echo htmlspecialchars( $c['hint_text'], ENT_NOQUOTES, 'UTF-8' ); ?></textarea>
                        <div class="nc-editor-hint"><?php echo nc_t( 'HTML: &lt;b&gt;, &lt;i&gt;, &lt;br&gt;, &lt;a href&gt;', 'HTML: &lt;b&gt;, &lt;i&gt;, &lt;br&gt;, &lt;a href&gt;' ); ?></div>
                        <div class="nc-greeting-delay">
                            <label><?php echo nc_t( 'Show delay', 'Задержка появления' ); ?></label>
                            <div class="nc-delay-wrap">
                                <input type="number" id="nc-opt-hd" value="<?php echo esc_attr( $c['hint_delay'] ?? 2 ); ?>" min="0" max="30" step="1">
                                <span class="nc-delay-unit"><?php echo nc_t( 'sec', 'сек' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visibility -->
                <div class="nc-card nc-card-visibility">
                    <div class="nc-card-title">&#128065; <?php echo nc_t( 'VISIBILITY', 'ВИДИМОСТЬ' ); ?></div>
                    <div class="nc-vis-row">
                        <label class="nc-vis-check">
                            <input type="checkbox" id="nc-opt-d"<?php checked( ! empty( $c['desktop'] ) ); ?>>
                            <em><?php echo nc_t( 'Desktop', 'Десктоп' ); ?></em>
                        </label>
                        <label class="nc-vis-check">
                            <input type="checkbox" id="nc-opt-m"<?php checked( ! empty( $c['mobile'] ) ); ?>>
                            <em><?php echo nc_t( 'Mobile', 'Мобильные' ); ?></em>
                        </label>
                    </div>
                </div>

                <!-- Language -->
                <div class="nc-card nc-card-language">
                    <div class="nc-card-title">&#127760; <?php echo nc_t( 'INTERFACE LANGUAGE', 'ЯЗЫК ИНТЕРФЕЙСА' ); ?></div>
                    <div class="nc-lang-btns">
                        <button type="button" class="nc-lang-opt<?php echo $lang === 'ru' ? ' active' : ''; ?>" data-lang="ru"><em>RU</em> <?php echo nc_t( 'Russian', 'Русский' ); ?></button>
                        <button type="button" class="nc-lang-opt<?php echo $lang === 'en' ? ' active' : ''; ?>" data-lang="en"><em>US</em> <?php echo nc_t( 'English', 'English' ); ?></button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bottom bar -->
        <div class="netchat-bottom-bar">
            <div class="nc-btn-group">
                <button type="button" class="nc-save-btn" id="nc-save-btn">&#128190; <?php echo nc_t( 'Save Settings', 'Сохранить настройки' ); ?></button>
                <button type="button" class="nc-reset-btn" id="nc-reset-btn">&#128260; <?php echo nc_t( 'Reset', 'Сбросить' ); ?></button>
            </div>
            <span class="nc-footer-text"><em>NetChat v<?php echo NETCHAT_VER; ?> &mdash; <a href="https://github.com/omhotey" target="_blank" rel="noopener">omhotey</a></em></span>
        </div>
    </div>
    <?php
}

/**
 * REST API save endpoint
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'netchat/v1', '/settings', array(
        'methods'             => 'POST',
        'callback'            => 'netchat_rest_save',
        'permission_callback' => function () {
            if ( ! current_user_can( 'manage_options' ) ) {
                return false;
            }
            $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
            return wp_verify_nonce( $nonce, 'wp_rest' ) !== false;
        },
    ) );
} );

function netchat_rest_save( $request ) {
    $data = $request->get_json_params();
    if ( ! $data ) {
        return new WP_Error( 'invalid_data', 'Invalid data', array( 'status' => 400 ) );
    }

    $config     = netchat_get_config();
    $messengers = array_keys( netchat_messengers() );

    // Messengers
    foreach ( $messengers as $k ) {
        $config['enabled'][ $k ] = isset( $data['enabled'][ $k ] ) ? 1 : 0;
        $config['links'][ $k ]   = netchat_validate_url( $data['links'][ $k ] ?? '', $k );
    }

    // Position
    $config['position']['top']    = '';
    $config['position']['right']  = ( isset( $data['position']['right'] )  && $data['position']['right']  !== '' ) ? max( 0, intval( $data['position']['right'] ) )  : '';
    $config['position']['bottom'] = ( isset( $data['position']['bottom'] ) && $data['position']['bottom'] !== '' ) ? max( 0, intval( $data['position']['bottom'] ) ) : '30';
    $config['position']['left']   = ( isset( $data['position']['left'] )   && $data['position']['left']   !== '' ) ? max( 0, intval( $data['position']['left'] ) )   : '';

    // Appearance
    $config['size']      = max( 40, min( 80, intval( $data['size'] ?? 56 ) ) );
    $config['animation'] = max( 0, min( 1000, intval( $data['animation'] ?? 300 ) ) );
    $config['color']     = preg_match( '/^#[0-9a-f]{6}$/i', $data['color'] ?? '#2563eb' ) ? $data['color'] : '#2563eb';

    // Greeting
    $config['hint']      = isset( $data['hint'] ) ? 1 : 0;
    $config['hint_text'] = wp_kses_post( $data['hint_text'] ?? '' );
    $config['hint_delay'] = max( 0, min( 30, intval( $data['hint_delay'] ?? 2 ) ) );

    // Visibility
    $config['desktop'] = isset( $data['desktop'] ) ? 1 : 0;
    $config['mobile']  = isset( $data['mobile'] ) ? 1 : 0;

    // Language
    $old_lang = $config['lang'];
    if ( isset( $data['lang'] ) ) {
        $config['lang'] = in_array( $data['lang'], array( 'ru', 'en' ), true ) ? $data['lang'] : 'ru';
    }
    $lang_changed = isset( $data['lang'] ) && $data['lang'] !== $old_lang;

    // Order
    if ( isset( $data['order'] ) ) {
        $order = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $data['order'] ) ) );
        $config['order'] = implode( ',', array_values( array_intersect( $order, $messengers ) ) );
    }

    // Save to JSON file
    if ( ! netchat_save_config( $config ) ) {
        return new WP_Error( 'save_failed', 'Failed to save settings', array( 'status' => 500 ) );
    }

    return array(
        'success'      => true,
        'message'      => nc_t( 'Settings saved!', 'Настройки сохранены!' ),
        'lang_changed' => $lang_changed,
    );
}
