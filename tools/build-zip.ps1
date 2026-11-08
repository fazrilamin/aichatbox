# Builds dist\ai-site-assistant-<version>.zip, ready for Plugins -> Add New -> Upload.
#   & tools\build-zip.ps1             check versions, lint, refresh the .pot, build
#   & tools\build-zip.ps1 -SkipLint   build without PHP lint (the .pot is still refreshed when PHP is found)
# Entry paths use forward slashes. Do NOT use Compress-Archive: Windows PowerShell 5.1 writes
# backslashes, and WordPress on Linux then reports "Plugin file does not exist".
param([switch]$SkipLint, [string]$Php = $env:AISA_PHP)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$src  = Join-Path $root 'ai-site-assistant'
$main = Join-Path $src 'ai-site-assistant.php'

# The header Version and AISA_VERSION must match, because assets are cache-busted by the constant.
$text   = [IO.File]::ReadAllText($main)
$header = [regex]::Match($text, '(?m)^\s*\*\s*Version:\s*(\S+)').Groups[1].Value
$const  = [regex]::Match($text, "define\(\s*'AISA_VERSION',\s*'([^']+)'").Groups[1].Value
if (-not $header -or $header -ne $const) { throw "Version mismatch: header '$header' vs AISA_VERSION '$const'." }
$stable = [regex]::Match([IO.File]::ReadAllText((Join-Path $src 'readme.txt')), '(?m)^Stable tag:\s*(\S+)').Groups[1].Value
if ($stable -ne $header) { throw "Version mismatch: header '$header' vs readme.txt Stable tag '$stable'." }

if (-not $Php) {
  $Php = @('C:\xampp\php\php.exe', (Get-Command php -ErrorAction SilentlyContinue).Source) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}
if (-not $SkipLint) {
  if (-not $Php) { throw 'No PHP found for linting. Set $env:AISA_PHP, or pass -SkipLint.' }
  $bad = Get-ChildItem $src -Recurse -Filter *.php | ForEach-Object { & $Php -l $_.FullName } | Where-Object { $_ -notmatch '^No syntax errors' }
  if ($bad) { $bad; throw 'PHP lint failed.' }
  'lint ok'
}

# Translation template: rewritten only when the strings change.
if ($Php) { & $Php (Join-Path $PSScriptRoot 'make-pot.php'); if ($LASTEXITCODE) { throw 'make-pot failed.' } }

$dist = Join-Path $root 'dist'
New-Item -ItemType Directory -Force $dist | Out-Null
$dest = Join-Path $dist "ai-site-assistant-$header.zip"
if ([IO.File]::Exists($dest)) { [IO.File]::Delete($dest) }
$zip = [IO.Compression.ZipFile]::Open($dest, 'Create')
try {
  Get-ChildItem $src -Recurse -File | ForEach-Object {
    $entry = 'ai-site-assistant/' + $_.FullName.Substring($src.Length + 1).Replace('\', '/')
    [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entry, [IO.Compression.CompressionLevel]::Optimal)
  }
} finally { $zip.Dispose() }
"built $dest ($([math]::Round((Get-Item $dest).Length / 1KB)) KB)"
