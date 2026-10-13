# 🛠 Руководство разработчика NetChat

> Техническая документация для разработчиков, расширяющих или модифицирующих плагин

**Версия:** 1.0.0 (Stable Release) | **Обновлено:** 15 апреля 2026

---

## 📋 Содержание

- [Архитектура плагина](#архитектура-плагина)
- [Структура файлов](#структура-файлов)
- [Хранение данных](#хранение-данных)
- [Константы](#константы)
- [Функции API](#функции-api)
- [WordPress хуки](#wordpress-хуки)
- [REST API](#rest-api)
- [Расширение плагина](#расширение-плагина)
- [Безопасность](#безопасность)

---

## 🏗 Архитектура плагина

NetChat — это лёгкий WordPress плагин без внешних зависимостей. Все настройки хранятся в JSON-файле, а не в базе данных WordPress.

### Поток данных

```
JSON-файл (wp-content/netchat-data.json)
```

> Для корректной работы плагина папка `wp-content` должна быть доступна для записи.

```
         ↓
  netchat_get_config()
         ↓
   ┌─────┴─────┐
   ↓           ↓
admin.php   frontend.php
(админка)   (виджет на сайте)
```

---

## 📁 Структура файлов

```
netchat/
├── netchat.php           # Точка входа, константы, инициализация
├── readme.txt            # WordPress.org readme
├── README.md             # GitHub документация
├── LICENSE               # GPLv2+ лицензия
│
├── includes/
│   ├── admin.php         # Админ-панель (меню, форма, REST API сохранение)
│   ├── frontend.php      # Фронтенд (подключение CSS/JS, рендер виджета)
│   └── helpers.php       # Утилиты (хранение, валидация, SVG, мессенджеры)
│
├── assets/
│   ├── css/
│   │   ├── admin.css     # Стили админки WordPress
│   │   └── style.css     # Стили виджета на фронтенде
│   ├── js/
│   │   ├── admin.js      # JS админки (REST API сохранение, валидация, управление каналами)
│   │   └── front.js      # JS виджета (toggle меню, клавиатура)
│   └── img/              # SVG иконки мессенджеров
│       ├── TG.svg, WhatsUp.svg, VK.svg, Max.svg
│       ├── Viber.svg, FB.svg, Email.svg, Tel.svg
│
└── languages/            # Локализация
    ├── ru_RU.mo          # Русский (binary)
    ├── ru_RU.po          # Русский (source)
    └── netchat.pot       # Template
```

---

## 💾 Хранение данных

### JSON-файл

Плагин **не использует** базу данных WordPress для хранения настроек. Все данные хранятся в файле:

```
wp-content/netchat-data.json
```

Константа `NETCHAT_DATA` определена в `helpers.php`:

```php
define( 'NETCHAT_DATA', WP_CONTENT_DIR . '/netchat-data.json' );
```

### Формат данных

```json
{
    "enabled": {
        "telegram": 1,
        "whatsapp": 1,
        "messenger": 0,
        "max": 0,
        "vk": 0,
        "viber": 0,
        "email": 0,
        "phone": 0
    },
    "links": {
        "telegram": "username",
        "whatsapp": "79001234567",
        "messenger": "",
        "max": "",
        "vk": "",
        "viber": "",
        "email": "",
        "phone": ""
    },
    "position": {
        "top": "",
        "right": "20",
        "bottom": "30",
        "left": ""
    },
    "size": 56,
    "animation": 300,
    "desktop": 1,
    "mobile": 1,
    "hint": 0,
    "hint_text": "",
    "hint_delay": 2,
    "order": "telegram,whatsapp,messenger,max,vk,viber,email,phone",
    "color": "#2563eb",
    "lang": "ru"
}
```

### Функции работы с данными

```php
// Чтение конфигурации из JSON-файла
function netchat_get_config(): array;

// Сохранение конфигурации в JSON-файл
function netchat_save_config( $config ): bool;

// Удаление файла настроек (сброс)
function netchat_delete_config(): bool;

// Получение значений по умолчанию
function netchat_defaults(): array;
```

> **Важно:** Плагин НЕ использует `get_option()`, `update_option()`, `wp_cache_get()` или `wp_cache_set`. Всё работает через прямой доступ к JSON-файлу.

---

## 🔑 Константы

```php
// netchat.php
define( 'NETCHAT_FILE', __FILE__ );            // Путь к главному файлу
define( 'NETCHAT_DIR',  plugin_dir_path( __FILE__ ) ); // Абсолютный путь к папке
define( 'NETCHAT_URL',  plugin_dir_url( __FILE__ ) );  // URL папки
define( 'NETCHAT_VER',  '1.0.0' );             // Версия плагина

// helpers.php
define( 'NETCHAT_DATA', WP_CONTENT_DIR . '/netchat-data.json' ); // Файл данных
```

---

## 🔧 Функции API

### Конфигурация

```php
/**
 * Получить текущую конфигурацию из JSON-файла
 *
 * @return array {
 *     @type array  $enabled     Включённые мессенджеры (ключ => 1/0)
 *     @type array  $links       Ссылки/данные мессенджеров
 *     @type array  $position    Позиция (top, right, bottom, left)
 *     @type int    $size        Размер кнопки (px)
 *     @type int    $animation   Скорость анимации (мс)
 *     @type string $color       HEX цвет кнопки
 *     @type int    $desktop     Показывать на десктопе (1/0)
 *     @type int    $mobile      Показывать на мобильных (1/0)
 *     @type int    $hint        Показывать приветствие (1/0)
 *     @type string $hint_text   Текст приветственного сообщения
 *     @type int    $hint_delay  Задержка приветствия (сек)
 *     @type string $order       Порядок мессенджеров (через запятую)
 *     @type string $lang        Язык интерфейса ('ru' | 'en')
 * }
 */
function netchat_get_config(): array;
```

### Мессенджеры

```php
/**
 * Получить список всех мессенджеров с метаданными
 *
 * @return array {
 *     @type string $name   Отображаемое имя
 *     @type string $icon   Идентификатор иконки
 *     @type string $scheme URL-схема для формирования ссылки
 *     @type string $color  HEX цвет по умолчанию
 * }
 */
function netchat_messengers(): array;

// Реальный возврат:
// telegram  => name:'Telegram',  scheme:'https://t.me/',    color:'#0088cc'
// whatsapp  => name:'WhatsApp',  scheme:'https://wa.me/',   color:'#25D366'
// messenger => name:'Facebook',  scheme:'https://m.me/',    color:'#0084FF'
// max       => name:'MAX',       scheme:'https://max.ru/',  color:'#FF6B35'
// vk        => name:'ВКонтакте', scheme:'https://vk.me/',   color:'#0077FF'
// viber     => name:'Viber',     scheme:'viber://chat?number=', color:'#7360F2'
// email     => name:'Email',     scheme:'mailto:',          color:'#EA4335'
// phone     => name:'Телефон',   scheme:'tel:',             color:'#34C759'
```

### Проверка активности

```php
/**
 * Проверить, включён ли хотя бы один мессенджер с заполненными данными
 *
 * @return bool
 */
function netchat_is_active(): bool;
```

### Валидация ссылок

```php
/**
 * Валидировать и нормализовать ссылку мессенджера
 *
 * @param string $url  Сырая ссылка от пользователя
 * @param string $type Ключ мессенджера
 * @return string Валидированная полная ссылка или пустая строка
 */
function netchat_validate_url( string $url, string $type ): string;
```

#### Правила валидации по типам

| Тип | Что принимает | Результат |
|-----|---------------|-----------|
| `telegram` | `username` (без @) или `https://t.me/...` | `https://t.me/username` |
| `whatsapp` | Только цифры (10-15) или `https://wa.me/...` | `https://wa.me/79001234567` |
| `vk` | `username` или `vk.com/...` / `vk.me/...` | `https://vk.me/username` |
| `max` | Полная ссылка `https://max.ru/...` | как есть |
| `viber` | Номер с `+` | `viber://chat?number=%2B79001234567` |
| `messenger` | `username` или `m.me/...` | `https://m.me/username` |
| `email` | Email или `mailto:...` | `mailto:info@example.com` |
| `phone` | Номер в любом формате | `tel:+79001234567` |

### Утилиты

```php
/**
 * Перевод строки (RU/EN)
 */
function nc_t( string $en, string $ru ): string;

/**
 * Получить SVG-иконку мессенджера
 */
function netchat_svg_icon( string $type ): string;

/**
 * Удалить файл настроек (сброс к умолчанию)
 */
function netchat_clear_cache(): void; // На самом деле вызывает netchat_delete_config()
```

---

## 🔌 WordPress хуки

### Реальные хуки, используемые в плагине

| Хук | Файл | Назначение |
|-----|------|------------|
| `plugins_loaded` | `netchat.php` | Загрузка текстового домена |
| `admin_menu` | `admin.php` | Добавление страницы настроек |
| `admin_enqueue_scripts` | `admin.php` | Подключение CSS/JS админки |
| `wp_enqueue_scripts` | `frontend.php` | Подключение CSS/JS фронтенда |
| `wp_footer` | `frontend.php` | Вывод виджета в footer сайта |
| `rest_api_init` | `admin.php` | Регистрация REST API endpoint |

### Действия плагина

```php
// ⚠️ В текущей версии (1.0.0) плагин НЕ добавляет собственных action-хуков.
// Если вам нужны события при сохранении настроек — используйте REST API ответ.
```

### Фильтры плагина

```php
// ⚠️ В текущей версии (1.0.0) плагин НЕ добавляет собственных filter-хуков.
// Для расширения функционала можно форкнуть репозиторий.
```

---

## 🌐 REST API

### Endpoint

```
POST /wp-json/netchat/v1/settings
```

**Требования:**
- Аутентификация: `current_user_can('manage_options')`
- Nonce: `X-WP-Nonce` заголовок

**Формат запроса:**

```json
{
    "enabled": { "telegram": 1, "whatsapp": 1 },
    "links": { "telegram": "username", "whatsapp": "79001234567" },
    "position": { "right": 20, "bottom": 30, "top": "", "left": "" },
    "size": 56,
    "animation": 300,
    "color": "#2563eb",
    "hint": 1,
    "hint_text": "Привет!",
    "hint_delay": 2,
    "desktop": 1,
    "mobile": 1,
    "lang": "ru",
    "order": "telegram,whatsapp,vk"
}
```

**Ответ:**

```json
{
    "success": true,
    "message": "Настройки сохранены!",
    "lang_changed": false
}
```

---

## 🔌 Расширение плагина

### Добавление нового мессенджера

Поскольку в версии 1.0.0 нет filter-хуков, для добавления нового мессенджера нужно:

1. Добавить запись в `netchat_messengers()` в `helpers.php`:

```php
'messenger_key' => [
    'name'   => 'Messenger Name',
    'icon'   => 'messenger_key',
    'scheme' => 'https://example.me/',
    'color'  => '#FF0000',
],
```

2. Добавить SVG-иконку в `netchat_svg_icon()` в `helpers.php`

3. Добавить правило валидации в `netchat_validate_url()` в `helpers.php`

4. Обновить `netchat_defaults()` — добавить ключ в `enabled` и `links`

### Кастомизация через тему

```php
// Переопределение CSS виджета через тему
add_action( 'wp_enqueue_scripts', function() {
    wp_add_inline_style( 'netchat-front', '
        #netchat .nc-main { border-radius: 8px !important; }
    ' );
}, 40 );
```

---

## 🔒 Безопасность

### Проверки в плагине

```php
// 1. Защита от прямого доступа
if ( ! defined( 'ABSPATH' ) ) exit;

// 2. Проверка прав (админка)
if ( ! current_user_can( 'manage_options' ) ) wp_die( '...' );

// 3. REST API permission_callback
'permission_callback' => function () {
    return current_user_can( 'manage_options' );
}

// 4. Санитизация ввода
$config['hint_text'] = wp_kses_post( $data['hint_text'] ?? '' );
$config['size'] = max( 40, min( 80, intval( $data['size'] ?? 56 ) ) );

// 5. Экранирование вывода
esc_url( $url );
esc_attr( $value );
esc_html( $text );
wp_kses_post( $html );
```

### Рекомендации по безопасности

1. Файл `netchat-data.json` находится вне папки плагина — это намеренное решение для защиты от перезаписи при обновлении
2. Файл доступен для чтения/записи веб-сервером — убедитесь, что права доступа корректны (644 или 664)
3. REST API endpoint защищён проверкой `manage_options`

---

## 📊 Структура данных

### Значения по умолчанию (v1.0.0)

```php
function netchat_defaults(): array {
    return [
        'enabled'   => [ 'telegram'=>0, 'whatsapp'=>0, 'messenger'=>0, 'max'=>0, 'vk'=>0, 'viber'=>0, 'email'=>0, 'phone'=>0 ],
        'links'     => [ 'telegram'=>'', 'whatsapp'=>'', 'messenger'=>'', 'max'=>'', 'vk'=>'', 'viber'=>'', 'email'=>'', 'phone'=>'' ],
        'position'  => [ 'top'=>'', 'right'=>'', 'bottom'=>'30', 'left'=>'' ],
        'size'      => 56,
        'animation' => 300,
        'desktop'   => 1,
        'mobile'    => 1,
        'hint'      => 0,
        'hint_text' => '',
        'hint_delay'=> 2,
        'order'     => 'telegram,whatsapp,messenger,max,vk,viber,email,phone',
        'color'     => '#2563eb',
        'lang'      => 'ru',
    ];
}
```

> **Все мессенджеры по умолчанию ОТКЛЮЧЕНЫ.** Пользователь должен сам включить нужные.

---

## 📦 Сборка и релиз

### Создание ZIP для WordPress.org

```bash
# 1. Обновите версию в netchat.php (сейчас 1.0.0)
# 2. Обновите changelog в readme.txt
# 3. Создайте ZIP
zip -r netchat-wp.zip netchat/ \
  -x "netchat/.git/*" \
  -x "netchat/preview/*" \
  -x "netchat/docs/*" \
  -x "*.md" \
  -x ".gitignore"
```

### Текущий релиз

**Версия:** 1.0.0 (Stable)
**Дата:** 15 апреля 2026
**Статус:** ✅ Выпущен

### Семантическое версионирование

| Изменение | Версия |
|-----------|--------|
| Обратная совместимость, баг-фиксы | `1.0.1` (patch) |
| Новые функции, обратно совместимо | `1.1.0` (minor) |
| Breaking changes | `2.0.0` (major) |

> **Breaking change:** переход с JSON-файла на WP options потребует миграции данных.

---

## 🗺️ Планы развития

> Это roadmap — функции, которые **могут** появиться в будущих версиях. Ничего из этого ещё не реализовано.

| Функция | Версия | Приоритет | Описание |
|---------|--------|-----------|----------|
| Шорткод `[netchat]` | 1.1.0 | 🟡 Средний | Ручная вставка виджета в контент страниц |
| Gutenberg блок | 1.1.0 | 🟡 Средний | Блок для редактора WordPress с превью |
| Action-хуки | 1.2.0 | 🟡 Средний | `netchat_loaded`, `netchat_settings_saved` для интеграции |
| Filter-хуки | 1.2.0 | 🟡 Средний | `netchat_config`, `netchat_messengers` для кастомизации |
| Встроенный чат | 1.3.0 | 🟠 Высокий | Окно чата прямо на сайте (без перехода в мессенджер) |
| Чатботы и ИИ-агенты | 1.4.0 | 🟠 Высокий | Подключение AI-ассистентов для автоответов |
| Метрики и аналитика | 1.4.0 | 🟡 Средний | Статистика кликов, конверсий, популярных каналов |
| Интеграция с CRM | 1.5.0 | 🟡 Средний | Передача заявок в Bitrix24, AmoCRM и др. |
| Мультисайт | 2.0.0 | 🔵 Низкий | Поддержка WordPress Multisite |
| Unit-тесты | 2.0.0 | 🔵 Низкий | PHPUnit для валидации и хранения данных |
| E2E тесты | 2.0.0 | 🔵 Низкий | Playwright для фронтенда |

---

## 🤝 Контрибьюция

1. Форкните репозиторий
2. Создайте ветку: `git checkout -b feature/my-feature`
3. Внесите изменения
4. Отправьте PR: `git push origin feature/my-feature`

---

**Последнее обновление:** 15 апреля 2026

**Версия:** 1.0.0 (Stable Release)

**Автор:** [omhotey](https://github.com/omhotey)
