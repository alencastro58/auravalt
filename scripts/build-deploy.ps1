param(
    [Parameter(Mandatory = $true)][string]$Tag
)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (git status --porcelain) { throw 'Há alterações não commitadas. Faça commit antes de gerar o pacote.' }
git rev-parse --verify --quiet "refs/tags/$Tag" | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Tag $Tag não existe. Crie com: git tag -a $Tag -m $Tag" }

if (Get-Command php -ErrorAction SilentlyContinue) {
    foreach ($file in (git ls-files '*.php')) {
        php -l $file | Out-Null
        if ($LASTEXITCODE -ne 0) { php -l $file; throw "Erro de sintaxe em $file." }
    }
    foreach ($test in @('tests/smoke.php', 'tests/http.php')) {
        php $test
        if ($LASTEXITCODE -ne 0) { throw "Teste falhou: $test." }
    }
} else {
    Write-Warning 'PHP não encontrado no PATH; teste de fumaça não executado.'
}

New-Item -ItemType Directory -Force -Path dist | Out-Null
$zip = "dist/AuraVault-$Tag.zip"
git archive --format=zip -o $zip $Tag
if ($LASTEXITCODE -ne 0) { throw 'git archive falhou.' }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$names = [IO.Compression.ZipFile]::OpenRead((Resolve-Path $zip)).Entries | ForEach-Object { $_.FullName }
foreach ($required in @('.htaccess', '.user.ini', 'index.php', 'app/.htaccess', 'app/bootstrap.php', 'app/auth.php')) {
    if ($names -notcontains $required) { throw "Pacote sem $required." }
}
foreach ($name in $names) {
    if ($name -match '^(tests|docs|scripts|\.github)/|config\.php$|install\.key$|\.md$') { throw "Pacote contém arquivo proibido: $name" }
}

$hash = (Get-FileHash $zip -Algorithm SHA256).Hash.ToLower()
"$hash  AuraVault-$Tag.zip" | Set-Content "$zip.sha256"
Write-Host "Pacote: $zip"
Write-Host "SHA-256: $hash"
