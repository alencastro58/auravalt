param(
    [string]$BaseUrl = 'https://www.auravault.com.br'
)
$ErrorActionPreference = 'Stop'
$failures = 0

function Get-Status([string]$Url) {
    try {
        $r = Invoke-WebRequest -Uri $Url -MaximumRedirection 0 -UseBasicParsing -ErrorAction Stop
        return [int]$r.StatusCode
    } catch {
        if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }
        return -1
    }
}

function Expect([string]$Path, [int[]]$Allowed, [string]$Label) {
    $code = Get-Status ($BaseUrl + $Path)
    if ($Allowed -contains $code) { Write-Host "OK    $code  $Label" }
    else { Write-Host "FALHA $code  $Label"; $script:failures++ }
}

$http = $BaseUrl -replace '^https://', 'http://'
$code = Get-Status $http
if ($code -eq 301) { Write-Host "OK    301  HTTP redireciona para HTTPS" } else { Write-Host "FALHA $code  HTTP não redireciona"; $failures++ }

Expect '/app/bootstrap.php' @(403, 404) 'app/ bloqueado'
Expect '/app/' @(403, 404) 'listagem de app/ bloqueada'
Expect '/docs/DEPLOY_HOSTGATOR.md' @(403, 404) 'docs/ bloqueado'
Expect '/tests/smoke.php' @(403, 404) 'tests/ bloqueado'
Expect '/README.md' @(403, 404) 'README.md bloqueado'
Expect '/.htaccess' @(403, 404) '.htaccess bloqueado'
Expect '/.gitignore' @(403, 404) '.gitignore bloqueado'
Expect '/.git/config' @(403, 404) '.git bloqueado'
Expect '/.user.ini' @(403, 404) '.user.ini bloqueado'
Expect '/tests/http.php' @(403, 404) 'tests/http.php bloqueado'
Expect '/auravault_private/config.php' @(403, 404) 'pasta privada não exposta'
Expect '/install.key' @(403, 404) 'install.key não exposta'

try {
    $headers = (Invoke-WebRequest -Uri ($BaseUrl + '/?action=login') -UseBasicParsing).Headers
    foreach ($name in @('Strict-Transport-Security', 'Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options')) {
        if ($headers[$name]) { Write-Host "OK         Cabeçalho $name presente" } else { Write-Host "FALHA      Cabeçalho $name ausente"; $failures++ }
    }
    if ($headers['X-Powered-By']) { Write-Host "FALHA      X-Powered-By expõe a versão do PHP: $($headers['X-Powered-By'])"; $failures++ }
} catch {
    Write-Host 'FALHA      Não foi possível ler os cabeçalhos da página de login'; $failures++
}

$code = Get-Status ($BaseUrl + '/?action=shared&token=' + ('a' * 64))
if ($code -eq 404) { Write-Host 'OK    404  Token inexistente' } else { Write-Host "FALHA $code  Token inexistente deveria retornar 404"; $failures++ }

try { $body = (Invoke-WebRequest -Uri ($BaseUrl + '/') -UseBasicParsing).Content } catch { $body = '' }
if ($body -match 'name="db_password"' -and $body -notmatch 'name="install_key"') {
    Write-Host 'FALHA      Instalador aberto sem chave (versão antiga em produção)'; $failures++
} else {
    Write-Host 'OK         Instalador não está aberto sem chave'
}

if ($failures -gt 0) { Write-Host "$failures verificação(ões) falharam."; exit 1 }
Write-Host 'Todas as verificações passaram.'
