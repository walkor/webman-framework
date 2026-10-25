<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support;

use Phar;
use RuntimeException;

/**
 * Serializes access to the running phar across forked processes.
 *
 * The phar extension reads an entry by seeking the archive descriptor and then
 * reading from it. That descriptor stays open for the whole process lifetime
 * when the archive is compressed (it is a decompressed temp file) or tar/zip
 * based (the running stub keeps it referenced), so after fork all workers
 * share one file offset and interleaved seek/read pairs return foreign bytes.
 *
 * Every native phar call is wrapped in an exclusive flock on the archive file.
 * The lock covers a single call, never the lifetime of an open stream, so a
 * long-lived handle (e.g. a file being sent to a slow client) does not block
 * other workers.
 *
 * The lock alone is not enough. PHP satisfies a forward seek from its read
 * buffer and then continues with read() at the kernel offset. After fork that
 * offset belongs to whichever worker used the descriptor last, so a read is
 * preceded by a seek that PHP cannot satisfy from the buffer.
 */
final class PharStream
{
    private const LOCK_ATTEMPTS = 10;

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $stream = null;

    /** @var resource|null */
    private $directory = null;

    private static string $archive = '';

    /** @var resource|null */
    private static $lock = null;

    private static int $lockPid = 0;

    private static bool $locked = false;

    private static bool $native = false;

    /**
     * Only needed where workers are forked. Windows has no fork, and its
     * mandatory file locks would make the archive unreadable while locked.
     */
    public static function register(): void
    {
        if (self::$archive !== '' || !function_exists('pcntl_fork')) {
            return;
        }
        $archive = Phar::running(false);
        if ($archive === '' || !in_array('phar', stream_get_wrappers(), true)) {
            return;
        }
        stream_wrapper_unregister('phar');
        if (!stream_wrapper_register('phar', self::class)) {
            stream_wrapper_restore('phar');
            trigger_error('Unable to register phar stream wrapper', E_USER_WARNING);
            return;
        }
        self::$archive = $archive;
        self::checkSwowAsyncIo();
    }

    /**
     * Swow's async file IO cannot be switched off at runtime, so a coroutine
     * would yield inside flock or fread and let another one enter.
     */
    private static function checkSwowAsyncIo(): void
    {
        if (!extension_loaded('swow') || !filter_var(ini_get('swow.enable'), FILTER_VALIDATE_BOOL)) {
            return;
        }
        if (filter_var(ini_get('swow.async_file'), FILTER_VALIDATE_BOOL) || filter_var(ini_get('swow.async_tty'), FILTER_VALIDATE_BOOL)) {
            $config = PHP_SAPI === 'micro' ? 'custom_ini of the webman/console build config' : 'php.ini';
            trigger_error("Swow async IO interleaves phar reads across coroutines, set swow.async_file=0 and swow.async_tty=0 in $config", E_USER_WARNING);
        }
    }

    /**
     * @param callable $operation
     * @return mixed
     */
    private static function locked(callable $operation): mixed
    {
        if (self::$locked) {
            return $operation();
        }
        $hooks = self::suspendFileHooks();
        try {
            $lock = self::acquire();
            self::$locked = true;
            try {
                return $operation();
            } finally {
                self::$locked = false;
                flock($lock, LOCK_UN);
            }
        } finally {
            self::resumeFileHooks($hooks);
        }
    }

    /**
     * Run a path based operation with the original phar wrapper in place.
     *
     * The user wrapper is unregistered before the original one is restored:
     * restoring over it directly never frees the user wrapper and leaks memory
     * on every call.
     *
     * @param callable $operation
     * @return mixed
     */
    private static function native(callable $operation): mixed
    {
        return self::locked(static function () use ($operation) {
            if (self::$native) {
                return $operation();
            }
            stream_wrapper_unregister('phar');
            stream_wrapper_restore('phar');
            self::$native = true;
            try {
                return $operation();
            } finally {
                self::$native = false;
                stream_wrapper_unregister('phar');
                stream_wrapper_register('phar', self::class);
            }
        });
    }

    /**
     * flock() locks belong to the open file description, which forked
     * processes share, so every process needs its own handle.
     *
     * PHP closes resources newest first on shutdown, so a phar stream opened
     * before this process created its handle is closed after the handle.
     *
     * @return resource
     */
    private static function acquire()
    {
        $pid = getmypid();
        if (self::$lockPid !== $pid || !is_resource(self::$lock)) {
            $lock = fopen(self::$archive, 'rb');
            if ($lock === false) {
                throw new RuntimeException('Unable to open ' . self::$archive . ' for locking');
            }
            if (is_resource(self::$lock)) {
                fclose(self::$lock);
            }
            self::$lock = $lock;
            self::$lockPid = $pid;
        }
        // A signal handler installed without SA_RESTART interrupts a blocking flock.
        for ($attempt = 1; !flock(self::$lock, LOCK_EX); $attempt++) {
            if ($attempt === self::LOCK_ATTEMPTS) {
                throw new RuntimeException('Unable to lock ' . self::$archive);
            }
        }
        return self::$lock;
    }

    /**
     * Swoole's file hook makes newly opened plain files yield, and its stdio
     * hook swaps the ops of every plain file stream, including the archive
     * descriptor and the lock handle. Either one lets another coroutine of
     * this process enter the critical section inside flock or fread.
     *
     * Hooked calls only yield inside a coroutine. Outside one the flags are
     * left alone, which also keeps away from the Swoole runtime while PHP
     * closes the remaining streams on shutdown, where changing them crashes.
     */
    private static function suspendFileHooks(): int
    {
        if (!defined('SWOOLE_HOOK_STDIO') || \Swoole\Coroutine::getCid() < 0) {
            return 0;
        }
        $flags = \Swoole\Runtime::getHookFlags();
        $fileHooks = $flags & (SWOOLE_HOOK_FILE | SWOOLE_HOOK_STDIO);
        if ($fileHooks === 0) {
            return 0;
        }
        \Swoole\Runtime::setHookFlags($flags & ~$fileHooks);
        return $flags;
    }

    private static function resumeFileHooks(int $flags): void
    {
        if ($flags !== 0) {
            \Swoole\Runtime::setHookFlags($flags);
        }
    }

    /**
     * The original wrapper reports normalized entry paths, which become
     * __FILE__ of included files and keys of include_once.
     */
    private static function normalize(string $path): string
    {
        $prefix = 'phar://' . self::$archive . '/';
        if (!str_starts_with($path, $prefix)) {
            return $path;
        }
        $segments = [];
        foreach (explode('/', substr($path, strlen($prefix))) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        return $prefix . implode('/', $segments);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $context = $this->context;
        $stream = self::native(static fn() => $options & STREAM_REPORT_ERRORS
            ? fopen($path, $mode, false, $context)
            : @fopen($path, $mode, false, $context));
        if ($stream === false) {
            return false;
        }
        $this->stream = $stream;
        $openedPath = self::normalize($path);
        return true;
    }

    public function stream_read(int $count): string|false
    {
        return self::locked(function () use ($count) {
            $this->resync();
            return fread($this->stream, $count);
        });
    }

    public function stream_write(string $data): int
    {
        return self::locked(fn() => (int)fwrite($this->stream, $data));
    }

    /**
     * Move the shared descriptor to this stream's position with a real lseek.
     *
     * Only a forward seek can be served from PHP's read buffer. Seeking to the
     * end of the entry first makes the seek back to the current position
     * reach the descriptor and drop whatever was buffered before the lock.
     */
    private function resync(): void
    {
        $position = (int)ftell($this->stream);
        fseek($this->stream, 0, SEEK_END);
        fseek($this->stream, $position);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return self::locked(fn() => fseek($this->stream, $offset, $whence) === 0);
    }

    public function stream_flush(): bool
    {
        return self::locked(fn() => fflush($this->stream));
    }

    public function stream_close(): void
    {
        self::locked(fn() => fclose($this->stream));
        $this->stream = null;
    }

    public function stream_tell(): int
    {
        return (int)ftell($this->stream);
    }

    public function stream_eof(): bool
    {
        return feof($this->stream);
    }

    public function stream_stat(): array|false
    {
        return fstat($this->stream);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function url_stat(string $path, int $flags): array|false
    {
        $stat = $flags & STREAM_URL_STAT_LINK ? 'lstat' : 'stat';
        return self::native(static fn() => $flags & STREAM_URL_STAT_QUIET ? @$stat($path) : $stat($path));
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $directory = self::native(static fn() => opendir($path));
        if ($directory === false) {
            return false;
        }
        $this->directory = $directory;
        return true;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->directory);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->directory);
        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->directory);
        $this->directory = null;
        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return self::native(static fn() => mkdir($path, $mode, (bool)($options & STREAM_MKDIR_RECURSIVE)));
    }

    public function rmdir(string $path, int $options): bool
    {
        return self::native(static fn() => rmdir($path));
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        return self::native(static fn() => rename($pathFrom, $pathTo));
    }

    public function unlink(string $path): bool
    {
        return self::native(static fn() => unlink($path));
    }
}
