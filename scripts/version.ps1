#Requires -Version 5.1
<#
.SYNOPSIS
    أداة إدارة إصدارات CND Manager محلياً: حفظ إصدار، عرض الإصدارات، واستعادة إصدار سابق.

.DESCRIPTION
    كل إصدار = وسم Git برسالة + نسخة من قاعدة البيانات في مجلد .versions
    الاستعادة تعيد الكود وقاعدة البيانات معاً، لأن الترحيلات تغيّر بنية الجداول،
    فإعادة الكود وحده تترك القاعدة ببنية لا يفهمها.

    التاريخ يمضي إلى الأمام دائماً: الاستعادة تُسجَّل ككوميت جديد فوق الفرع،
    ولا تُحذف كوميتات ولا تُزاح المؤشرات، فتبقى كل الإصدارات قابلة للرجوع إليها.

.EXAMPLE
    .\scripts\version.ps1 status
    .\scripts\version.ps1 save 1.1.0 -Message "إضافة وحدة الآليات" -CommitAll
    .\scripts\version.ps1 list
    .\scripts\version.ps1 restore 1.0.0
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('status', 'list', 'save', 'restore', 'snapshot', 'help')]
    [string]$Command = 'help',

    [Parameter(Position = 1)]
    [string]$Version,

    # رسالة الإصدار (تُكتب في الوسم وفي meta.json).
    [string]$Message,

    # save: يضمّ كل التعديلات غير المحفوظة في كوميت واحد قبل الوسم.
    [switch]$CommitAll,

    # save/snapshot: تخطّي نسخ قاعدة البيانات.
    [switch]$NoDb,

    # restore: إعادة الكود فقط دون قاعدة البيانات.
    [switch]$CodeOnly,

    # restore: إعادة قاعدة البيانات فقط دون لمس الكود.
    [switch]$DbOnly,

    # restore: فتح الإصدار في فرع للفحص بدل تطبيقه على الفرع الحالي.
    [switch]$AsBranch,

    # تخطّي أسئلة التأكيد (للتشغيل الآلي).
    [switch]$Yes
)

$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

$RepoRoot    = Split-Path -Parent $PSScriptRoot
$VersionsDir = Join-Path $RepoRoot '.versions'
$VersionFile = Join-Path $RepoRoot 'VERSION'
$EnvFile     = Join-Path $RepoRoot 'BackEnd\.env'

# ---------------------------------------------------------------- الإخراج

function Write-Step { param([string]$Text) Write-Host "==> $Text" -ForegroundColor Cyan }
function Write-Ok   { param([string]$Text) Write-Host "    $Text" -ForegroundColor Green }
function Write-Note { param([string]$Text) Write-Host "    $Text" -ForegroundColor DarkGray }
function Write-Warn { param([string]$Text) Write-Host "    تنبيه: $Text" -ForegroundColor Yellow }

function Confirm-Action {
    param([string]$Question)

    if ($Yes) { return $true }

    $answer = Read-Host "$Question [y/N]"
    return ($answer -eq 'y' -or $answer -eq 'Y' -or $answer -eq 'نعم')
}

# ---------------------------------------------------------------- Git

function Invoke-Git {
    param([string[]]$Arguments, [switch]$AllowFailure)

    Push-Location $RepoRoot
    try {
        $output = & git @Arguments
        $code = $LASTEXITCODE
    } finally {
        Pop-Location
    }

    if ($code -ne 0 -and -not $AllowFailure) {
        throw ("فشل الأمر: git " + ($Arguments -join ' ') + [Environment]::NewLine + ($output -join [Environment]::NewLine))
    }

    $global:LASTEXITCODE = $code
    return $output
}

function Get-CurrentBranch { return (Invoke-Git @('rev-parse', '--abbrev-ref', 'HEAD')) }

function Get-DirtyFiles {
    $lines = Invoke-Git @('status', '--porcelain')
    if (-not $lines) { return @() }

    return @($lines | Where-Object { $_ -and $_.Trim().Length -gt 0 })
}

function Test-TagExists {
    param([string]$Tag)

    Invoke-Git @('rev-parse', '-q', '--verify', "refs/tags/$Tag") -AllowFailure | Out-Null
    return ($LASTEXITCODE -eq 0)
}

function Get-LatestTag {
    $rows = Invoke-Git @('for-each-ref', '--count=1', '--sort=-creatordate', '--format=%(refname:short)', 'refs/tags/v*')
    if (-not $rows) { return $null }

    return ([string](@($rows)[0])).Trim()
}

function Resolve-VersionName {
    param([string]$Raw)

    if ([string]::IsNullOrWhiteSpace($Raw)) { throw 'رقم الإصدار مطلوب، مثل: 1.2.0' }

    $number = $Raw.Trim()
    if ($number.StartsWith('v') -or $number.StartsWith('V')) { $number = $number.Substring(1) }

    if ($number -notmatch '^\d+\.\d+\.\d+$') {
        throw "رقم الإصدار غير صالح: '$Raw'. الصيغة المطلوبة major.minor.patch مثل 1.2.0"
    }

    return [pscustomobject]@{ Number = $number; Tag = "v$number" }
}

function Get-CurrentVersion {
    if (Test-Path $VersionFile) {
        $value = (Get-Content $VersionFile -Raw -Encoding UTF8).Trim()
        if ($value) { return $value }
    }

    $latest = Get-LatestTag
    if ($latest) { return ([string]$latest).TrimStart('v') }

    return '0.0.0'
}

function Set-VersionFile {
    param([string]$Number)

    # بلا BOM وبسطر ختامي واحد، كي تقرأه أي أداة أخرى دون مفاجآت.
    [System.IO.File]::WriteAllText($VersionFile, $Number + "`n", (New-Object System.Text.UTF8Encoding($false)))
}

# ---------------------------------------------------------------- PostgreSQL

function Get-EnvValue {
    param([string]$Key, [string]$Default = '')

    if (-not (Test-Path $EnvFile)) { return $Default }

    foreach ($line in (Get-Content $EnvFile -Encoding UTF8)) {
        $trimmed = $line.Trim()
        if ($trimmed.StartsWith('#')) { continue }

        if ($trimmed -match ('^' + [regex]::Escape($Key) + '\s*=\s*(.*)$')) {
            $value = $Matches[1].Trim()

            if ($value.Length -ge 2) {
                $first = $value.Substring(0, 1)
                $last  = $value.Substring($value.Length - 1, 1)
                if (($first -eq '"' -and $last -eq '"') -or ($first -eq "'" -and $last -eq "'")) {
                    $value = $value.Substring(1, $value.Length - 2)
                }
            }

            return $value
        }
    }

    return $Default
}

function Get-PgTool {
    param([string]$Name)

    $found = Get-Command $Name -ErrorAction SilentlyContinue
    if ($found) { return $found.Source }

    foreach ($root in @('C:\Program Files\PostgreSQL', 'C:\Program Files (x86)\PostgreSQL')) {
        if (-not (Test-Path $root)) { continue }

        $versions = Get-ChildItem $root -Directory | Sort-Object { [int]($_.Name -replace '\D', '') } -Descending
        foreach ($directory in $versions) {
            $candidate = Join-Path $directory.FullName ('bin\' + $Name + '.exe')
            if (Test-Path $candidate) { return $candidate }
        }
    }

    return $null
}

function Get-DbSettings {
    return [pscustomobject]@{
        Server   = (Get-EnvValue 'DB_HOST' '127.0.0.1')
        Port     = (Get-EnvValue 'DB_PORT' '5432')
        Name     = (Get-EnvValue 'DB_DATABASE' 'cnd_manager')
        User     = (Get-EnvValue 'DB_USERNAME' 'postgres')
        Password = (Get-EnvValue 'DB_PASSWORD' '')
    }
}

function Backup-Database {
    param([string]$Destination)

    $pgDump = Get-PgTool 'pg_dump'
    if (-not $pgDump) {
        Write-Warn 'لم يُعثر على pg_dump — تُرك الإصدار بلا نسخة من قاعدة البيانات.'
        return $false
    }

    $db = Get-DbSettings
    $previousPassword = $env:PGPASSWORD
    $env:PGPASSWORD = $db.Password

    try {
        & $pgDump "--host=$($db.Server)" "--port=$($db.Port)" "--username=$($db.User)" "--dbname=$($db.Name)" `
            '--format=custom' '--compress=6' '--no-owner' '--no-privileges' "--file=$Destination"
        $code = $LASTEXITCODE
    } finally {
        $env:PGPASSWORD = $previousPassword
    }

    if ($code -ne 0) {
        Write-Warn "pg_dump انتهى بالرمز $code — راجع بيانات الاتصال في BackEnd\.env"
        return $false
    }

    $size = [math]::Round((Get-Item $Destination).Length / 1MB, 2)
    Write-Ok "نسخة قاعدة البيانات: $Destination ($size ميغابايت)"
    return $true
}

function Restore-Database {
    param([string]$DumpPath)

    if (-not (Test-Path $DumpPath)) { throw "ملف النسخة غير موجود: $DumpPath" }

    $pgRestore = Get-PgTool 'pg_restore'
    if (-not $pgRestore) {
        throw 'لم يُعثر على pg_restore — ثبّت أدوات عميل PostgreSQL، أو استعد الكود وحده بـ -CodeOnly'
    }

    $db = Get-DbSettings
    $previousPassword = $env:PGPASSWORD
    $env:PGPASSWORD = $db.Password

    try {
        # ‎--clean --if-exists يُسقط الكائنات الحالية قبل إعادة بنائها من النسخة.
        & $pgRestore "--host=$($db.Server)" "--port=$($db.Port)" "--username=$($db.User)" "--dbname=$($db.Name)" `
            '--clean' '--if-exists' '--no-owner' '--no-privileges' $DumpPath
        $code = $LASTEXITCODE
    } finally {
        $env:PGPASSWORD = $previousPassword
    }

    if ($code -ne 0) {
        # pg_restore يعيد رمزاً غير صفري على التحذيرات أيضاً (إسقاط كائنات غير موجودة أصلاً).
        Write-Warn "pg_restore انتهى بالرمز $code — غالباً تحذيرات إسقاط لكائنات غير موجودة. تحقّق من البيانات قبل المتابعة."
    } else {
        Write-Ok "أُعيدت قاعدة البيانات '$($db.Name)' من $DumpPath"
    }
}

# ---------------------------------------------------------------- البيانات الوصفية

function Write-Meta {
    param([string]$Directory, [hashtable]$Data)

    $path = Join-Path $Directory 'meta.json'
    $json = ($Data | ConvertTo-Json -Depth 5)
    [System.IO.File]::WriteAllText($path, $json, (New-Object System.Text.UTF8Encoding($false)))
}

function New-BackupDir {
    param([string]$Name)

    $path = Join-Path $VersionsDir $Name
    if (-not (Test-Path $path)) { New-Item -ItemType Directory -Path $path -Force | Out-Null }

    return $path
}

# ---------------------------------------------------------------- الأوامر

function Invoke-Status {
    $branch  = Get-CurrentBranch
    $head    = Invoke-Git @('log', '-1', '--format=%h %s')
    $dirty   = Get-DirtyFiles
    $current = Get-CurrentVersion
    $db      = Get-DbSettings

    Write-Host ''
    Write-Host '  حالة الإصدار' -ForegroundColor White
    Write-Host '  ------------' -ForegroundColor DarkGray
    Write-Host "  الإصدار الحالي : v$current"
    Write-Host "  الفرع          : $branch"
    Write-Host "  آخر كوميت      : $head"
    Write-Host "  قاعدة البيانات : $($db.Name) على $($db.Server):$($db.Port)"

    $lastTag = Get-LatestTag
    if ($lastTag) {
        $ahead = Invoke-Git @('rev-list', '--count', "$lastTag..HEAD")
        Write-Host "  آخر وسم        : $lastTag (بعده $ahead كوميت غير موسوم)"
    } else {
        Write-Host '  آخر وسم        : لا يوجد'
    }

    if ($dirty.Count -gt 0) {
        Write-Host "  تعديلات معلّقة : $($dirty.Count) ملف" -ForegroundColor Yellow
        foreach ($line in ($dirty | Select-Object -First 10)) { Write-Note $line }
        if ($dirty.Count -gt 10) { Write-Note "... و $($dirty.Count - 10) غيرها" }
    } else {
        Write-Host '  تعديلات معلّقة : لا شيء — الشجرة نظيفة' -ForegroundColor Green
    }

    $dumpPath = Join-Path (Join-Path $VersionsDir "v$current") 'db.dump'
    if (Test-Path $dumpPath) {
        $size = [math]::Round((Get-Item $dumpPath).Length / 1MB, 2)
        Write-Host "  نسخة القاعدة   : موجودة ($size ميغابايت)" -ForegroundColor Green
    } else {
        Write-Host '  نسخة القاعدة   : غير موجودة لهذا الإصدار' -ForegroundColor Yellow
    }

    Write-Host ''
}

function Invoke-List {
    $format = '%(refname:short)|%(creatordate:format:%Y-%m-%d %H:%M)|%(objectname:short)|%(contents:subject)'
    $rows = Invoke-Git @('for-each-ref', '--sort=-creatordate', "--format=$format", 'refs/tags/v*')

    Write-Host ''
    if (-not $rows) {
        Write-Host '  لا توجد إصدارات موسومة بعد.' -ForegroundColor Yellow
        Write-Host '  أنشئ أولها بـ: .\scripts\version.ps1 save 1.0.0 -Message "الإصدار الأول"' -ForegroundColor DarkGray
        Write-Host ''
        return
    }

    $current = 'v' + (Get-CurrentVersion)
    Write-Host '  الإصدارات المحفوظة' -ForegroundColor White
    Write-Host '  ------------------' -ForegroundColor DarkGray

    foreach ($row in $rows) {
        $parts = $row -split '\|', 4
        $tag = $parts[0]

        $dumpPath = Join-Path (Join-Path $VersionsDir $tag) 'db.dump'
        if (Test-Path $dumpPath) { $dbMark = '[قاعدة: نعم]' } else { $dbMark = '[قاعدة: لا ]' }

        if ($tag -eq $current) { $marker = '>' } else { $marker = ' ' }
        if ($tag -eq $current) { $color = 'Green' } else { $color = 'Gray' }

        Write-Host ("  {0} {1,-10} {2}  {3}  {4}" -f $marker, $tag, $parts[1], $dbMark, $parts[3]) -ForegroundColor $color
    }

    Write-Host ''
    Write-Host '  > الإصدار المطبَّق حالياً | للاستعادة: .\scripts\version.ps1 restore <الرقم>' -ForegroundColor DarkGray
    Write-Host ''
}

function Invoke-Save {
    if (-not $Version) {
        throw 'حدّد رقم الإصدار، مثل: .\scripts\version.ps1 save 1.1.0 -Message "وصف الإصدار"'
    }

    $target = Resolve-VersionName $Version
    if (Test-TagExists $target.Tag) {
        throw "الوسم $($target.Tag) موجود مسبقاً. اختر رقماً أعلى، أو احذف الوسم يدوياً بـ: git tag -d $($target.Tag)"
    }

    $text = $Message
    if ([string]::IsNullOrWhiteSpace($text)) { $text = "الإصدار $($target.Number)" }

    $dirty = Get-DirtyFiles
    if ($dirty.Count -gt 0) {
        if (-not $CommitAll) {
            Write-Host ''
            foreach ($line in $dirty) { Write-Note $line }
            throw "هناك $($dirty.Count) ملف غير محفوظ. احفظها بكوميت أولاً، أو أضف -CommitAll لضمّها في كوميت الإصدار."
        }

        Write-Step "ضمّ $($dirty.Count) ملف في كوميت الإصدار"
        Invoke-Git @('add', '-A') | Out-Null
        Invoke-Git @('commit', '-m', $text) | Out-Null
        Write-Ok 'تم الكوميت'
    }

    if ((Get-CurrentVersion) -ne $target.Number) {
        Write-Step "تحديث ملف VERSION إلى $($target.Number)"
        Set-VersionFile $target.Number
        Invoke-Git @('add', '--', 'VERSION') | Out-Null
        Invoke-Git @('commit', '-m', "chore(release): $($target.Tag)") | Out-Null
        Write-Ok 'تم'
    }

    Write-Step "وسم الإصدار $($target.Tag)"
    Invoke-Git @('tag', '-a', $target.Tag, '-m', $text) | Out-Null
    $sha = [string](Invoke-Git @('rev-parse', 'HEAD'))
    Write-Ok "$($target.Tag) -> $($sha.Substring(0, 8))"

    $directory = New-BackupDir $target.Tag
    $hasDump = $false

    if (-not $NoDb) {
        Write-Step 'أخذ نسخة من قاعدة البيانات'
        $hasDump = Backup-Database (Join-Path $directory 'db.dump')
    } else {
        Write-Note 'تُخطّيت نسخة قاعدة البيانات (-NoDb)'
    }

    Write-Meta $directory @{
        version    = $target.Number
        tag        = $target.Tag
        message    = $text
        commit     = $sha
        branch     = [string](Get-CurrentBranch)
        created_at = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        database   = (Get-DbSettings).Name
        has_dump   = $hasDump
        machine    = $env:COMPUTERNAME
    }

    Write-Host ''
    Write-Ok "حُفظ الإصدار $($target.Tag)"
    Write-Note "للعودة إليه لاحقاً: .\scripts\version.ps1 restore $($target.Number)"
    Write-Note 'وثّق التغييرات في CHANGELOG.md قبل تسليم الإصدار.'
    Write-Host ''
}

function Invoke-Snapshot {
    $stamp = (Get-Date).ToString('yyyyMMdd-HHmmss')
    $directory = New-BackupDir "_snapshots\$stamp"

    Write-Step "لقطة احتياطية سريعة: $stamp"

    $hasDump = $false
    if (-not $NoDb) { $hasDump = Backup-Database (Join-Path $directory 'db.dump') }

    Write-Meta $directory @{
        kind       = 'snapshot'
        commit     = [string](Invoke-Git @('rev-parse', 'HEAD'))
        branch     = [string](Get-CurrentBranch)
        version    = (Get-CurrentVersion)
        created_at = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        database   = (Get-DbSettings).Name
        has_dump   = $hasDump
        dirty      = (Get-DirtyFiles).Count
    }

    Write-Ok "اللقطة في: $directory"
    Write-Host ''
}

function Invoke-Restore {
    if (-not $Version) { throw 'حدّد الإصدار المطلوب، مثل: .\scripts\version.ps1 restore 1.0.0' }

    $target = Resolve-VersionName $Version
    if (-not (Test-TagExists $target.Tag)) {
        throw "لا يوجد إصدار بالوسم $($target.Tag). اعرض المتاح بـ: .\scripts\version.ps1 list"
    }

    $dumpPath = Join-Path (Join-Path $VersionsDir $target.Tag) 'db.dump'
    $restoreDb = (-not $CodeOnly)

    if ($restoreDb -and -not (Test-Path $dumpPath)) {
        Write-Warn "لا توجد نسخة قاعدة بيانات للإصدار $($target.Tag) — سيُستعاد الكود وحده."
        Write-Warn 'إن نُفِّذت ترحيلات بعد هذا الإصدار فستبقى آثارها في القاعدة.'
        $restoreDb = $false
    }

    $stamp   = (Get-Date).ToString('yyyyMMdd-HHmmss')
    $headSha = [string](Invoke-Git @('rev-parse', 'HEAD'))
    $branch  = [string](Get-CurrentBranch)

    Write-Host ''
    Write-Host "  استعادة الإصدار $($target.Tag)" -ForegroundColor White
    Write-Host "  الحالة الحالية : $branch @ $($headSha.Substring(0, 8))"

    if ($DbOnly) {
        Write-Host '  النطاق         : قاعدة البيانات فقط'
    } elseif ($restoreDb) {
        Write-Host '  النطاق         : الكود + قاعدة البيانات'
    } else {
        Write-Host '  النطاق         : الكود فقط'
    }

    Write-Host ''
    if (-not (Confirm-Action 'متابعة الاستعادة؟')) {
        Write-Note 'أُلغيت العملية.'
        return
    }

    # (1) شبكة الأمان: وسم الحالة الحالية ونسخ قاعدتها قبل أي تغيير.
    Write-Step 'حفظ الحالة الحالية قبل الاستعادة'
    $safetyTag = "safety/pre-restore-$stamp"
    Invoke-Git @('tag', '-a', $safetyTag, '-m', "الحالة قبل الاستعادة إلى $($target.Tag)") | Out-Null
    Write-Ok "وسم الأمان: $safetyTag"

    $safetyDir = New-BackupDir "_pre-restore\$stamp"
    $safetyDump = $false
    if (-not $CodeOnly -and -not $NoDb) { $safetyDump = Backup-Database (Join-Path $safetyDir 'db.dump') }

    Write-Meta $safetyDir @{
        kind        = 'pre-restore'
        restored_to = $target.Tag
        commit      = $headSha
        branch      = $branch
        safety_tag  = $safetyTag
        version     = (Get-CurrentVersion)
        created_at  = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        has_dump    = $safetyDump
    }

    # (2) الكود
    if (-not $DbOnly) {
        $dirty = Get-DirtyFiles
        if ($dirty.Count -gt 0) {
            Write-Step "حفظ $($dirty.Count) ملف معلّق في stash"
            Invoke-Git @('stash', 'push', '-u', '-m', "pre-restore-$stamp") | Out-Null
            Write-Ok 'لاسترجاعها لاحقاً: git stash list ثم git stash pop'
        }

        if ($AsBranch) {
            $newBranch = "restore/$($target.Tag)-$stamp"
            Write-Step "فتح الإصدار في فرع للفحص: $newBranch"
            Invoke-Git @('switch', '-c', $newBranch, $target.Tag) | Out-Null
            Write-Ok "أنت الآن على $newBranch — الفرع $branch لم يُمسّ."
        } else {
            Write-Step "إعادة ملفات الكود إلى محتوى $($target.Tag)"

            # الملفات التي أُضيفت بعد هذا الإصدار تُحذف، والباقي يعود إلى محتواه وقتها.
            $added = Invoke-Git @('diff', '--name-only', '--diff-filter=A', "$($target.Tag)..HEAD")
            Invoke-Git @('restore', '--source', $target.Tag, '--staged', '--worktree', '--', '.') | Out-Null

            if ($added) {
                $files = @($added | Where-Object { $_ -and $_.Trim().Length -gt 0 })
                if ($files.Count -gt 0) {
                    Invoke-Git (@('rm', '-f', '-q', '--ignore-unmatch', '--') + $files) | Out-Null
                    Write-Ok "حُذف $($files.Count) ملف أُضيف بعد الإصدار"
                }
            }

            Set-VersionFile $target.Number
            Invoke-Git @('add', '-A') | Out-Null

            $staged = Invoke-Git @('diff', '--cached', '--name-only')
            if ($staged) {
                Invoke-Git @('commit', '-m', "rollback: العودة إلى الإصدار $($target.Tag)") | Out-Null
                $newSha = [string](Invoke-Git @('rev-parse', 'HEAD'))
                Write-Ok "كوميت الاستعادة: $($newSha.Substring(0, 8)) على $branch"
            } else {
                Write-Note 'الكود مطابق للإصدار أصلاً — لا كوميت جديد.'
            }
        }
    }

    # (3) قاعدة البيانات
    if ($restoreDb) {
        Write-Step 'إعادة قاعدة البيانات'
        Restore-Database $dumpPath
    }

    Write-Host ''
    Write-Ok "اكتملت الاستعادة إلى $($target.Tag)"
    Write-Note "للتراجع عن الاستعادة نفسها: git checkout $safetyTag (أو استعد النسخة من .versions\_pre-restore\$stamp)"
    Write-Note 'ثم: composer install و npm install إن اختلفت الاعتماديات بين الإصدارين.'
    Write-Host ''
}

function Invoke-Help {
    Write-Host ''
    Write-Host '  إدارة إصدارات CND Manager' -ForegroundColor White
    Write-Host '  -------------------------' -ForegroundColor DarkGray
    Write-Host '  .\scripts\version.ps1 status                     الحالة الحالية'
    Write-Host '  .\scripts\version.ps1 list                       كل الإصدارات المحفوظة'
    Write-Host '  .\scripts\version.ps1 save <رقم> -Message "وصف"   حفظ إصدار جديد (وسم + نسخة قاعدة)'
    Write-Host '  .\scripts\version.ps1 restore <رقم>              العودة إلى إصدار سابق'
    Write-Host '  .\scripts\version.ps1 snapshot                   لقطة قاعدة بيانات سريعة بلا وسم'
    Write-Host ''
    Write-Host '  الخيارات' -ForegroundColor White
    Write-Host '  -CommitAll    (save)    يضمّ التعديلات غير المحفوظة في كوميت الإصدار'
    Write-Host '  -NoDb         (save)    بلا نسخة قاعدة بيانات'
    Write-Host '  -CodeOnly     (restore) إعادة الكود دون القاعدة'
    Write-Host '  -DbOnly       (restore) إعادة القاعدة دون الكود'
    Write-Host '  -AsBranch     (restore) فتح الإصدار في فرع للفحص بدل تطبيقه'
    Write-Host '  -Yes                    بلا أسئلة تأكيد'
    Write-Host ''
    Write-Host '  الدليل الكامل: docs\_markdown\07-guides\04-إدارة-الإصدارات.md' -ForegroundColor DarkGray
    Write-Host ''
}

# ---------------------------------------------------------------- التشغيل

Invoke-Git @('rev-parse', '--git-dir') -AllowFailure | Out-Null
if ($LASTEXITCODE -ne 0) { throw "المجلد ليس مستودع Git: $RepoRoot" }

if (-not (Test-Path $VersionsDir)) { New-Item -ItemType Directory -Path $VersionsDir -Force | Out-Null }

switch ($Command) {
    'status'   { Invoke-Status }
    'list'     { Invoke-List }
    'save'     { Invoke-Save }
    'restore'  { Invoke-Restore }
    'snapshot' { Invoke-Snapshot }
    default    { Invoke-Help }
}
