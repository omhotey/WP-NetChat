<?php
/** NetChat admin: restored canonical MD3 layout plus isolated live preview. */
if (!defined('ABSPATH')) exit;
add_action('admin_menu','netchat_admin_menu');
function netchat_admin_menu(){add_menu_page('NetChat','NetChat','manage_options','netchat','netchat_admin_page','dashicons-format-chat',30);}
function netchat_admin_messengers(){
 $colors=array('telegram'=>'#0088cc','whatsapp'=>'#25D366','messenger'=>'#0084FF','max'=>'#FF6B35','vk'=>'#0077FF','viber'=>'#7360F2','email'=>'#EA4335','phone'=>'#34C759');$result=array();foreach($colors as$key=>$color)$result[$key]=array('color'=>$color);return $result;
}
add_action('admin_enqueue_scripts','netchat_admin_assets');
function netchat_admin_assets($hook){
 if($hook!=='toplevel_page_netchat'||!current_user_can('manage_options'))return;
 $state=netchat_read_settings();if(is_wp_error($state))return;
 wp_enqueue_style('netchat-admin',NETCHAT_URL.'assets/css/admin.css',array(),NETCHAT_VER.'.r2');
 wp_enqueue_style('netchat-admin-preview',NETCHAT_URL.'assets/css/admin-preview.css',array('netchat-admin'),NETCHAT_VER.'.r2');
 wp_enqueue_script('netchat-validation',NETCHAT_URL.'assets/js/validation.js',array(),NETCHAT_VER.'.r2',true);
 wp_enqueue_script('netchat-admin',NETCHAT_URL.'assets/js/admin.js',array('netchat-validation'),NETCHAT_VER.'.r2',true);
 $form_available=false;foreach(netchat_get_channels() as $item)if(($item['action']??'')==='netchat-forms-open'){$form_available=true;break;}
 wp_localize_script('netchat-admin','netchatAdmin',array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('netchat_save_settings'),'revision'=>$state['revision'],'config'=>netchat_get_config(),'defaults'=>netchat_defaults(),'formAvailable'=>$form_available,'i18n'=>array(
 'save'=>nc_t('Save settings','Сохранить настройки'),'saving'=>nc_t('Saving…','Сохранение…'),'saved'=>nc_t('Settings saved.','Настройки сохранены.'),'error'=>nc_t('Could not save. Your edits remain on this page.','Не удалось сохранить. Ваши правки остались на странице.'),'invalid'=>nc_t('Correct the highlighted values before saving.','Исправьте отмеченные значения перед сохранением.'),'conflict'=>nc_t('Settings changed in another session. Your edits remain here; copy them and reload before saving again.','Настройки изменены в другом сеансе. Правки остались здесь; скопируйте их и обновите страницу перед повторным сохранением.'),'dirty'=>nc_t('Unsaved changes','Есть несохранённые изменения'),'clean'=>nc_t('All changes saved','Изменения сохранены'),'reset'=>nc_t('Restore widget defaults in this draft? Save to apply them.','Восстановить настройки виджета в черновике? Для применения нажмите «Сохранить».'),'reset_done'=>nc_t('Defaults restored in the draft. Save to apply.','Настройки по умолчанию восстановлены в черновике. Сохраните для применения.'),'reload_language'=>nc_t('Reload this page to update its language.','Обновите страницу, чтобы применить язык интерфейса.'),'empty'=>nc_t('Enable a channel and enter a valid contact.','Включите канал и укажите корректный контакт.'),'hidden'=>nc_t('The widget is hidden by these device or visibility settings.','Виджет скрыт настройками устройства или условиями появления.'),'ready'=>nc_t('The button is available in this preview.','Кнопка доступна в предпросмотре.'),'link'=>nc_t('Enter a safe HTTP(S), mailto or tel link.','Укажите безопасную ссылку HTTP(S), mailto или tel.'))));
}
function netchat_admin_json_error($error){$data=$error->get_error_data();$status=is_array($data)&&isset($data['status'])?(int)$data['status']:400;wp_send_json_error(array('code'=>$error->get_error_code(),'message'=>$error->get_error_message()),$status);}
add_action('wp_ajax_netchat_save_settings','netchat_admin_ajax_save');
function netchat_admin_ajax_save(){
 if(!current_user_can('manage_options')){netchat_admin_json_error(netchat_error('netchat_forbidden','Administrator permission required.','Требуются права администратора.',403));return;}
 $nonce=$_POST['_wpnonce']??'';
 if(!is_string($nonce)||!wp_verify_nonce($nonce,'netchat_save_settings')){netchat_admin_json_error(netchat_error('netchat_nonce','Invalid nonce. Reload the page.','Проверка безопасности не пройдена. Обновите страницу.',403));return;}
 $json=isset($_POST['settings'])&&is_string($_POST['settings'])?wp_unslash($_POST['settings']):'';
 if(strlen($json)>65536){netchat_admin_json_error(netchat_invalid_setting('document'));return;}
 $input=json_decode($json,true);
 if(!is_array($input)||json_last_error()!==JSON_ERROR_NONE){netchat_admin_json_error(netchat_invalid_setting('document'));return;}
 $result=netchat_save_settings($input,$_POST['revision']??'',$nonce);
 if(is_wp_error($result)){netchat_admin_json_error($result);return;}
 wp_send_json_success(array('revision'=>$result['revision'],'config'=>$result['settings'],'message'=>nc_t('Settings saved.','Настройки сохранены.')));
}
add_action('admin_post_netchat_save_settings','netchat_admin_save');
function netchat_admin_save(){
 $result=netchat_save_settings(wp_unslash($_POST['settings']??array()),$_POST['revision']??'',$_POST['_wpnonce']??'');
 if(is_wp_error($result)){$data=$result->get_error_data();wp_die(esc_html($result->get_error_message()),'NetChat',array('response'=>is_array($data)&&isset($data['status'])?(int)$data['status']:400));return;}
 wp_safe_redirect(admin_url('admin.php?page=netchat&saved=1'));exit;
}
function netchat_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );

    $state=netchat_read_settings(); if(is_wp_error($state)){echo '<div class="wrap"><h1>NetChat</h1><p>'.esc_html($state->get_error_message()).'</p></div>';return;}
    $c = netchat_get_config();
    $m = netchat_admin_messengers();

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
        $cards_html .= '    <span class="nc-drag-handle" draggable="true" aria-hidden="true" title="' . esc_attr(nc_t('Drag to reorder','Перетащите для изменения порядка')) . '">⋮⋮</span>';
        $cards_html .= '    <div class="nc-messenger-left">';
        $cards_html .= '      <div class="nc-messenger-icon" style="background:' . esc_attr( $mc ) . '">' . netchat_svg_icon( $key ) . '</div>';
        $cards_html .= '      <span class="nc-messenger-name">' . esc_html( $name ) . '</span>';
        $cards_html .= '    </div>';
        $cards_html .= '    <div class="nc-card-actions"><button type="button" class="nc-card-move" data-direction="up" aria-label="' . esc_attr(nc_t('Move up: ','Поднять: ').$name) . '">↑</button><button type="button" class="nc-card-move" data-direction="down" aria-label="' . esc_attr(nc_t('Move down: ','Опустить: ').$name) . '">↓</button><label class="nc-toggle"><input type="checkbox" name="settings[enabled][' . esc_attr($key) . ']" aria-label="' . esc_attr($name) . '"' . checked( $enabled, true, false ) . '><span class="nc-slider"></span></label></div>';
        $cards_html .= '  </div>';
        $cards_html .= '  <div class="nc-messenger-input' . ( $enabled ? ' visible' : '' ) . '">';
        $cards_html .= '    <div class="nc-input-wrap">';
        $cards_html .= '      <input type="text" class="nc-input" name="settings[links][' . esc_attr($key) . ']" data-real-value="' . esc_attr( $link ) . '" value="' . esc_attr( $link ) . '" placeholder="' . esc_attr( $hint ) . '">';
        $cards_html .= '      <span class="nc-input-status"></span>';
        $cards_html .= '    </div>';
        $cards_html .= '  </div>';
        $cards_html .= '</div>';
    }

    $side_offset = $is_left ? $c['position']['left'] : $c['position']['right'];
    $pos_right = $c['position']['right'];
    $pos_bottom = $c['position']['bottom'];
    $show_greeting = ! empty( $c['hint'] );
    ?>
    <div class="netchat-standalone-page">
        <h1 class="nc-page-heading">NetChat <span><?php echo esc_html(NETCHAT_VER); ?></span></h1><p class="nc-page-description"><?php echo esc_html(nc_t('Contact channels and widget appearance','Каналы связи и внешний вид виджета')); ?></p>
        <div id="netchat-notice-container" aria-live="polite"></div><span id="nc-draft-status" role="status" aria-live="polite"></span><input type="hidden" name="revision" id="nc-revision" value="<?php echo esc_attr($state['revision']); ?>">

        <div class="netchat-layout">
            <!-- Left column: Messengers -->
            <div class="nc-col-left">
                <div class="nc-card">
                    <div class="nc-card-title">&#128172; <?php echo nc_t( 'MESSENGERS', 'МЕССЕНДЖЕРЫ' ); ?></div>
                    <input type="hidden" id="nc-order" name="settings[order]" value="<?php echo esc_attr($c['order']); ?>">
                    <label for="nc-launcher-mode"><?php echo esc_html(nc_t('Button opens','Кнопка открывает')); ?></label><select id="nc-launcher-mode" name="settings[launcher_mode]"><option value="channels" <?php selected($c['launcher_mode'],'channels'); ?>><?php echo esc_html(nc_t('Contact channels','Каналы связи')); ?></option><option value="form" <?php selected($c['launcher_mode'],'form'); ?>><?php echo esc_html(nc_t('Form or form selection','Форму или выбор формы')); ?></option></select><p class="nc-editor-hint"><?php echo esc_html(nc_t('The form plugin must be active; otherwise the contact channels open.','Нужен активный плагин форм; иначе откроются каналы связи.')); ?></p>
                    <div id="netchat-channels">
                        <?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
                <div class="nc-card nc-card-preview"><div class="nc-card-title"><?php echo esc_html(nc_t('Live preview','Предпросмотр')); ?></div><div class="nc-preview-devices"><button type="button" class="nc-preview-device active" data-device="desktop"><?php echo esc_html(nc_t('Desktop','Компьютер')); ?></button><button type="button" class="nc-preview-device" data-device="mobile"><?php echo esc_html(nc_t('Phone','Телефон')); ?></button></div><div id="nc-preview-stage" class="nc-preview-stage" data-device="desktop" aria-label="<?php echo esc_attr(nc_t('Widget preview','Предпросмотр виджета')); ?>"><div id="nc-preview-empty"></div><div id="nc-preview-widget"><div id="nc-preview-hint"></div><div id="nc-preview-menu"></div><button type="button" id="nc-preview-toggle" aria-label="<?php echo esc_attr(nc_t('Toggle preview menu','Открыть меню предпросмотра')); ?>" aria-expanded="false">•••</button></div></div><label class="nc-preview-slider" for="nc-preview-time"><?php echo esc_html(nc_t('Time on page, seconds','Время на странице, секунды')); ?> <output id="nc-preview-time-value">600</output><input type="range" id="nc-preview-time" min="0" max="600" step="1" value="600"></label><label class="nc-preview-slider" for="nc-preview-scroll"><?php echo esc_html(nc_t('Page scroll, %','Прокрутка страницы, %')); ?> <output id="nc-preview-scroll-value">100</output><input type="range" id="nc-preview-scroll" min="0" max="100" step="1" value="100"></label><p id="nc-preview-status" class="nc-editor-hint" aria-live="polite"></p><p class="nc-editor-hint"><?php echo esc_html(nc_t('Preview models current settings. Position is scaled to the frame. Links do not navigate; addon actions belong to their own plugins.','Предпросмотр отражает текущие настройки. Положение масштабируется внутри рамки. Ссылки не открываются; дополнительные действия отображаются отдельными плагинами.')); ?></p></div>
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
                        <label id="nc-pos-side-label" for="nc-pos-right"><?php echo esc_html(nc_t('Right','Справа')); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-pos-right" name="settings[position][right]" value="<?php echo esc_attr( $pos_right ); ?>" min="0" max="10000" step="1">
                            <span class="nc-unit">px</span>
                        </div>
                    </div>
                    <div class="nc-pos-row">
                        <label><?php echo nc_t( 'Bottom', 'Снизу' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-pos-bottom" name="settings[position][bottom]" value="<?php echo esc_attr( $pos_bottom ); ?>" min="0" max="10000" step="1">
                            <span class="nc-unit">px</span>
                        </div>
                    </div>
                    <?php foreach(array('left'=>array('Left','Слева'),'top'=>array('Top','Сверху'))as $coordinate=>$labels): ?>
                    <div class="nc-pos-row"><label for="nc-pos-<?php echo esc_attr($coordinate); ?>"><?php echo esc_html(nc_t($labels[0],$labels[1])); ?></label><div class="nc-pos-input-wrap"><input type="number" min="0" max="10000" step="1" id="nc-pos-<?php echo esc_attr($coordinate); ?>" name="settings[position][<?php echo esc_attr($coordinate); ?>]" value="<?php echo esc_attr($c['position'][$coordinate]); ?>"><span class="nc-unit">px</span></div></div>
                    <?php endforeach; ?><p class="nc-editor-hint"><?php echo esc_html(nc_t('Top overrides bottom; left overrides right. Empty fields keep the opposite edge.','Сверху имеет приоритет над снизу, слева — над справа. Пустая координата использует противоположный край.')); ?></p>
                </div>

                <!-- Appearance -->
                <div class="nc-card nc-card-appearance">
                    <div class="nc-card-title">&#127912; <?php echo nc_t( 'APPEARANCE', 'ВНЕШНИЙ ВИД' ); ?></div>
                    <div class="nc-ap-row">
                        <label for="nc-opt-sz"><?php echo nc_t( 'Size', 'Размер' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-opt-sz" name="settings[size]" value="<?php echo esc_attr( $c['size'] ); ?>" min="40" max="80" step="1">
                            <span class="nc-unit">px</span>
                        </div>
                        <span class="nc-ap-unit">40&ndash;80</span>
                    </div>
                    <div class="nc-ap-row">
                        <label for="nc-opt-sp"><?php echo nc_t( 'Animation', 'Анимация' ); ?></label>
                        <div class="nc-pos-input-wrap">
                            <input type="number" id="nc-opt-sp" name="settings[animation]" value="<?php echo esc_attr( $c['animation'] ); ?>" min="0" max="1000" step="50">
                            <span class="nc-unit">ms</span>
                        </div>
                    </div>
                    <div class="nc-ap-row">
                        <label for="nc-opt-cl"><?php echo nc_t( 'Color', 'Цвет' ); ?></label>
                        <input type="color" id="nc-opt-cl" name="settings[color]" value="<?php echo esc_attr( $c['color'] ); ?>">
                    </div>
                </div>

                <!-- Greeting -->
                <div class="nc-card nc-card-greeting">
                    <div class="nc-card-title">&#128172; <?php echo nc_t( 'GREETING MESSAGE', 'ПРИВЕТСТВЕННОЕ СООБЩЕНИЕ' ); ?></div>
                    <div class="nc-greeting-toggle">
                        <label class="nc-toggle">
                            <input type="checkbox" id="nc-opt-h" aria-label="<?php echo esc_attr(nc_t('Show greeting','Показывать приветствие')); ?>" name="settings[hint]"<?php checked( $show_greeting ); ?>>
                            <span class="nc-slider"></span>
                        </label>
                        <span class="nc-greeting-label"><?php echo nc_t( 'Show message', 'Показывать сообщение' ); ?></span>
                    </div>
                    <label class="nc-vis-check" for="nc-opt-hmobile">
                        <input type="checkbox" id="nc-opt-hmobile" name="settings[hint_mobile]"<?php checked(!empty($c['hint_mobile'])); ?>>
                        <em><?php echo nc_t('Show greeting on phones','Показывать подсказку на телефонах'); ?></em>
                    </label>
                    <div class="nc-greeting-fields<?php echo $show_greeting ? ' visible' : ' hidden'; ?>" id="nc-greeting-fields">
                        <div class="nc-editor-toolbar">
                            <button type="button" class="nc-editor-btn" data-action="bold" title="<?php echo esc_attr(nc_t('Bold','Жирный')); ?>"><b>B</b></button>
                            <button type="button" class="nc-editor-btn" data-action="italic" title="<?php echo esc_attr(nc_t('Italic','Курсив')); ?>"><i>I</i></button>
                            <button type="button" class="nc-editor-btn" data-action="break" title="<?php echo esc_attr(nc_t('Line break','Перенос строки')); ?>">&#8629;</button>
                            <button type="button" class="nc-editor-btn" data-action="link" title="<?php echo esc_attr(nc_t('Link','Ссылка')); ?>">&#128279;</button>
                        </div>
                        <textarea class="nc-greeting-textarea" id="nc-opt-ht" name="settings[hint_text]" rows="3" placeholder="<?php echo esc_attr( nc_t( 'e.g. Need help?', 'Например: Нужна помощь?' ) ); ?>"><?php echo esc_textarea( $c['hint_text'] ); ?></textarea>
                        <div class="nc-editor-hint"><?php echo nc_t( 'HTML: &lt;b&gt;, &lt;i&gt;, &lt;br&gt;, &lt;a href&gt;', 'HTML: &lt;b&gt;, &lt;i&gt;, &lt;br&gt;, &lt;a href&gt;' ); ?></div>
                        <div class="nc-scroll-setting">
                            <label for="nc-opt-hscroll"><?php echo nc_t('Greeting after page scroll (%)','Приветствие после прокрутки страницы (%)'); ?></label>
                            <input type="number" id="nc-opt-hscroll" name="settings[hint_scroll_percent]" value="<?php echo esc_attr($c['hint_scroll_percent']); ?>" min="0" max="100" step="1" required aria-describedby="nc-hscroll-help">
                            <small id="nc-hscroll-help"><?php echo nc_t('0: no scroll wait; 100: end of page. Time is measured from page navigation; both conditions are required.','0 — без ожидания прокрутки; 100 — конец страницы. Время отсчитывается от открытия страницы; оба условия обязательны.'); ?></small>
                        </div>
                        <div class="nc-greeting-delay">
                            <label for="nc-opt-hd"><?php echo nc_t( 'Greeting time from page navigation', 'Подсказка: время от открытия страницы' ); ?></label>
                            <div class="nc-delay-wrap">
                                <input type="number" id="nc-opt-hd" name="settings[hint_delay]" value="<?php echo esc_attr( $c['hint_delay'] ?? 2 ); ?>" min="0" max="600" step="1" required>
                                <span class="nc-delay-unit"><?php echo nc_t( 'sec', 'сек' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visibility -->
                <div class="nc-card nc-card-visibility">
                    <div class="nc-card-title">&#128065; <?php echo nc_t( 'VISIBILITY', 'ВИДИМОСТЬ' ); ?></div>
                    <div class="nc-scroll-setting">
                        <label for="nc-opt-bd"><?php echo nc_t('Button time from page navigation (seconds)','Кнопка: время от открытия страницы (секунды)'); ?></label>
                        <input type="number" id="nc-opt-bd" name="settings[button_delay]" value="<?php echo esc_attr($c['button_delay']); ?>" min="0" max="600" step="1" required aria-describedby="nc-bd-help">
                        <small id="nc-bd-help"><?php echo nc_t('0: no time wait. Appears once the time has elapsed and the chosen scroll percentage is reached.','0 — без ожидания времени. Появится, когда пройдёт заданное время и будет достигнут указанный процент прокрутки.'); ?></small>
                    </div>
                    <div class="nc-scroll-setting">
                        <label for="nc-opt-bscroll"><?php echo nc_t('Button after page scroll (%)','Кнопка после прокрутки страницы (%)'); ?></label>
                        <input type="number" id="nc-opt-bscroll" name="settings[button_scroll_percent]" value="<?php echo esc_attr($c['button_scroll_percent']); ?>" min="0" max="100" step="1" required aria-describedby="nc-bscroll-help">
                        <small id="nc-bscroll-help"><?php echo nc_t('0: no scroll delay; 100: end of page. Once shown, the button stays until reload.','0 — без ожидания прокрутки; 100 — конец страницы. После появления кнопка остаётся до перезагрузки.'); ?></small>
                    </div>
                    <div class="nc-vis-row">
                        <label class="nc-vis-check">
                            <input type="checkbox" id="nc-opt-d" name="settings[desktop]"<?php checked( ! empty( $c['desktop'] ) ); ?>>
                            <em><?php echo nc_t( 'Desktop', 'Десктоп' ); ?></em>
                        </label>
                        <label class="nc-vis-check">
                            <input type="checkbox" id="nc-opt-m" name="settings[mobile]"<?php checked( ! empty( $c['mobile'] ) ); ?>>
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

