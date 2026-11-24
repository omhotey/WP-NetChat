=== NetChat — каналы связи / Contact channels ===
Contributors: omhotey
Tags: contact, messenger, telegram, whatsapp
Requires at least: 5.0
Requires PHP: 7.2
Stable tag: 1.3.2
License: GPLv2 or later

== Description ==
NetChat 1.3.2 — виджет восьми каналов связи: Telegram, WhatsApp, Facebook Messenger, MAX, VK, Viber, Email, Phone. Размер, цвет, координаты, анимация, RU/EN, видимость устройств, задержки и пороги прокрутки кнопки и приветствия. Приветствие на мобильных выключено по умолчанию.
Contact launcher with eight channels, RU/EN settings, timing and scroll visibility controls. An independent addon may register a safe link or action through netchat_channels API v1.

== Installation ==
Настройки NetChat 1.3.0 и 1.3.1 хранятся в netchat_settings_v3 и сохраняются при обновлении. Внимание: при замене версии 1.2.3 и более ранних эта сборка не читает и не переносит её настройки, формы, заявки и JSON. Самостоятельная установка Forms не переносит эти данные автоматически; не обновляйте старую версию, пока не обеспечен и не проверен доступ к её данным. В этой сборке нет движка форм, заявок или уведомлений.
Updates preserve NetChat 1.3.x settings. Version 1.2.3 and earlier data is not migrated, and installing Forms does not migrate it automatically. Do not upgrade an older installation until its data is accessible through a verified migration.

== Changelog ==
= 1.3.2 =
Если доступна только форма, кнопка открывает её сразу и показывает значок формы. / The single form action opens directly with the form icon.

= 1.3.1 =
Прямое открытие формы, контакты для Forms и изменение порядка каналов на карточках. / Direct form action, contact API and card reordering.

= 1.3.0 =
Чистый launcher и API netchat.channels/1. / Clean launcher and channel action API.


= Админка R2 / Admin UI R2 =
Восстановлены оригинальные карточки, MD3-переключатели, брендовые иконки и оформление групп. Добавлены безопасный desktop/mobile preview и изменение порядка понятными кнопками вверх/вниз. Сохранение через AJAX сохраняет черновик при ошибках и конфликтах; сброс готовит только настройки виджета к явному сохранению. Публичный runtime/API и чистое хранилище не изменены.
Canonical MD3 channel cards and control layout restored; safe desktop/mobile preview and friendly channel ordering added. Async save preserves the draft on failure/conflict. Reset affects only widget draft settings and requires saving. Public runtime/API and fresh settings storage remain unchanged.
