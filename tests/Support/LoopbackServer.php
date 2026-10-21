<?php
/**
 * PHP implementation of FedEx API's
 * @author Mário Pereira <bin.to.hex@gmail.com>
 * @package mariopereira/fedex
 * @license https://www.gnu.org/licenses/gpl-3.0.en.htmlURL GPL-3.0-or-later
 */

namespace Mariopereira\Fedex\Tests\Support;

/**
 * Starts and stops the throwaway server of the loopback tests (loopback_server.php) as a separate PHP process, and reads
 * what it received. It only ever listens on 127.0.0.1, and it is stopped at the end of the tests, or when PHP exits
 */
final class LoopbackServer
{
    /**
     * The process of the server
     * @var resource|null
     */
    private $process;

    /**
     * The pipes of the process
     * @var resource[]
     */
    private $pipes = [];

    /**
     * The port of the server
     * @var int
     */
    private $port;

    /**
     * The file where the server writes what it receives
     * @var string
     */
    private $log;

    /**
     * Has the server been stopped?
     * @var bool
     */
    private $stopped = false;

    /**
     * Use start() to make a server
     * @param resource $process The process
     * @param resource[] $pipes The pipes of the process
     * @param int $port The port
     * @param string $log The log file
     */
    private function __construct($process, array $pipes, int $port, string $log)
    {
        $this->process = $process;
        $this->pipes = $pipes;
        $this->port = $port;
        $this->log = $log;
    }

    /**
     * Start a server on a free port of the loopback interface
     * @return LoopbackServer
     * @throws \RuntimeException When the server cannot be started
     */
    public static function start(): self
    {
        $port = self::freePort();
        $log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fedex_loopback_' . $port . '.log';
        @unlink($log);

        // the array form of the command: no shell and no quoting problems
        $process = proc_open([PHP_BINARY, __DIR__ . '/loopback_server.php', (string) $port, $log], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('cannot start the loopback server');
        }

        $server = new self($process, $pipes, $port, $log);
        $up = false;

        for ($i = 0; $i < 100; $i++) {
            if (self::portOpen($port)) {
                $up = true;
                break;
            }

            usleep(100000);
        }

        if (!$up) {
            stream_set_blocking($pipes[2], false);
            $why = stream_get_contents($pipes[2]);
            $server->stop();

            throw new \RuntimeException('the loopback server did not start: ' . trim((string) $why));
        }

        // the connection that was used to see if it is up was logged: forget it
        usleep(150000);
        $server->forget();
        register_shutdown_function(function () use ($server) {
            $server->stop();
        });

        return $server;
    }

    /**
     * A port of the loopback interface that nothing listens on
     * @return int
     */
    public static function freePort(): int
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        return $port;
    }

    /**
     * Does something listen on the port?
     * @param int $port The port
     * @return bool
     */
    public static function portOpen(int $port): bool
    {
        $connection = @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 0.3);

        if ($connection) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * The port of the server
     * @return int
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * The url of a path on the server
     * @param string $path The path, starting with a slash
     * @return string
     */
    public function url(string $path): string
    {
        return 'http://127.0.0.1:' . $this->port . $path;
    }

    /**
     * Is the server still listening?
     * @return bool
     */
    public function isOpen(): bool
    {
        return self::portOpen($this->port);
    }

    /**
     * Forget what the server has received so far
     * @return void
     */
    public function forget(): void
    {
        @file_put_contents($this->log, '');
    }

    /**
     * What the server has received so far
     * @return array Each request with its 'head' (the text of the request line and the headers, with the port replaced by PORT) and its 'body'
     */
    public function requests(): array
    {
        if (!is_file($this->log)) {
            return [];
        }

        $out = [];

        foreach (file($this->log, FILE_IGNORE_NEW_LINES) as $line) {
            $request = json_decode($line, true);
            $request['head'] = str_replace('127.0.0.1:' . $this->port, '127.0.0.1:PORT', $request['head']);
            $request['body'] = base64_decode($request['body']);
            $out[] = $request;
        }

        return $out;
    }

    /**
     * "METHOD /path" of every request that the server has received
     * @return string[]
     */
    public function seen(): array
    {
        return array_map(function (array $request) {
            $parts = explode(' ', strtok($request['head'], "\r\n"));

            return $parts[0] . ' ' . $parts[1];
        }, $this->requests());
    }

    /**
     * The header lines of a request (without the request line)
     * @param array $request A request of requests()
     * @return string[]
     */
    public static function headerLines(array $request): array
    {
        return array_slice(explode("\r\n", $request['head']), 1);
    }

    /**
     * Make a plain GET, outside of the code under test (to reset the state of a route of the server)
     * @param string $path The path
     * @return void
     */
    public function get(string $path): void
    {
        $connection = stream_socket_client('tcp://127.0.0.1:' . $this->port);
        fwrite($connection, "GET $path HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");
        stream_get_contents($connection);
        fclose($connection);
    }

    /**
     * Stop the server and make sure that its process is gone
     * @return void
     */
    public function stop(): void
    {
        if ($this->stopped) {
            return;
        }

        $this->stopped = true;
        $connection = @stream_socket_client('tcp://127.0.0.1:' . $this->port, $errno, $errstr, 0.5);

        if ($connection) {
            @fwrite($connection, "GET /quit HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");
            @stream_get_contents($connection);
            @fclose($connection);
        }

        usleep(300000);
        $status = proc_get_status($this->process);

        if ($status['running']) {
            proc_terminate($this->process);

            if (PHP_OS_FAMILY === 'Windows') {
                @exec('taskkill /F /PID ' . (int) $status['pid'] . ' >NUL 2>&1');
            }
        }

        foreach ($this->pipes as $pipe) {
            @fclose($pipe);
        }

        @proc_close($this->process);
        @unlink($this->log);
    }
}
