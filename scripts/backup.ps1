#requires -Version 5.1
<#
.SYNOPSIS
    Cria snapshot local da pasta do plugin antes de qualquer mudanca.

.DESCRIPTION
    Copia a pasta ml-gallery-pro/ para backups/<timestamp>_<reason>/.
    Recuperavel: basta mover de volta. O diretorio backups/ esta no .gitignore.

.PARAMETER Reason
    Razao curta do backup (ex.: "pre-release-v0.26.16", "pre-updater-refactor").

.EXAMPLE
    .\backup.ps1
    .\backup.ps1 -Reason "pre-release-v0.26.16"
#>
[CmdletBinding()]
param(
    [string]$Reason = "manual"
)

$ErrorActionPreference = "Stop"

$root       = Resolve-Path (Join-Path $PSScriptRoot "..")
$pluginDir  = Join-Path $root "ml-gallery-pro"
$backupRoot = Join-Path $root "backups"
$stamp      = Get-Date -Format "yyyyMMdd-HHmmss"
$dest       = Join-Path $backupRoot "${stamp}_${Reason}"

if (-not (Test-Path $pluginDir)) {
    Write-Error "[backup] Pasta do plugin nao encontrada: $pluginDir"
    exit 1
}

New-Item -ItemType Directory -Force -Path $dest | Out-Null
Copy-Item -Path (Join-Path $pluginDir "*") -Destination $dest -Recurse -Force

$count = (Get-ChildItem -Recurse -File $dest).Count
Write-Host "[backup] OK -> $dest ($count arquivos)"
Write-Host "[backup] Para restaurar: mavis-trash ml-gallery-pro ; Move-Item '$dest' ml-gallery-pro"
