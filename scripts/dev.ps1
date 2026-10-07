param(
    [int]$ApiPort = 8000,
    [int]$StorePort = 3000
)

$ErrorActionPreference = 'Stop'
# Some Windows launchers inherit both PATH and Path; Start-Process rejects that environment.
if (@([Environment]::GetEnvironmentVariables('Process').Keys | Where-Object { $_ -ieq 'Path' }).Count -gt 1) {
    $inheritedPath = [Environment]::GetEnvironmentVariable('Path', 'Process')
    [Environment]::SetEnvironmentVariable('PATH', $null, 'Process')
    [Environment]::SetEnvironmentVariable('Path', $inheritedPath, 'Process')
}
$projectRoot = Split-Path -Parent $PSScriptRoot
$backendPath = Join-Path $projectRoot 'backend'
$frontendPath = Join-Path $projectRoot 'frontend'
$runtimePath = Join-Path $projectRoot '.runtime'

if (!(Test-Path -LiteralPath (Join-Path $backendPath 'vendor/autoload.php'))) {
    throw 'Install backend dependencies with Composer first.'
}
if (!(Test-Path -LiteralPath (Join-Path $backendPath '.env'))) {
    throw 'Configure backend/.env and run migrations first.'
}
if (!(Test-Path -LiteralPath (Join-Path $frontendPath 'node_modules/next/dist/bin/next'))) {
    throw 'Run npm ci in frontend first.'
}

foreach ($devPort in @($ApiPort, $StorePort)) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $devPort -ErrorAction SilentlyContinue
    if ($listener) { throw "Port $devPort is already in use. Existing services have been left running." }
}

New-Item -ItemType Directory -Force -Path $runtimePath | Out-Null
$phpBinary = (Get-Command php).Source
$nodeBinary = (Get-Command node).Source
$env:XDEBUG_MODE = 'off'
$phpModules = & $phpBinary -m
$phpArguments = @('-d', 'xdebug.mode=off')
if ($phpModules -notcontains 'intl') { $phpArguments += @('-d', 'extension=intl') }
$phpArguments += @('-S', "127.0.0.1:$ApiPort", '-t', '.', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')

$apiProcess = Start-Process -FilePath $phpBinary -ArgumentList $phpArguments -WorkingDirectory (Join-Path $backendPath 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath 'api.out.log') -RedirectStandardError (Join-Path $runtimePath 'api.err.log')
$env:API_INTERNAL_URL = "http://127.0.0.1:$ApiPort/api/v1"
$env:NEXT_PUBLIC_SITE_URL = "http://localhost:$StorePort"
$storeProcess = Start-Process -FilePath $nodeBinary -ArgumentList @('node_modules/next/dist/bin/next', 'dev', '--hostname', '127.0.0.1', '--port', $StorePort) -WorkingDirectory $frontendPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath 'store.out.log') -RedirectStandardError (Join-Path $runtimePath 'store.err.log')

Write-Output "Storefront: http://localhost:$StorePort"
Write-Output "Admin: http://localhost:$ApiPort/admin"
Write-Output "Started API PID $($apiProcess.Id), storefront PID $($storeProcess.Id). Logs: $runtimePath"
Write-Output "To stop these servers: Stop-Process -Id $($apiProcess.Id),$($storeProcess.Id)"
