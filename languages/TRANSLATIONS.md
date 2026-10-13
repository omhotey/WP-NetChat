# NetChat Translations

**Версия:** 1.0.0 | **Обновлено:** 15 апреля 2026

## Available Languages

- ✅ **English (US)** - Default
- ✅ **Russian (ru_RU)** - Полный перевод

## Files Structure

```
languages/
├── netchat.pot      # Template file (53 strings)
├── ru_RU.po         # Russian translation source
├── ru_RU.mo         # Russian translation compiled
└── index.php        # Security
```

## Translation Statistics

| Language | Strings | Status |
|----------|---------|--------|
| English | 53 | ✅ Default |
| Russian | 58 | ✅ Complete |

## How to Add New Translation

### 1. Generate POT file
```bash
php generate-pot.php
```

### 2. Create PO file
Copy template and translate:
```bash
cp languages/netchat.pot languages/xx_XX.po
```

Edit `xx_XX.po` and translate all `msgstr` values.

### 3. Compile MO file
```bash
php compile-mo.php
```

### 4. Test
Set WordPress language to `xx_XX` and check translations.

## Translation Tools

### Online Editors
- POEditor: https://poeditor.com/
- Transifex: https://www.transifex.com/
- Weblate: https://weblate.org/

### Desktop Editors
- Poedit: https://poedit.net/ (Recommended)
- Virtaal: https://virtaal.translatehouse.org/

### WordPress Plugins
- Loco Translate: https://wordpress.org/plugins/loco-translate/
- WPML: https://wpml.org/

## Contributing

To contribute a translation:

1. Fork the repository
2. Create or edit `languages/xx_XX.po`
3. Compile to `languages/xx_XX.mo`
4. Submit Pull Request

## Credits

- **Russian Translation**: NetChat Team
- **Template Generator**: generate-pot.php
- **MO Compiler**: compile-mo.php
