# Проверки NetChat 1.2.3

## Сборка без WordPress

```sh
python tools/build-release.py
python tools/build-release.py --verify-only
python tools/test-release-dispatcher.py
python tools/test-style-delivery.py --php php
node tests/normalization.test.cjs
node tests/distribution-privacy.test.cjs
node tests/visibility-009.test.mjs
node tests/visibility-009-admin.test.mjs
```

Сборщик сравнивает 61 файл с закреплённым манифестом и воспроизводит принятый ZIP. Сам сборщик создаёт в `dist/release-1.2.3/` только `netchat-wp-1.2.3.zip`, его `.zip.manifest.json` и `SHA256SUMS.txt`; инструменты проверок сохраняют свои отчёты отдельно или в подпапках этого каталога. Второй установочный архив не выпускается. Указанные Node-проверки используют изоляцию и не подтверждают работу настоящего WordPress.

## Политика CSS и привязка исходников

`tools/test-style-delivery.py` передаёт исходный модуль и синтетические фикстуры через stdin в PHP CLI. 63 проверки охватывают URL/HTML/path guards, приоритет выключателя, сохранение исходного тега и точных CSS. WordPress, БД и почта не загружаются. Отчёт включает только SHA 61 runtime-файла; синтаксис проверяется только у runtime PHP. По умолчанию результат — dist/style-delivery-checks/php.json; в CI — dist/release-1.2.3/style-delivery-checks/php.json. Для существующего контейнера допустим явный --docker-container и имя; этот runner не создаёт контейнеры.

`tools/test-release-dispatcher.py` проверяет штатный entrypoint на временных копиях: точный ZIP, verify-only без изменения результата, отсутствие артефакта/модуля/require/builder, неизвестная и несогласованная версия, повреждённый ZIP и попытка скрыть изменение корня папкой source/. Проверка выполняется также в package job CI.

Сборка работает с корнем текущего checkout, без подмены папкой source/. Неизвестная/несогласованная версия, отсутствующие builder/модуль/require и отличающиеся runtime-байты приводят к ошибке. Для проверки без изменения готовых артефактов используйте --verify-only или PowerShell -VerifyOnly; -PythonPath позволяет явно указать установленный Python 3. Перед следующей версией требуется обновить зарегистрированный builder и его manifest сознательно, сохранив модуль CSS. [Политика и откат](../docs/CSS-016.md).

Локальный результат и hosted CI — разные проверки. Статус запуска для нужного коммита или тега смотрите в [GitHub Actions](https://github.com/omhotey/WP-NetChat/actions/workflows/ci.yml); наличие workflow или старый PASS не подтверждают текущую ревизию.

## Почтовой шаблон

```sh
NETCHAT_TEST_CORE=/path/to/existing/wordpress php tests/email-notifications.php
```

Скрипт читает только PHPMailer имеющегося ядра, не загружает WordPress, не обращается к SQL и не отправляет почту. 81 проверка на синтетических данных охватывает русские HTML/plain тексты, часовой пояс, полные ответы и переключатель текста обычной формы, экранирование HTML/заголовков/ссылок, MIME, временные обработчики и их очистку, повторы и отсутствие утечки AltBody в последующие письма. Для старого ядра используется его штатный PHPMailer.

## GitHub Actions

`.github/workflows/ci.yml` сначала воспроизводит точный ZIP на Windows. Этот артефакт передаётся парам WordPress 5.0 / PHP 7.2 и WordPress 7.1.3 / PHP 8.3. `bash tests/compatibility-ci.sh` требует `CI=true` и `GITHUB_ACTIONS=true`: сценарий предназначен исключительно для временного worker. Зависимости устанавливаются только там.

Сохраняются SQL-наборы wp-integration, leads, service-forms, editor, lifecycle и data-transfer; normalization/privacy/009; установка, переустановка, активация, обычное удаление и явная очистка; старые архивы 1.0.0/1.2.0 и браузерные проверки форм, переноса и прокрутки. `release-121-upgrade.sh` сохраняет историческое имя, но проверяет обновление до 1.2.3. Оба прежних имени папки проверяются с явным переходом в `netchat/`. Basename ожидаемо меняется только при этом переходе; настройки, формы и заявки сравниваются по SHA256.

## Локальные ограничения

Общий `tests/run.ps1` автоматически не запускается: прежние сценарии меняют тестовые настройки и результаты. Их нельзя направлять на рабочий сайт или чужие данные. Любая локальная проверка с записью требует отдельной изоляции.

Публичные результаты CI содержат только синтетические JSON и снимки. В SQL-тестах почтовый транспорт заменён `/bin/true` и `/bin/false`, реальная доставка не выполняется. Автоматический cron-loopback отключён только во временном браузерном ядре; обработчики проверяются через WP-CLI.

[Проверки выпуска](../docs/RELEASE_CHECKLIST.md) · [Обновление](../docs/UPGRADE.md).
