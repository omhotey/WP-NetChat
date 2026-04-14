# 🤖 AI Agents Configuration — NetChat Project

**NetChat** — WordPress Plugin Development System

**Версия:** 1.0
**Статус:** ✅ READY

---

## 📋 АГЕНТЫ (7 активных)

| Агент | Роль | Триггеры | Статус |
|-------|------|----------|--------|
| **Orchestrator** | Управление workflow | "координируй", "план", "распредели" | ✅ READY |
| **Frontend Agent** | CSS/JS фронтенд виджет | "фронтенд", "css", "стили", "анимация", "виджет" | ✅ READY |
| **Backend Agent** | PHP логика, REST API | "php", "сервер", "api", "валидация", "сохранение" | ✅ READY |
| **Code Review Agent** | Проверка кода | "проверь код", "review", "аудит" | ✅ READY |
| **QA Agent** | Тестирование | "тест", "проверь", "баг" | ✅ READY |
| **UX Agent** | UI/UX, адаптивность | "дизайн", "ui", "ux", "интерфейс" | ✅ READY |
| **DevOps Agent** | ZIP сборка, деплой | "собери zip", "deploy", "архив" | ✅ READY |

---

## 🚀 АВТОМАТИЧЕСКОЕ ПЕРЕКЛЮЧЕНИЕ

Агенты переключаются **автоматически** по триггерам в запросе:

### Примеры:

| Запрос | Агент | Действие |
|--------|-------|----------|
| "Исправь стили хинта" | Frontend Agent | CSS правки |
| "Почини валидацию email" | Backend Agent | PHP правки |
| "Проверь код на ошибки" | Code Review Agent | Аудит |
| "Почему кнопка не появляется" | QA Agent | Дебаг |
| "Собери ZIP архив" | DevOps Agent | Сборка |
| "Координируй исправление" | Orchestrator | Workflow |

---

## 🎯 WORKFLOW PATTERNS

### Pattern 1: Исправление бага
```
"Кнопка не появляется на фронте"
   ↓
Orchestrator → QA Agent (диагноз) → Frontend Agent (фикс) → Code Review → ✅
```

### Pattern 2: Новая функция
```
"Добавь тултипы при наведении"
   ↓
Orchestrator → Frontend Agent (CSS+JS) → Code Review → ✅
```

### Pattern 3: Сборка и деплой
```
"Собери ZIP и проверь"
   ↓
DevOps Agent → QA Agent (проверка на сервере) → ✅
```

---

## ️ РАБОЧАЯ ОБЛАСТЬ

**Проект:** `d:\DEV\WP NetChat\netchat\`

**Ключевые файлы:**
| Файл | Назначение |
|------|------------|
| `includes/helpers.php` | Хранилище (JSON), валидация URL |
| `includes/admin.php` | Админка + REST API save |
| `includes/frontend.php` | Рендер виджета |
| `assets/css/style.css` | Фронтенд стили |
| `assets/css/admin.css` | Админка стили |
| `assets/js/admin.js` | Админка JS |
| `assets/js/front.js` | Фронтенд JS |
| `netchat.php` | Главный файл плагина |

**Конфиг:** `wp-content/netchat-data.json` (JSON файл настроек)

---

## ✅ СТАТУС ГОТОВНОСТИ

| Компонент | Статус |
|-----------|--------|
| **Агенты** | ✅ 7/7 READY |
| **Рабочая область** | ✅ DEFINED |
| **Workflow** | ✅ AUTO-SWITCH ENABLED |

---

**Версия:** 1.0
**Обновлено:** 2026-04-13
**Статус:** ✅ Agents synchronized for NetChat project
