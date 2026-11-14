[CmdletBinding()]
param(
    [switch] $CheckOnly
)

$ErrorActionPreference = 'Stop'
$appUrl = 'http://127.0.0.1:8765/help'
$inspectUrl = 'http://127.0.0.1:4040/api/tunnels'

$ngrok = Get-Command ngrok -ErrorAction SilentlyContinue
if ($null -eq $ngrok) {
    Write-Error 'ngrok was not found on PATH. Install it from https://ngrok.com/download/windows.'
    exit 1
}

try {
    $appResponse = Invoke-WebRequest -Uri $appUrl -UseBasicParsing -TimeoutSec 5
    if ([int] $appResponse.StatusCode -ne 200) {
        throw "The app returned HTTP $($appResponse.StatusCode)."
    }
} catch {
    Write-Error 'The app is not responding at 127.0.0.1:8765. Start it first with: C:\xampp\php\php.exe -S 127.0.0.1:8765 -t public bin/router.php'
    exit 1
}

$activeTunnel = $null
try {
    $inspection = Invoke-RestMethod -Uri $inspectUrl -TimeoutSec 2
    $activeTunnels = @($inspection.tunnels | Where-Object {
        ([string] $_.config.addr) -match '^(http://)?(localhost|127\.0\.0\.1):8765/?$'
    } | Select-Object -First 1)
    if ($activeTunnels.Count -gt 0) {
        $activeTunnel = $activeTunnels[0]
    }
} catch {
    # The local ngrok inspector is unavailable when no tunnel is running.
}

if ($null -ne $activeTunnel) {
    Write-Host "An ngrok tunnel is already active: $($activeTunnel.public_url)"
    Write-Host 'No duplicate tunnel was started.'
    exit 0
}

if ($CheckOnly) {
    Write-Host 'The app and ngrok are ready. Run .\start-ngrok.ps1 to open the HTTPS tunnel.'
    exit 0
}

Write-Host 'Starting an ngrok tunnel to the PolishedPro app on 127.0.0.1:8765.'
Write-Host 'Keep this window open. Press Ctrl+C to stop the tunnel.'
& $ngrok.Source http 8765
exit $LASTEXITCODE
