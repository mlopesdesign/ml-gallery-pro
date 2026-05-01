# AGENT_RULES.md — ML Gallery Pro

## Identity

- **Plugin Name:** ML Gallery Pro
- **Slug:** `ml-gallery-pro`
- **Main File:** `ml-gallery-pro/ml-gallery-pro.php`
- **Author:** mlopesdesign.com

---

## Versioning Standard

Every release MUST have matching versions across **all five locations**:

| Location | Example |
|---|---|
| Plugin header (`Version:`) | `0.26.0` |
| PHP constant (`MLGP_VERSION`) | `0.26.0` |
| `readme.txt` (`Stable tag:`) | `0.26.0` |
| Git tag | `v0.26.0` |
| ZIP filename | `ML-Gallery-Pro-v0_26_0.zip` |

**No mismatch is ever acceptable.**

Increment rules:
- **Patch** (`0.26.0` → `0.26.1`): bug fixes, CSS tweaks, copy changes
- **Minor** (`0.26.x` → `0.27.0`): new features, new UI sections, new AJAX endpoints
- **Major** (`0.x.y` → `1.0.0`): breaking changes, schema migrations, public API changes

---

## Packaging Standard

### ZIP Structure

```
ML-Gallery-Pro-vX_Y_Z.zip
└── ml-gallery-pro/
    ├── ml-gallery-pro.php
    ├── readme.txt
    ├── assets/
    │   ├── css/
    │   ├── js/
    │   └── images/
    └── includes/
        ├── Admin/
        ├── Blocks/
        ├── Core/
        ├── Database/
        ├── Frontend/
        ├── License/
        └── Media/
```

### ZIP Rules

- Root folder inside ZIP MUST be `ml-gallery-pro/`
- ZIP MUST be installable via WordPress > Plugins > Upload
- ZIP MUST NOT contain `.git/`, `node_modules/`, `*.log`, `.DS_Store`
- ZIP filename: `ML-Gallery-Pro-vX_Y_Z.zip` (underscores for dots)

---

## Git Workflow

### Branches

- `main` — stable, always releasable
- `dev` — active development (optional)
- Feature branches: `feature/scan-storage`, `fix/search-freeze`, etc.

### Tags

- Format: `vX.Y.Z` (e.g., `v0.26.0`)
- Tag MUST match all version fields before pushing
- Tag on `main` only

### Commits

- Prefix: `fix:`, `feat:`, `refactor:`, `style:`, `docs:`, `chore:`
- Example: `feat: add published_at editable date field to galleries and albums`
- One logical change per commit when possible

---

## Code Rules

### PHP

- WordPress coding standards
- `capability check` (manage_options) on every AJAX handler
- `nonce` validation on every request
- `sanitize` all inputs, `escape` all outputs
- `$wpdb->prepare()` for all SQL with variables
- UTF-8 encoding only — no ANSI, no ISO-8859-1, no mojibake

### JavaScript

- No frameworks — vanilla JS only
- Template literals for HTML generation
- Event delegation on root container
- `escapeHtml()` for all user-generated content
- Debounce search inputs (300-500ms)
- Never rebuild DOM containers that hold active inputs

### CSS

- Scoped under `.mlgp-*` classes only
- CSS custom properties for brand colors (`--mlgp-brand`, `--mlgp-ink`, etc.)
- Mobile-first responsive (`640px`, `768px`, `960px`, `1280px`)
- No `!important` unless overriding WordPress admin styles

---

## Forbidden Actions

- Never rebuild from scratch
- Never rename slug, folder, or main file
- Never remove working features
- Never deliver partial snippets
- Never change database schema without `dbDelta` migration
- Never touch frontend rendering without explicit request
- Never break existing save/update AJAX flows
- Never introduce dependencies (jQuery, React, etc.)

---

## Release Checklist

Before creating a release:

1. [ ] Version matches in: header, constant, readme.txt
2. [ ] `node --check` passes on all `.js` files
3. [ ] No PHP syntax errors
4. [ ] No console errors in browser
5. [ ] All existing features still work
6. [ ] ZIP contains only `ml-gallery-pro/` root
7. [ ] ZIP filename matches version
8. [ ] Git tag matches version
9. [ ] Commit message describes the change
10. [ ] UTF-8 encoding verified on all files
