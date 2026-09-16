#!/usr/bin/env php
<?php
/**
 * file-stream.php
 *
 * Called as:
 *   sudo php file-stream.php <mode> <path>
 * 
 * The script reads commands from STDIN and writes results to STDOUT.
 * Each command is a line terminated by "\n".
 *
 * Supported commands (sent by the wrapper):
 *   READ <bytes>                – read <bytes> from the file
 *   WRITE <hexlen>\n<data>      – write <hexlen> bytes (hex length) followed by raw data
 *   SEEK <offset> <whence>      – fseek()
 *   TELL                        – ftell()
 *   STAT                        - fstat()
 *   EOF                         – feof()
 *   CLOSE                       – close file and exit
 *
 * Responses are plain text lines:
 *   OK <optional‑payload>
 *   ERR <message>
 */
if (!isset($argc)) {
    return;
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php file-stream.php <mode> <path>\n");
    exit(1);
}

$mode = $argv[1];
$path = $argv[2];

$fh = @fopen($path, $mode);
if ($fh === false) {
    fwrite(STDERR, "Cannot open $path with mode $mode\n");
    exit(1);
}

/* Helper to send a line */
function reply(string $line): void
{
    fwrite(STDOUT, $line . "\n");
    fflush(STDOUT);
}

/* Main command loop */
while (($line = fgets(STDIN)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($line === '') continue;

    $parts = explode(' ', $line, 2);
    $cmd = strtoupper($parts[0]);

    switch ($cmd) {
        case 'READ':
            $bytes = (int)($parts[1] ?? 0);
            $data  = fread($fh, $bytes);
            if ($data === false) {
                reply('ERR read_failed');
            } else {
                // send length in hex then raw data (binary‑safe)
                $hex = dechex(strlen($data));
                reply('OK ' . $hex);
                fwrite(STDOUT, $data);
                fflush(STDOUT);
            }
            break;

        case 'WRITE':
            // format: WRITE <hexlen>
            $hexLen = $parts[1] ?? '0';
            $len    = hexdec($hexLen);
            $data   = '';
            $remaining = $len;
            while ($remaining > 0 && ($chunk = fread(STDIN, $remaining)) !== false) {
                $data .= $chunk;
                $remaining -= strlen($chunk);
            }
            $written = fwrite($fh, $data);
            if ($written === $len) {
                reply('OK');
            } else {
                reply('ERR write_failed');
            }
            break;

        case 'SEEK':
            // SEEK <offset> <whence>
            [$off, $wh] = array_map('intval', explode(' ', $parts[1] ?? '0 0'));
            $res = fseek($fh, $off, $wh);
            reply($res === 0 ? 'OK' : 'ERR seek_failed');
            break;

        case 'TELL':
            $pos = ftell($fh);
            reply($pos !== false ? 'OK ' . $pos : 'ERR tell_failed');
            break;

        case 'STAT':
            $info = fstat($fh);
            $json = json_encode($info);
            reply('OK ' . $json);
            break;

        case 'EOF':
            reply(feof($fh) ? 'OK 1' : 'OK 0');
            break;

        case 'CLOSE':
            fclose($fh);
            reply('OK');
            exit(0);

        default:
            reply('ERR unknown_command');
    }
}
