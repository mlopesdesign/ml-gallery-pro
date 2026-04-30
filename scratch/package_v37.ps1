$zipName = "d:\ANTIGRAVITY\ml-gallery-pro\ml-gallery-pro-v0.22.37.zip"
$sourceFolder = "d:\ANTIGRAVITY\ml-gallery-pro\ml-gallery-pro"
$rootName = "ml-gallery-pro"

if (Test-Path $zipName) { Remove-Item $zipName }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($zipName, "Create")

$files = Get-ChildItem -Path $sourceFolder -Recurse

foreach ($file in $files) {
    if ($file.Attributes -band [System.IO.FileAttributes]::Directory) {
        $archivePath = $rootName + "/" + (Resolve-Path $file.FullName -Relative).Replace("\", "/") + "/"
        # For folders, we just create the entry
        # But ZipFileExtensions doesn't have a direct folder creation that's easy
        # So we skip explicit folders and let file creation handle it, 
        # or we add a dummy entry if needed. 
        # Actually, most zips just need the file paths.
    } else {
        $relative = $file.FullName.Substring($sourceFolder.Length + 1)
        $archivePath = $rootName + "/" + $relative.Replace("\", "/")
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $archivePath)
    }
}

$zip.Dispose()
Write-Host "ZIP created with forward slashes."
