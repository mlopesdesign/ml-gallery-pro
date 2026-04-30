$zipName = "d:\ANTIGRAVITY\ml-gallery-pro\ml-gallery-pro-v0.22.38.zip"
$sourceFolder = "d:\ANTIGRAVITY\ml-gallery-pro\ml-gallery-pro"
$rootName = "ml-gallery-pro"

if (Test-Path $zipName) { Remove-Item $zipName }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipName, "Create")

$files = Get-ChildItem -Path $sourceFolder -Recurse

foreach ($file in $files) {
    if ($file.Attributes -band [System.IO.FileAttributes]::Directory) {
        # Standard ZIP format doesn't require explicit directory entries if files exist,
        # but we follow the established pattern.
    } else {
        $relative = $file.FullName.Substring($sourceFolder.Length + 1)
        $archivePath = $rootName + "/" + $relative.Replace("\", "/")
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $archivePath)
    }
}

$zip.Dispose()
Write-Host "ZIP v0.22.38 created with forward slashes."
