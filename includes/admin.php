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
    if ( ! in_array( $hook, array('toplevel_page_netchat','netchat_page_netchat-lead-settings'), true ) ) return;
    wp_enqueue_style( 'netchat-admin', NETCHAT_URL . 'assets/css/admin.css', array(), NETCHAT_VER . '.' . filemtime(NETCHAT_DIR.'assets/css/admin.css') );
    wp_enqueue_script( 'netchat-validation', NETCHAT_URL . 'assets/js/validation.js', array(), NETCHAT_VER, true );
    wp_enqueue_script( 'netchat-admin', NETCHAT_URL . 'assets/js/admin.js', array( 'netchat-validation' ), NETCHAT_VER . '.' . filemtime(NETCHAT_DIR.'assets/js/admin.js'), true );
    wp_localize_script( 'netchat-admin', 'netchatAdmin', array(
        'restNonce' => wp_create_nonce( 'wp_rest' ),
        'restUrl'   => esc_url_raw( rest_url( 'netchat/v1/settings' ) ),
        'revision' => netchat_get_revision(),
        'config' => netchat_get_config(),
        'defaults' => netchat_defaults(),
        'i18n_save' => $hook==='netchat_page_netchat-lead-settings'?nc_t('Save enquiry settings','Сохранить настройки обращений'):nc_t( 'Save settings', 'Сохранить настройки' ),
        'i18n_saving' => nc_t( 'Saving...', 'Сохранение...' ),
        'i18n_invalid' => nc_t( 'Correct the highlighted contacts before saving.', 'Исправьте отмеченные контакты перед сохранением.' ),
        'i18n_conflict' => nc_t( 'Settings were changed in another session. Your edits are preserved. Copy them and reload this page before saving again.', 'Настройки изменены в другом сеансе. Ваши правки сохранены на странице. Скопируйте их и обновите страницу перед повторным сохранением.' ),
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
    $is_left = $c['position']['left'] !== '';
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
        $cards_html .= '      <input type="text" class="nc-input" data-real-value="' . esc_attr( $link ) . '" value="' . esc_attr( $link ) . '" placeholder="' . esc_attr( $hint ) . '">';
        $cards_html .= '      <span class="nc-input-status"></span>';
        $cards_html .= '    </div>';
        $cards_html .= '  </div>';
        $cards_html .= '</div>';
    }

    $side_offset = $is_left ? $c['position']['left'] : $c['position']['right'];
    $pos_right  = $side_offset !== '' ? intval( $side_offset ) : 20;
    $pos_bottom = ( $c['position']['bottom'] !== '' ) ? intval( $c['position']['bottom'] ) : 30;
    $show_greeting = ! empty( $c['hint'] );
    $migration_error = netchat_get_migration_error();
    if ( is_wp_error( $migration_error ) ) echo '<div class="notice notice-error"><p>' . esc_html( $migration_error->get_error_message() ) . '</p></div>';
    $warnings = netchat_get_migration_warnings();
    if ( $warnings ) echo '<div class="notice notice-warning"><p>' . esc_html( nc_t( 'Review these migrated contacts. Their original values have been retained: ', 'Проверьте перенесённые контакты. Исходные значения сохранены: ' ) . implode( ', ', $warnings ) ) . '</p></div>';
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
                    <div class="nc-messenger-card nc-form-channel" id="netchat-leads-channel">
                        <div class="nc-messenger-head">
                            <div class="nc-messenger-left">
                                <div class="nc-messenger-icon" style="background:#2563eb"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h5"/></svg></div>
                                <span class="nc-messenger-name"><?php echo esc_html(nc_t('Website enquiries','Заявки на сайте')); ?></span>
                            </div>
                            <label class="nc-toggle"><input type="checkbox" data-nc-lead-setting="enabled" aria-label="<?php echo esc_attr(nc_t('Accept enquiries','Принимать заявки')); ?>"<?php checked(!empty($c['leads']['enabled'])); ?>><span class="nc-slider"></span></label>
                        </div>
                        <a class="nc-form-settings-link" href="<?php echo esc_url(admin_url('admin.php?page=netchat-lead-settings')); ?>"><?php echo esc_html(nc_t('Enquiry settings →','Настройки обращений →')); ?></a>
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
                        <label id="nc-pos-side-label" data-left="<?php echo esc_attr( nc_t( 'Left', 'Слева' ) ); ?>" data-right="<?php echo esc_attr( nc_t( 'Right', 'Справа' ) ); ?>"><?php echo $is_left ? nc_t( 'Left', 'Слева' ) : nc_t( 'Right', 'Справа' ); ?></label>
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
                <div class="nc-card nc-card-launcher"><div class="nc-card-title">Поведение кнопки</div><div class="nc-launcher-options">
                    <label><input type="radio" name="nc-launcher-mode" value="form" <?php checked($c['launcher_mode'],'form'); ?>><span><strong>Только форма</strong><small>Одна кнопка сразу открывает попап. Мессенджеры доступны внутри.</small></span></label>
                    <label><input type="radio" name="nc-launcher-mode" value="menu" <?php checked($c['launcher_mode'],'menu'); ?>><span><strong>Форма и мессенджеры</strong><small>Кнопка раскрывает меню: форма сверху, каналы связи ниже.</small></span></label>
                </div><a class="nc-form-settings-link" href="<?php echo esc_url(admin_url('admin.php?page=netchat-service-forms')); ?>">Выбрать основную / общую форму</a><p class="nc-launcher-help">Привязка страницы выбирает её форму. Без привязки используется настройка главной. Когда приём форм выключен, доступны только мессенджеры.</p></div>
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
                        <div class="nc-scroll-setting">
                            <label for="nc-opt-hscroll"><?php echo nc_t('Greeting after page scroll (%)','Приветствие после прокрутки страницы (%)'); ?></label>
                            <input type="number" id="nc-opt-hscroll" value="<?php echo esc_attr($c['hint_scroll_percent']); ?>" min="0" max="100" step="1" required aria-describedby="nc-hscroll-help">
                            <small id="nc-hscroll-help"><?php echo nc_t('0: no scroll delay; 100: end of page. The delay starts after both thresholds.','0 — без ожидания прокрутки; 100 — конец страницы. Задержка отсчитывается после достижения обоих порогов.'); ?></small>
                        </div>
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
                    <div class="nc-scroll-setting">
                        <label for="nc-opt-bscroll"><?php echo nc_t('Button after page scroll (%)','Кнопка после прокрутки страницы (%)'); ?></label>
                        <input type="number" id="nc-opt-bscroll" value="<?php echo esc_attr($c['button_scroll_percent']); ?>" min="0" max="100" step="1" required aria-describedby="nc-bscroll-help">
                        <small id="nc-bscroll-help"><?php echo nc_t('0: no scroll delay; 100: end of page. Once shown, the button stays until reload.','0 — без ожидания прокрутки; 100 — конец страницы. После появления кнопка остаётся до перезагрузки.'); ?></small>
                    </div>
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

/** Full settings replacement. WP core authenticates cookie/nonces before permissions. */
add_action( 'rest_api_init', function () {
    $args = array();
    foreach ( netchat_defaults() as $key => $value ) $args[$key] = array( 'required' => !in_array($key,array('launcher_mode','button_scroll_percent','hint_scroll_percent'),true) );
    $args['revision'] = array( 'required' => true, 'type' => 'integer', 'minimum' => 1 );
    register_rest_route( 'netchat/v1', '/settings', array(
        'methods' => 'POST', 'callback' => 'netchat_rest_save',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'args' => $args,
    ) );
} );
function netchat_rest_save( $request ) {
    $data = $request->get_json_params();
    if ( ! is_array( $data ) || ! isset( $data['revision'] ) || ! is_int( $data['revision'] ) || $data['revision'] < 1 ) {
        return new WP_Error( 'netchat_invalid_config', 'A full JSON settings document and integer revision are required.', array( 'status' => 400 ) );
    }
    $revision = $data['revision']; unset( $data['revision'] );
    $current=netchat_get_config();
    foreach(array('launcher_mode','button_scroll_percent','hint_scroll_percent')as$key)if(!array_key_exists($key,$data))$data[$key]=$current[$key];
    $config = netchat_normalize_config( $data, true );
    if ( is_wp_error( $config ) ) return $config;
    $old_lang = netchat_get_config()['lang'];
    $saved = netchat_save_config( $config, $revision );
    if ( is_wp_error( $saved ) ) return $saved;
    if ( ! $saved ) return new WP_Error( 'netchat_save_failed', 'Failed to save settings.', array( 'status' => 503 ) );
    return array( 'success' => true, 'message' => nc_t( 'Settings saved!', 'Настройки сохранены!' ), 'lang_changed' => $config['lang'] !== $old_lang, 'revision' => netchat_get_revision(), 'config' => netchat_get_config() );
}
