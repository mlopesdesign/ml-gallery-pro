# scripts/

Pipelines locais (PowerShell, Windows) e do CI (bash, Linux runner) que
garantem que cada mudança vire release versionada e empacotada de forma
reprodutível.

## Local (Windows / PowerShell 5.1+)

| Script              | Função                                                                 |
|---------------------|------------------------------------------------------------------------|
| `backup.ps1`        | Snapshot da pasta `ml-gallery-pro/` antes de qualquer mudança.         |
| `sync-version.ps1`  | Valida header / constante / readme estão com a mesma versão.           |
| `package.ps1`       | Empacota `ml-gallery-pro/` em `dist/ML-Gallery-Pro-vX_Y_Z.zip`.        |
| `release.ps1`       | Fluxo completo: backup → sync → package → instruções de commit/push.   |

### Uso típico

```powershell
cd B:\PLUGINS MINIMAX CODE\ml-gallery-pro\scripts

# 1) Antes de mexer no plugin
.\backup.ps1 -Reason "pre-feature-foo"

# 2) Apos ajustar header/constante/readme para a nova versao
.\sync-version.ps1

# 3) Build do ZIP
.\package.ps1 -Version 0.26.16

# 4) Release completa (imprime comandos para revisar e executar)
.\release.ps1 -Version 0.26.16 -Title "ML Gallery Pro v0.26.16 - Fix da capa" -Notes "..."
```

## CI (Linux / GitHub Actions runner)

| Script              | Equivalente local  | Função                              |
|---------------------|--------------------|-------------------------------------|
| `sync-version.sh`   | `sync-version.ps1` | Mesma validação, em bash.           |
| `package.sh`        | `package.ps1`      | Mesmo empacotamento, em bash + zip. |

Esses scripts são consumidos por `.github/workflows/release.yml` no push de
qualquer tag `v*`. O workflow também faz:

- `php -l` em todos os `.php` (sintaxe)
- `node --check` em todos os `.js` (sintaxe)
- Empacota o ZIP, computa SHA-256 e cria GitHub Release com o ZIP como asset

## Convenções

- **Versão** sempre `X.Y.Z` (sem prefixo `v` no header, constante, readme).
- **Tag** sempre `vX.Y.Z`.
- **ZIP** sempre `ML-Gallery-Pro-vX_Y_Z.zip` (com `v` no nome + underscores).
- **Pastas proibidas no ZIP**: `.git/`, `node_modules/`, `vendor/`, `*.log`, `.DS_Store`, `Thumbs.db`, `Desktop.ini`.
- **Pastas locais ignoradas pelo git**: `backups/`, `dist/`.
