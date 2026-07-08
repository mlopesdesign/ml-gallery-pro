# ML Gallery Pro — Permanent Agent Rules

## Environment

This project does NOT use Champ.

Never search for Champ.
Never inspect Champ configuration.
Never ask about Champ.
Never attempt to run Champ.
Never use Champ as part of build, validation, packaging, tests, or deployment.

This project uses Docker for safe build/validation/packaging when execution is required.

## Execution Rules

* Use Docker-based execution only when commands are needed.
* Do not use local PowerShell, CMD, bash/sh, wp-cli, or host-level package tools.
* Do not assume Champ exists.
* Do not waste time or tokens looking for Champ.
* If a build/test/package step is needed, use Docker or the existing repository workflow only.

## WordPress Plugin Rules

* Work only on the existing plugin root.
* Keep slug unchanged: `ml-gallery-pro`
* Keep root folder unchanged: `ml-gallery-pro/`
* Keep main file unchanged: `ml-gallery-pro/ml-gallery-pro.php`
* Final ZIP must install as UPDATE over the existing plugin.
* Never create a parallel plugin.
* Never rename the root folder.
* Never package from a temporary renamed folder.

## Versioning Rules

Synchronize version in:

* plugin header
* `MLGP_VERSION`
* `readme.txt` Stable tag
* changelog

## Scope Control

Only modify files required by the task.
Do not inspect unrelated systems unless needed.
Do not refactor unrelated code.
Do not touch Grid, Grid Plus, import, media engine, license, or updater unless the task explicitly requires it.

---

## Release Workflow

Every change that ships a new version follows the same flow. **No exceptions.**

### 1. Backup first (local)

```powershell
cd scripts
.\backup.ps1 -Reason "pre-release-vX.Y.Z"
```

This snapshots `ml-gallery-pro/` into `backups/<timestamp>_<reason>/`.
`backups/` is git-ignored but recoverable from the OS Recycle Bin via `mavis-trash`.

### 2. Update version in **all** sync points

The version MUST be identical in:

| Location                            | Field                |
|-------------------------------------|----------------------|
| `ml-gallery-pro/ml-gallery-pro.php`| Plugin header `Version:` |
| `ml-gallery-pro/ml-gallery-pro.php`| `define('MLGP_VERSION', ...)` |
| `ml-gallery-pro/readme.txt`        | `Stable tag:`        |
| `CHANGELOG.md`                      | New top entry        |
| `ml-gallery-pro/readme.txt`        | `= X.Y.Z =` block    |
| Git tag                             | `vX.Y.Z`             |
| Release asset (ZIP)                 | `ML-Gallery-Pro-vX_Y_Z.zip` |

Validate before doing anything else:

```powershell
.\sync-version.ps1
```

### 3. Build the ZIP

```powershell
.\package.ps1 -Version X.Y.Z
# -> dist/ML-Gallery-Pro-vX_Y_Z.zip + SHA-256
```

ZIP MUST:

- Have `ml-gallery-pro/` as the **root** folder.
- Exclude `.git/`, `node_modules/`, `vendor/`, `*.log`, `.DS_Store`, `Thumbs.db`.
- Be installable via `WP Admin > Plugins > Upload` as an **update** over the existing plugin.

### 4. Commit, tag, push

```powershell
git add -A
git -c user.name=mlopesdesign -c user.email=mlopesdesign@gmail.com commit -m "release: vX.Y.Z"
git -c user.name=mlopesdesign -c user.email=mlopesdesign@gmail.com tag -a vX.Y.Z -m "ML Gallery Pro vX.Y.Z"
git push origin main --follow-tags
```

Or use the full orchestrator:

```powershell
.\release.ps1 -Version X.Y.Z -Title "ML Gallery Pro vX.Y.Z - <resumo>" -Notes "<markdown changelog>"
```

### 5. CI auto-publishes the Release

`.github/workflows/release.yml` listens for tag pushes matching `v*` and:

1. Runs `php -l` and `node --check` on every file.
2. Re-runs `sync-version.sh` and `package.sh`.
3. Computes SHA-256 + size.
4. Creates a GitHub Release with the ZIP as the only asset and `generate_release_notes: true`.

If the CI build fails, **fix the cause** before re-pushing the tag. Don't `git tag -f`.

### Auto-update on customer sites

`includes/Core/Updater.php` (do not touch without explicit request) calls
`https://api.github.com/repos/mlopesdesign/ml-gallery-pro/releases/latest`
and exposes the asset as a native WordPress update. The asset name is matched
in this order:

1. `ML-Gallery-Pro-vX_Y_Z.zip` (AGENT_RULES standard)
2. `ml-gallery-pro-vX.Y.Z.zip` (lowercase, dots)
3. `ml-gallery-pro-X.Y.Z.zip` (no `v`)

Always ship the first one. The others are fallbacks for legacy zips.

---

## What this agent should NOT do

- Don't rename `ml-gallery-pro/`, the main file, or the slug.
- Don't touch the Updater, License Manager, Grid, Grid Plus, Media Storage,
  or Database Repository unless the task explicitly says so.
- Don't introduce jQuery, React, or any framework dependency.
- Don't commit `*.zip`, `backups/`, `dist/`, or scratch folders.
- Don't rebase or force-push `main`.
- Don't create parallel plugins.
