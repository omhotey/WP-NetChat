# 🤖 AGENT ACTIVATION CONFIG — NetChat

**Версия:** 1.0
**Дата:** 2026-04-13
**Статус:** ✅ READY

---

## 🤖 АГЕНТЫ (7 активных)

### Промпты в папке `prompt/`

| Агент | Файл | Статус | Триггеры активации |
|-------|------|--------|-------------------|
| **Orchestrator** | `ORCHESTRATOR AGENT.md` | ✅ READY | "координируй", "план", "распредели" |
| **Frontend Agent** | `FRONTEND AGENT.md` | ✅ READY | "css", "стили", "анимация", "фронтенд" |
| **Backend Agent** | `BACKEND AGENT.md` | ✅ READY | "php", "api", "валидация", "сохранение" |
| **Code Review** | `CODE REVIEW AGENT.md` | ✅ READY | "проверь код", "review", "аудит" |
| **QA Agent** | `QA AGENT.md` | ✅ READY | "тест", "проверь", "баг", "debug" |
| **UX Agent** | `UX AGENT.md` | ✅ READY | "дизайн", "ui", "ux", "интерфейс" |
| **DevOps** | `DEVOPS AGENT.md` | ✅ READY | "собери zip", "архив", "deploy" |

---

## 🔄 AUTO-ACTIVATION WORKFLOW

### Как работает автоматическое переключение:

1. **Пользователь пишет запрос** → AI анализирует текст
2. **Распознавание триггера** → Определение нужного агента
3. **Активация агента** → Переключение контекста + загрузка промпта
4. **Выполнение задачи** → Агент решает задачу
5. **Возврат результата** → Пользователь получает ответ

### Примеры активации:

```
User: "Исправь стили hint bubble"
   ↓
AI: Триггер "стили" → Frontend Agent
   ↓
Frontend Agent: CSS правки в style.css
   ↓
Результат: "Стили исправлены ✅"
```

```
User: "Почему данные не сохраняются"
   ↓
AI: Триггер "сохраняются" → Backend Agent
   ↓
Backend Agent: Проверка validate_url + save_config
   ↓
Результат: "Валидация исправлена ✅"
```

```
User: "Собери ZIP архив"
   ↓
AI: Триггер "собери zip" → DevOps Agent
   ↓
DevOps Agent: Python скрипт сборки
   ↓
Результат: "ZIP готов ✅"
```

---

## 🎯 ТАБЛИЦА АКТИВАЦИИ АГЕНТОВ

| Запрос | Агент | Действие |
|--------|-------|----------|
| "Исправь CSS" | Frontend Agent | style.css правки |
| "Почини валидацию" | Backend Agent | helpers.php правки |
| "Проверь код" | Code Review Agent | Аудит |
| "Найди баг" | QA Agent | Диагностика |
| "Проверь дизайн" | UX Agent | UI рекомендации |
| "Собери ZIP" | DevOps Agent | Python сборка |
| "План исправления" | Orchestrator | Workflow |

---

## 📄 СВЯЗАННЫЕ ФАЙЛЫ

| Файл | Назначение |
|------|------------|
| `prompt/AGENTS.md` | Список всех агентов |
| `prompt/AGENT_WORKFLOW.md` | Матрица триггеров |
| `prompt/AGENT_ACTIVATION.md` | Этот файл |
| `prompt/ORCHESTRATOR AGENT.md` | Главный агент |

---

## 🎯 СТАТУС: ГОТОВО К РАБОТЕ

**Все агенты:** ✅ ACTIVE
**Workflow:** ✅ AUTO-SWITCH ENABLED
**Рабочая область:** d:\DEV\WP NetChat\netchat\

---

*Версия: 1.0 | Обновлено: 2026-04-13 | Вектор (Orchestrator)*
