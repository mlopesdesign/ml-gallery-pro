#requires -Version 5.1
<#
.SYNOPSIS
    Empacota ml-gallery-pro/ em um ZIP instalavel seguindo AGENT_RULES.md.

.DESCRIPTION
    - Pasta raiz do ZIP: ml-gallery-pro/
    - Exclui .git/, .DS_Store, Thumbs.db, *.log, node_modules/, .idea/, .vscode/
    - Nome do arquivo: ML-Gallery-Pro-vX_Y_Z.zip (underscores nos pontos)
    - Roda sync-version.ps1 antes de empacotar.

.PARAMETER Version
    Versao no formato X.Y.Z (sem prefixo v).

.EXAMPLE
    .\package.ps1 -Version 0.26.16
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string]$Version
)

$ErrorActionPreference = "Stop"

$root       = Resolve-Path (Join-Path $PSScriptRoot "..")
$pluginDir  = Join-Path $root "ml-gallery-pro"
$distDir    = Join-Path $root "dist"
$pluginFile = Join-Path $pluginDir "ml-gallery-pro.php"

# Valida formato
if ($Version -notmatch '^\d+\.\d+\.\d+$') {
    Write-Error "[package] Versao invalida: '$Version' (esperado X.Y.Z)"
    exit 1
}

# Sincronizacao de versao
& (Join-Path $PSScriptRoot "sync-version.ps1") | Out-Null
if ($LASTEXITCODE -ne 0) { exit 1 }

# Confere que a versao pedida eh a que esta no plugin
$headerVer = (Select-String -Path $pluginFile -Pattern '^\s*\*\s*Version:\s*(\S+)' | Select-Object -First 1).Matches.Groups[1].Value
if ($headerVer -ne $Version) {
    Write-Error "[package] Versao do header ($headerVer) difere da pedida ($Version)"
    exit 1
}

# Caminho do ZIP
$underscored = ($Version -replace '\.', '_')
$zipName     = "ML-Gallery-Pro-v$underscored.zip"
$zipPath     = Join-Path $distDir $zipName

New-Item -ItemType Directory -Force -Path $distDir | Out-Null
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

# Lista de arquivos (com exclusoes)
$exclude = @('\/\.git(\/|$)', '\\\.(DS_Store|log)$', '\/Thumbs\.db$', '\/Desktop\.ini$', '\\\.(log|swp|swo|idea|vscode)', '\/node_modules\/', '\/vendor\/', '\.zip$')
$files = @(Get-ChildItem -Path $pluginDir -Recurse -File | Where-Object {
    $p = $_.FullName
    -not ($exclude | Where-Object { $p -match $_ })
} | Select-Object -ExpandProperty FullName)

Compress-Archive -Path $files -DestinationPath $zipPath -CompressionLevel Optimal

$sha  = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash
$size = (Get-Item $zipPath).Length

Write-Host "[package] OK"
Write-Host "  Arquivo: $zipName"
Write-Host "  Tamanho: $size bytes"
Write-Host "  SHA-256: $sha"
Write-Host "  Caminho: $zipPath"
