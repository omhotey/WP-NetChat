# 🤖 AGENT AUTO-SWITCH WORKFLOW — NetChat

**Автоматическое переключение агентов по триггерам**

---

## 🎯 ТРИГГЕРЫ АГЕНТОВ

### Orchestrator (Вектор)
**Триггеры:**
- "координируй", "управляй", "план", "распредели"
- "workflow", "оптимизируй", "статус"

**Действие:** Анализ → Распределение → Мониторинг

---

### Frontend Agent
**Триггеры:**
- "фронтенд", "css", "стили", "анимация"
- "виджет", "кнопка", "тултип", "hint"
- "hover", "transition", "responsive"

**Действие:** CSS/JS правки → Проверка → Отчёт

---

### Backend Agent
**Триггеры:**
- "php", "сервер", "api", "валидация"
- "сохранение", "json", "rest", "endpoint"
- "не сохраняет", "данные теряются"

**Действие:** PHP правки → Проверка → Отчёт

---

### Code Review Agent
**Триггеры:**
- "проверь код", "review", "аудит"
- "безопасность", "security", "xss"
- "оптимизируй", "производительность"

**Действие:** Анализ → Feedback → Approval

---

### QA Agent
**Триггеры:**
- "тест", "проверь", "баг", "ошибка"
- "не работает", "почему", "debug"
- "лог", "консоль", "debug.log"

**Действие:** Диагностика → Отчёт → Fix verification

---

### UX Agent
**Триггеры:**
- "дизайн", "ui", "ux", "интерфейс"
- "цвет", "шрифт", "layout", "responsive"
- "material design", "адаптивность"

**Действие:** Анализ → Рекомендации → Implementation

---

### DevOps Agent
**Триггеры:**
- "собери zip", "архив", "deploy"
- "загрузи на сервер", "установи"
- "проверь структуру"

**Действие:** Сборка → Валидация → Готово

---

## 🔄 WORKFLOW PATTERNS

### Pattern 1: Исправление бага
```
User: "Кнопка не появляется на фронте"
   ↓
Orchestrator: Классификация (🔴 CRITICAL)
   ↓
QA Agent: Диагностика
   ↓
Frontend/Backend Agent: Исправление
   ↓
Code Review Agent: Проверка
   ↓
Orchestrator: Approval ✅
```

### Pattern 2: Новая функция
```
User: "Добавь тултипы при наведении"
   ↓
Orchestrator: Анализ
   ↓
Frontend Agent: CSS + JS реализация
   ↓
Code Review Agent: Проверка
   ↓
Orchestrator: Approval ✅
```

### Pattern 3: Сборка и деплой
```
User: "Собери ZIP архив"
   ↓
DevOps Agent: Сборка через Python
   ↓
QA Agent: Проверка на сервере
   ↓
Orchestrator: Approval ✅
```

---

## 📊 MATRIX: ЗАДАЧА → АГЕНТ

| Тип задачи | Основной агент | Поддержка |
|------------|----------------|-----------|
| **CSS/JS фикс** | Frontend Agent | QA Agent |
| **PHP фикс** | Backend Agent | QA Agent |
| **Code Review** | Code Review Agent | Frontend/Backend Agent |
| **Тестирование** | QA Agent | Frontend/Backend Agent |
| **Дизайн/UI** | UX Agent | Frontend Agent |
| **Сборка ZIP** | DevOps Agent | QA Agent |
| **Координация** | Orchestrator | Все агенты |

---

## 🎯 AUTO-DETECTION RULES

### Правило 1: Приоритет критичных задач
Если запрос содержит "не работает", "ошибка", "баг", "вылетает"
→ **Немедленно** Orchestrator + QA Agent (🔴 CRITICAL)

### Правило 2: Контекстная память
Если предыдущая задача была по Frontend → **Автоматически** Frontend Agent для смежных задач

### Правило 3: Мульти-агент
Если задача сложная (>3 подзадач) → Orchestrator создаёт workflow

### Правило 4: Эскалация
Если агент не справляется (2 попытки) → Orchestrator переназначает

---

*Версия: 1.0 | Обновлено: 2026-04-13 | Вектор (Orchestrator)*
