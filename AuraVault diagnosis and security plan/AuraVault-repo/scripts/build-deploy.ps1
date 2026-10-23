param(
    [Parameter(Mandatory = $true)][string]$Tag
)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (git status --porcelain) { throw 'Há alterações não commitadas. Faça commit antes de gerar o pacote.' }
git rev-parse --verify --quiet "refs/tags/$Tag" | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Tag $Tag não existe. Crie com: git tag -a $Tag -m $Tag" }

if (Get-Command php -ErrorAction SilentlyContinue) {
    foreach ($file in @('index.php', 'app/bootstrap.php', 'app/media.php', 'app/views.php', 'tests/smoke.php')) {
        php -l $file | Out-Null
        if ($LASTEXITCODE -ne 0) { php -l $file; throw "Erro de sintaxe em $file." }
    }
    php tests/smoke.php
    if ($LASTEXITCODE -ne 0) { throw 'Teste de fumaça falhou.' }
} else {
    Write-Warning 'PHP não encontrado no PATH; teste de fumaça não executado.'
}

New-Item -ItemType Directory -Force -Path dist | Out-Null
$zip = "dist/AuraVault-$Tag.zip"
git archive --format=zip -o $zip $Tag
if ($LASTEXITCODE -ne 0) { throw 'git archive falhou.' }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$names = [IO.Compression.ZipFile]::OpenRead((Resolve-Path $zip)).Entries | ForEach-Object { $_.FullName }
foreach ($required in @('.htaccess', 'index.php', 'app/.htaccess', 'app/bootstrap.php')) {
    if ($names -notcontains $required) { throw "Pacote sem $required." }
}
foreach ($name in $names) {
    if ($name -match '^(tests|docs|scripts)/|config\.php$|install\.key$|\.md$') { throw "Pacote contém arquivo proibido: $name" }
}

$hash = (Get-FileHash $zip -Algorithm SHA256).Hash.ToLower()
"$hash  AuraVault-$Tag.zip" | Set-Content "$zip.sha256"
Write-Host "Pacote: $zip"
Write-Host "SHA-256: $hash"
