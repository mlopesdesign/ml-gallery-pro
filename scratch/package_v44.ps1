Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$sourceDir = "d:\ANTIGRAVITY\ml-gallery-pro\ml-gallery-pro"
$destZip = "d:\ANTIGRAVITY\ml-gallery-pro\ml-gallery-pro-v0.22.44.zip"

if (Test-Path $destZip) { Remove-Item $destZip }

$zip = [System.IO.Compression.ZipFile]::Open($destZip, [System.IO.Compression.ZipArchiveMode]::Create)

# 1. Add recursive folder entries first
$dirs = Get-ChildItem -Path $sourceDir -Recurse | Where-Object { $_.PSIsContainer } | Sort-Object FullName

# Ensure the root folder ml-gallery-pro/ is the very first entry
$zip.CreateEntry("ml-gallery-pro/")

foreach ($dir in $dirs) {
    $rel = $dir.FullName.Substring($sourceDir.Length).TrimStart("\")
    if ($rel) {
        $zipEntryPath = "ml-gallery-pro/" + $rel.Replace("\", "/") + "/"
        $zip.CreateEntry($zipEntryPath)
    }
}

# 2. Add files
$files = Get-ChildItem -Path $sourceDir -Recurse | Where-Object { ! $_.PSIsContainer }

foreach ($file in $files) {
    $rel = $file.FullName.Substring($sourceDir.Length).TrimStart("\")
    $zipEntryPath = "ml-gallery-pro/" + $rel.Replace("\", "/")
    
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $zipEntryPath, [System.IO.Compression.CompressionLevel]::Optimal)
}

$zip.Dispose()

Write-Host "SUCCESS: ZIP created at $destZip"
