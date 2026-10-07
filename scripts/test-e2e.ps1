param(
    [string]$Database = 'mora_e2e_test',
    [string]$DatabaseHost = '127.0.0.1',
    [int]$DatabasePort = 55432,
    [string]$DatabaseUser = 'mora',
    [int]$ApiPort = 8000,
    [int]$StorePort = 3000,
    [switch]$Production
)

$ErrorActionPreference = 'Stop'
# Some Windows launchers inherit both PATH and Path; Start-Process rejects that environment.
if (@([Environment]::GetEnvironmentVariables('Process').Keys | Where-Object { $_ -ieq 'Path' }).Count -gt 1) {
    $inheritedPath = [Environment]::GetEnvironmentVariable('Path', 'Process')
    [Environment]::SetEnvironmentVariable('PATH', $null, 'Process')
    [Environment]::SetEnvironmentVariable('Path', $inheritedPath, 'Process')
}
if (!$Database.EndsWith('_test')) { throw 'Browser tests require a disposable database ending in _test.' }
$projectRoot = Split-Path -Parent $PSScriptRoot
$backendPath = Join-Path $projectRoot 'backend'
$frontendPath = Join-Path $projectRoot 'frontend'
$runtimePath = Join-Path $projectRoot '.runtime'
if (Test-Path -LiteralPath (Join-Path $backendPath 'bootstrap/cache/config.php')) {
    throw 'Clear the backend config cache before selecting the disposable browser database.'
}
foreach ($testPort in @($ApiPort, $StorePort)) {
    if (Get-NetTCPConnection -State Listen -LocalPort $testPort -ErrorAction SilentlyContinue) {
        throw "Port $testPort is in use. Stop its server or choose another port before testing."
    }
}

New-Item -ItemType Directory -Force -Path $runtimePath | Out-Null
$phpBinary = (Get-Command php).Source
$nodeBinary = (Get-Command node).Source
$npmBinary = (Get-Command npm.cmd).Source
$phpArguments = @('-d', 'xdebug.mode=off')
$phpModules = & $phpBinary -d xdebug.mode=off -m
if ($phpModules -notcontains 'intl') { $phpArguments += @('-d', 'extension=intl') }

function Wait-Ready([string]$Url) {
    $deadline = (Get-Date).AddSeconds(90)
    do {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) { return }
        } catch { }
        Start-Sleep -Milliseconds 500
    } while ((Get-Date) -lt $deadline)
    throw "Server did not become ready at $Url. See .runtime/e2e-*.log."
}

$settings = @{
    APP_ENV = 'testing'; APP_DEBUG = 'false'; APP_NAME = 'Mora'
    APP_KEY = 'base64:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa='
    APP_CONFIG_CACHE = (Join-Path $runtimePath 'e2e-config-unused.php')
    APP_URL = "http://127.0.0.1:$ApiPort"; FRONTEND_URL = "http://127.0.0.1:$StorePort"
    DB_CONNECTION = 'pgsql'; DB_HOST = $DatabaseHost; DB_PORT = [string]$DatabasePort
    DB_DATABASE = $Database; DB_USERNAME = $DatabaseUser; DB_URL = ''
    SESSION_DRIVER = 'database'; SESSION_SECURE_COOKIE = 'false'; SESSION_DOMAIN = 'null'
    SANCTUM_STATEFUL_DOMAINS = "127.0.0.1:$StorePort,127.0.0.1:$ApiPort"
    CACHE_STORE = 'database'; QUEUE_CONNECTION = 'sync'; MAIL_MAILER = 'array'
    API_INTERNAL_URL = "http://127.0.0.1:$ApiPort/api/v1"
    NEXT_PUBLIC_SITE_URL = "http://127.0.0.1:$StorePort"
    E2E_BASE_URL = "http://127.0.0.1:$StorePort"; E2E_ADMIN_URL = "http://127.0.0.1:$ApiPort"
    E2E_ADMIN_EMAIL = 'browser-admin@example.test'
    E2E_PHP_BINARY = $phpBinary; E2E_PHP_ARGUMENTS = (ConvertTo-Json -InputObject $phpArguments -Compress)
    E2E_ADMIN_PASSWORD = ('Browser-Test!' + [Guid]::NewGuid().ToString('N'))
    XDEBUG_MODE = 'off'; NEXT_TELEMETRY_DISABLED = '1'
    HOSTNAME = '127.0.0.1'; PORT = [string]$StorePort
}
# Credentials stay in process environment; DB_PASSWORD may be provided by the caller.
$previousEnvironment = @{}
$apiProcess = $null
$storeProcess = $null
$testExitCode = 1
try {
    foreach ($name in $settings.Keys) {
        $previousEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $settings[$name], 'Process')
    }
    Push-Location $backendPath
    try {
        & $phpBinary @phpArguments tests/Support/prepare-browser.php --reset
        if ($LASTEXITCODE -ne 0) { throw 'Browser fixture preparation failed.' }
    } finally { Pop-Location }

    if ($Production) {
        Push-Location $frontendPath
        try {
            & $npmBinary run build
            if ($LASTEXITCODE -ne 0) { throw 'Production storefront build failed.' }
        } finally { Pop-Location }
    }

    # Exercise real HTTP CSRF enforcement; Laravel bypasses it in the testing environment.
    $env:APP_ENV = 'local'
    $apiProcess = Start-Process -FilePath $phpBinary -ArgumentList ($phpArguments + @('-S', "127.0.0.1:$ApiPort", '-t', '.', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')) -WorkingDirectory (Join-Path $backendPath 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath 'e2e-api.out.log') -RedirectStandardError (Join-Path $runtimePath 'e2e-api.err.log')
    Wait-Ready "http://127.0.0.1:$ApiPort/api/v1/health"
    & $nodeBinary (Join-Path $projectRoot 'scripts/verify-http.mjs') "http://127.0.0.1:$ApiPort" --backend-only
    if ($LASTEXITCODE -ne 0) { throw 'Backend HTTP smoke checks failed.' }
    $storeArguments = if ($Production) { @('.next/standalone/server.js') } else { @('node_modules/next/dist/bin/next', 'dev', '--hostname', '127.0.0.1', '--port', $StorePort) }
    $storeProcess = Start-Process -FilePath $nodeBinary -ArgumentList $storeArguments -WorkingDirectory $frontendPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath 'e2e-store.out.log') -RedirectStandardError (Join-Path $runtimePath 'e2e-store.err.log')
    Wait-Ready "http://127.0.0.1:$StorePort"
    Push-Location $frontendPath
    try {
        & $npmBinary run test:e2e
        $testExitCode = $LASTEXITCODE
    } finally { Pop-Location }
} finally {
    foreach ($ownedProcess in @($storeProcess, $apiProcess)) {
        if ($ownedProcess -and !$ownedProcess.HasExited) { Stop-Process -Id $ownedProcess.Id -ErrorAction SilentlyContinue }
    }
    foreach ($name in $previousEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousEnvironment[$name], 'Process')
    }
}
exit $testExitCode
