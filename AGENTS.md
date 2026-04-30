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
