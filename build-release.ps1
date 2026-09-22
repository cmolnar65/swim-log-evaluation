param(
    [string]$Output = "../chriss-swim-training-progress-evaluation.zip"
)

$ErrorActionPreference = "Stop"

$PluginSlug = "chriss-swim-training-progress-evaluation"
$RepoRoot = (Resolve-Path $PSScriptRoot).Path
$TempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("swimlog-release-" + [guid]::NewGuid().ToString("N"))
$StageDir = Join-Path $TempRoot $PluginSlug
$OutputPath = [System.IO.Path]::GetFullPath((Join-Path $RepoRoot $Output))

# Development/repository files that must not ship in the WordPress release package.
$ExcludeTopLevel = @(
    ".git",
    ".github",
    "tests",
    "phpunit.xml.dist",
    "composer.json",
    "composer.lock",
    "build-release.ps1",
    "build-release.sh",
    "PLUGIN-CHECK.md"
)

try {
    New-Item -ItemType Directory -Path $StageDir -Force | Out-Null

    Get-ChildItem -LiteralPath $RepoRoot -Force | ForEach-Object {
        if ($ExcludeTopLevel -contains $_.Name) {
            return
        }

        Copy-Item -LiteralPath $_.FullName -Destination $StageDir -Recurse -Force
    }

    if (Test-Path -LiteralPath $OutputPath) {
        Remove-Item -LiteralPath $OutputPath -Force
    }

    $OutputDirectory = Split-Path -Parent $OutputPath
    if ($OutputDirectory -and -not (Test-Path -LiteralPath $OutputDirectory)) {
        New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    }

    Compress-Archive -Path $StageDir -DestinationPath $OutputPath -CompressionLevel Optimal

    Write-Host "Release package created:"
    Write-Host "  $OutputPath"
    Write-Host ""
    Write-Host "Excluded development files:"
    $ExcludeTopLevel | ForEach-Object { Write-Host "  $_" }
}
finally {
    if (Test-Path -LiteralPath $TempRoot) {
        Remove-Item -LiteralPath $TempRoot -Recurse -Force
    }
}
