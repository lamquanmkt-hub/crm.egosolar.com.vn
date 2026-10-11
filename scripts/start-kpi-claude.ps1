param(
    [ValidateSet('Start', 'Status', 'Logs', 'Attach')]
    [string]$Action = 'Start',
    [string]$ClaudePath = '',
    [switch]$ResumeExisting,
    [string]$PromptPath = ''
)

$ErrorActionPreference = 'Stop'
$taskWorkspace = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskRunDir = Join-Path $taskWorkspace 'outputs\claude-kpi-agent'
$taskSessionFile = Join-Path $taskRunDir 'session.json'
$taskPromptFile = Join-Path $taskWorkspace 'docs\projects\kpi-automation\CLAUDE-PROMPT.md'
if ($PromptPath) {
    $taskPromptFile = [IO.Path]::GetFullPath((Join-Path $taskWorkspace $PromptPath))
    if (-not $taskPromptFile.StartsWith($taskWorkspace + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Prompt phải nằm trong workspace dự án.'
    }
}

function Get-KpiRunState($taskSaved) {
    $taskResult = $null
    $taskStarted = $false
    if (Test-Path -LiteralPath $taskSaved.stdout) {
        foreach ($taskLine in Get-Content -LiteralPath $taskSaved.stdout -TotalCount 5 -ErrorAction SilentlyContinue) {
            try {
                $taskEvent = $taskLine | ConvertFrom-Json -ErrorAction Stop
                if ($taskEvent.type -eq 'system' -and $taskEvent.subtype -eq 'init') { $taskStarted = $true }
            } catch { }
        }
        foreach ($taskLine in Get-Content -LiteralPath $taskSaved.stdout -Tail 100 -ErrorAction SilentlyContinue) {
            try {
                $taskEvent = $taskLine | ConvertFrom-Json -ErrorAction Stop
                if ($taskEvent.type -eq 'system' -and $taskEvent.subtype -eq 'init') { $taskStarted = $true }
                if ($taskEvent.type -eq 'result') { $taskResult = $taskEvent }
            } catch { }
        }
    }
    $taskProcess = Get-Process -Id $taskSaved.pid -ErrorAction SilentlyContinue
    $taskAlive = $false
    if ($taskProcess) {
        try { $taskAlive = $taskProcess.StartTime.ToUniversalTime().Ticks -eq ([datetime]$taskSaved.processStartedAt).ToUniversalTime().Ticks } catch { }
    }
    $taskState = if ($taskResult) { if ($taskResult.is_error) { 'error' } else { 'completed' } }
        elseif ($taskAlive) { if ($taskStarted) { 'running' } else { 'starting' } }
        else { 'exited-without-result' }
    [pscustomobject]@{
        sessionId = $taskSaved.sessionId
        pid = $taskSaved.pid
        status = $taskState
        resultType = if ($taskResult) { $taskResult.subtype } else { $null }
        permissionDenials = if ($taskResult) { @($taskResult.permission_denials).Count } else { $null }
    }
}

if (-not $ClaudePath) {
    $taskCliCommand = Get-Command claude -ErrorAction SilentlyContinue
    if ($taskCliCommand) {
        $ClaudePath = $taskCliCommand.Source
    } else {
        $taskExtensions = Join-Path $env:USERPROFILE '.vscode\extensions'
        $taskCandidates = Get-ChildItem -LiteralPath $taskExtensions -Directory -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -like 'anthropic.claude-code-*-win32-x64' } |
            Sort-Object LastWriteTime -Descending
        foreach ($taskCandidate in $taskCandidates) {
            $taskBinary = Join-Path $taskCandidate.FullName 'resources\native-binary\claude.exe'
            if (Test-Path -LiteralPath $taskBinary) { $ClaudePath = $taskBinary; break }
        }
    }
}
if (-not $ClaudePath -or -not (Test-Path -LiteralPath $ClaudePath)) {
    throw 'Không tìm thấy Claude Code. Truyền -ClaudePath tới CLI đã cài, hoặc mở prompt bàn giao trong Claude Code.'
}

Push-Location -LiteralPath $taskWorkspace
try {
    if ($Action -ne 'Start') {
        if (-not (Test-Path -LiteralPath $taskSessionFile)) { throw 'Chưa có mã phiên Claude trong dự án.' }
        $taskSaved = Get-Content -LiteralPath $taskSessionFile -Raw | ConvertFrom-Json
        if ($Action -eq 'Status') { Get-KpiRunState $taskSaved | ConvertTo-Json -Compress; return }
        if ($Action -eq 'Logs') {
            if (Test-Path -LiteralPath $taskSaved.stdout) { Get-Content -LiteralPath $taskSaved.stdout -Tail 30 }
            if (Test-Path -LiteralPath $taskSaved.stderr) { Get-Content -LiteralPath $taskSaved.stderr -Tail 10 }
            return
        }
        $taskState = Get-KpiRunState $taskSaved
        if ($taskState.status -in @('starting', 'running')) { throw 'Phiên đang chạy nền; dùng Status/Logs. Chỉ resume sau khi phiên kết thúc để tránh hai agent cùng sửa source.' }
        & $ClaudePath --resume $taskSaved.sessionId --agent ego-kpi-implementer
        return
    }

    if (Test-Path -LiteralPath $taskSessionFile) {
        $taskPrevious = Get-Content -LiteralPath $taskSessionFile -Raw | ConvertFrom-Json
        $taskPreviousState = Get-KpiRunState $taskPrevious
        if ($taskPreviousState.status -in @('starting', 'running')) { throw 'Đã có phiên KPI đang chạy; dùng -Action Status/Logs.' }
    }
    $taskActiveText = & $ClaudePath agents --json --cwd $taskWorkspace
    if ($LASTEXITCODE -ne 0) { throw 'Không kiểm tra được các phiên đang chạy; chưa khởi chạy thêm.' }
    $taskActive = @($taskActiveText | ConvertFrom-Json)
    $taskOtherWriters = @($taskActive | Where-Object { $_.status -notin @('idle', 'completed', 'exited', 'stopped') })
    if ($taskOtherWriters.Count -gt 0) { throw 'Có phiên Claude khác đang làm việc trong workspace; cần tránh hai phiên cùng sửa source.' }

    if (-not (Test-Path -LiteralPath $taskPromptFile -PathType Leaf)) { throw 'Không tìm thấy prompt bàn giao.' }
    if ($ResumeExisting -and -not $taskPrevious) { throw 'Chưa có phiên để tiếp tục.' }
    New-Item -ItemType Directory -Path $taskRunDir -Force | Out-Null
    $taskSessionId = if ($ResumeExisting) { $taskPrevious.sessionId } else { [guid]::NewGuid().ToString() }
    $taskRunId = [guid]::NewGuid().ToString()
    $taskLogStem = if ($ResumeExisting) { $taskSessionId + '.' + $taskRunId } else { $taskSessionId }
    $taskStdout = Join-Path $taskRunDir ($taskLogStem + '.jsonl')
    $taskStderr = Join-Path $taskRunDir ($taskLogStem + '.stderr.txt')
    if ($taskPrevious) {
        Copy-Item -LiteralPath $taskSessionFile -Destination (Join-Path $taskRunDir ($taskRunId + '.previous-session.json'))
    }
    # Supported print mode retains tool permissions. Pass the prompt as stdin,
    # so its Unicode, quotes and newlines are never interpreted as shell arguments.
    $taskArguments = @('--print', '--agent', 'ego-kpi-implementer', '--name', 'ego-kpi-automation',
        '--permission-mode', 'acceptEdits', '--permission-prompts', 'none',
        '--output-format', 'stream-json', '--verbose')
    $taskArguments += if ($ResumeExisting) { @('--resume', $taskSessionId) } else { @('--session-id', $taskSessionId) }
    $taskProcess = Start-Process -FilePath $ClaudePath -ArgumentList $taskArguments -WorkingDirectory $taskWorkspace `
        -RedirectStandardInput $taskPromptFile -RedirectStandardOutput $taskStdout -RedirectStandardError $taskStderr `
        -WindowStyle Hidden -PassThru
    $taskSaved = [pscustomobject]@{
        sessionId = $taskSessionId
        pid = $taskProcess.Id
        name = 'ego-kpi-automation'
        workspace = $taskWorkspace
        claudePath = $ClaudePath
        processStartedAt = $taskProcess.StartTime.ToUniversalTime().ToString('o')
        startedAt = (Get-Date).ToString('o')
        prompt = $taskPromptFile.Substring($taskWorkspace.Length + 1).Replace('\', '/')
        resumed = [bool]$ResumeExisting
        stdout = $taskStdout
        stderr = $taskStderr
        mode = 'print-background-acceptEdits'
    }
    $taskSaved | ConvertTo-Json | Set-Content -LiteralPath $taskSessionFile -Encoding utf8
    Get-KpiRunState $taskSaved | ConvertTo-Json -Compress
} finally {
    Pop-Location
}
