# Build Script for Cielo Product Fields Plugin
# This script packages only the essential files for production deployment.

# Fallback for $PSScriptRoot in case the script is pasted directly into the console
$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } else { $PWD.Path }

$pluginName = "cielo-product-fields"
$zipName = "$pluginName.zip"
$parentDir = Split-Path -Parent $scriptDir
$zipDestPath = Join-Path $parentDir $zipName
$tempDir = Join-Path $scriptDir "temp_$pluginName"

Write-Host "Starting production build for $pluginName..." -ForegroundColor Cyan

# 1. Clean up previous builds
if (Test-Path $zipDestPath) { 
    Remove-Item $zipDestPath -Force 
    Write-Host "Removed old zip file from parent directory." -ForegroundColor DarkGray
}
if (Test-Path $tempDir) { 
    Remove-Item $tempDir -Recurse -Force 
}

# 2. Create a temporary staging directory
$stagingDir = "$tempDir\$pluginName"
New-Item -ItemType Directory -Path $stagingDir | Out-Null

# 3. Define the strictly essential files and folders (Excluding src, node_modules, package.json)
$essentialItems = @(
    "cielo-product-fields.php",
    "includes",
    "build",
    "assets"
)

$filesFound = 0

# 4. Copy items to the staging directory
foreach ($item in $essentialItems) {
    $sourcePath = Join-Path $scriptDir $item
    if (Test-Path $sourcePath) {
        Copy-Item -Path $sourcePath -Destination $stagingDir -Recurse -Force
        Write-Host " Included: $item" -ForegroundColor Green
        $filesFound++
    } else {
        Write-Host " Warning: $item not found!" -ForegroundColor Yellow
    }
}

# 5. Safety Check: Ensure the main plugin file was actually found and copied
$mainPluginFile = Join-Path $stagingDir "cielo-product-fields.php"
if (!(Test-Path $mainPluginFile)) {
    Write-Host "=======================================================" -ForegroundColor Red
    Write-Host " ERROR: The main plugin file (cielo-product-fields.php) was not found!" -ForegroundColor Red
    Write-Host " Make sure the file exists in the current directory and is named correctly." -ForegroundColor Red
    Write-Host " The ZIP file will NOT be created." -ForegroundColor Red
    Write-Host "=======================================================" -ForegroundColor Red
    Remove-Item $tempDir -Recurse -Force
    exit
}

if ($filesFound -eq 0) {
    Write-Host " ERROR: No files were found to compress. Aborting." -ForegroundColor Red
    Remove-Item $tempDir -Recurse -Force
    exit
}

# 6. Compress the staging directory contents into the final ZIP (Fixed Nesting Issue)
Write-Host "Compressing files into the parent directory ($zipDestPath)..." -ForegroundColor Cyan
Compress-Archive -Path "$stagingDir\*" -DestinationPath $zipDestPath -Force

# 7. Clean up the temporary staging directory
Remove-Item $tempDir -Recurse -Force

Write-Host "=======================================================" -ForegroundColor Cyan
Write-Host " Build Complete: $zipName is ready for deployment in the parent directory!" -ForegroundColor Green
Write-Host "=======================================================" -ForegroundColor Cyan