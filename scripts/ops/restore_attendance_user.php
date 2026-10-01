<?php
/**
 * Khôi phục công của một tài khoản đã bị xóa cứng từ bản backup mysqldump (.sql.gz).
 *
 * Dùng:  php restore_attendance_user.php <file.sql.gz> <user_id> [--apply]
 *   - Không có --apply: CHỈ XEM TRƯỚC, không ghi gì vào database.
 *   - Có --apply: tạo lại tài khoản (KHÓA, không đăng nhập được) nếu chưa có + chèn các dòng công chưa tồn tại.
 * An toàn: chỉ chèn thêm, không sửa / xóa dòng nào đang có; bọc trong transaction.
 */
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

[$script, $file, $uid] = array_pad($argv, 3, null);
$apply = in_array('--apply', $argv, true);
$uid = (int) $uid;
if (! $file || $uid <= 0 || ! is_readable($file)) {
    fwrite(STDERR, "Dung: php restore_attendance_user.php <file.sql.gz> <user_id> [--apply]\n");
    exit(1);
}

/** Tách các bộ giá trị (a,b,'c',NULL) của một câu INSERT ... VALUES ...; của mysqldump. */
function parse_tuples(string $line): Generator
{
    $p = strpos($line, ' VALUES ');
    if ($p === false) {
        return;
    }
    $i = $p + 8;
    $n = strlen($line);
    while ($i < $n) {
        if ($line[$i] !== '(') {
            $i++;
            continue;
        }
        $i++;
        $fields = [];
        while ($i < $n) {
            $c = $line[$i];
            if ($c === "'") {
                $i++;
                $buf = '';
                while ($i < $n) {
                    $ch = $line[$i];
                    if ($ch === '\\') {
                        $nx = $line[$i + 1] ?? '';
                        $buf .= match ($nx) {
                            '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", 'b' => "\x08",
                            default => $nx,
                        };
                        $i += 2;
                    } elseif ($ch === "'") {
                        if (($line[$i + 1] ?? '') === "'") {
                            $buf .= "'";
                            $i += 2;
                        } else {
                            $i++;
                            break;
                        }
                    } else {
                        $buf .= $ch;
                        $i++;
                    }
                }
                $fields[] = $buf;
            } else {
                $j = $i;
                while ($j < $n && $line[$j] !== ',' && $line[$j] !== ')') {
                    $j++;
                }
                $tok = substr($line, $i, $j - $i);
                $fields[] = $tok === 'NULL' ? null : $tok;
                $i = $j;
            }
            $c = $line[$i] ?? '';
            if ($c === ',') {
                $i++;
                continue;
            }
            if ($c === ')') {
                $i++;
                break;
            }
        }
        yield $fields;
    }
}

$attCols = Schema::getColumnListing('attendance_records');
$userCols = Schema::getColumnListing('users');

$userRow = null;
$attRows = [];
$gz = gzopen($file, 'rb');
while (($line = gzgets($gz)) !== false) {
    if (str_starts_with($line, 'INSERT INTO `attendance_records` ')) {
        foreach (parse_tuples($line) as $f) {
            if (count($f) !== count($attCols)) {
                fwrite(STDERR, 'DUNG: so cot bang cong trong backup ('.count($f).') khac hien tai ('.count($attCols).")\n");
                exit(2);
            }
            if ((int) $f[1] === $uid) {
                $attRows[] = array_combine($attCols, $f);
            }
        }
    } elseif (str_starts_with($line, 'INSERT INTO `users` ')) {
        foreach (parse_tuples($line) as $f) {
            if ((int) ($f[0] ?? 0) === $uid && count($f) === count($userCols)) {
                $userRow = array_combine($userCols, $f);
            }
        }
    }
}
gzclose($gz);

usort($attRows, fn ($a, $b) => strcmp($a['work_date'], $b['work_date']));
$exists = DB::table('users')->where('id', $uid)->exists();

echo "== File backup: $file\n";
echo '== Tai khoan ID '.$uid.' trong backup: '.($userRow ? $userRow['name'].' <'.$userRow['email'].'> phong ban #'.($userRow['department_id'] ?? '-') : 'KHONG THAY').PHP_EOL;
echo '== Tai khoan ID '.$uid.' hien tai: '.($exists ? 'CON' : 'DA MAT (se tao lai o trang thai KHOA)').PHP_EOL;
echo '== So dong cong trong backup: '.count($attRows).PHP_EOL;
foreach ($attRows as $r) {
    echo '   '.$r['work_date'].' | vao '.substr((string) $r['check_in_at'], 11, 8).' | ra '.substr((string) $r['check_out_at'], 11, 8).' | '.$r['status'].PHP_EOL;
}
if (! $attRows) {
    echo "Khong co cong de khoi phuc. Dung.\n";
    exit(0);
}
$already = DB::table('attendance_records')->where('user_id', $uid)->pluck('work_date')->map(fn ($d) => (string) $d)->all();
$toInsert = array_values(array_filter($attRows, fn ($r) => ! in_array($r['work_date'], array_map(fn ($d) => substr($d, 0, 10), $already), true)));
echo '== Dong cong chua co trong database hien tai (se chen): '.count($toInsert).PHP_EOL;

if (! $apply) {
    echo "\n[XEM TRUOC] Chua ghi gi. Dung dung so lieu thi chay lai them --apply.\n";
    exit(0);
}

DB::transaction(function () use ($uid, $exists, $userRow, $toInsert) {
    if (! $exists) {
        $name = $userRow ? preg_replace('/^\[Đã xóa\]\s*/u', '', (string) $userRow['name']) : 'Nhân viên #'.$uid;
        DB::table('users')->insert([
            'id' => $uid,
            'name' => '[Đã xóa] '.$name,
            'email' => 'deleted_user_'.$uid.'_'.time().'@deleted.local',
            'password' => Hash::make(Str::random(40)),
            'is_active' => 0,
            'department_id' => $userRow['department_id'] ?? null,
            'position_id' => $userRow['position_id'] ?? null,
            'created_at' => $userRow['created_at'] ?? now(),
            'updated_at' => now(),
        ]);
        echo "Da tao lai tai khoan ID $uid (KHOA, khong dang nhap duoc).\n";
    }
    $n = 0;
    foreach ($toInsert as $r) {
        unset($r['id']);
        $r['user_id'] = $uid;
        $n += DB::table('attendance_records')->insertOrIgnore($r);
    }
    echo "Da chen $n dong cong.\n";
});

echo '== Sau khoi phuc: ID '.$uid.' co '.DB::table('attendance_records')->where('user_id', $uid)->count().' dong cong.'.PHP_EOL;
