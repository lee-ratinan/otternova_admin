<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The apps' error logs (CodeIgniter's writable/logs/log-YYYY-MM-DD.log), behind the auth and department:EXE,ENG
 * filters: pick an app, then one of its log files, then read its entries. Each app's folder comes from this app's
 * .env (ERROR_LOG_PATH_API, _ADMIN, _CLIENT, _MART); in Docker the other apps' folders are mounted read-only. Only
 * files named like CodeIgniter's logs, directly in that folder, are ever read. Every app logs in UTC.
 */
class ErrorLogs extends BaseController
{
    /** site => its label, the .env key of its log folder and its icon */
    public const SITES = [
        'api'    => ['label' => 'API', 'env' => 'ERROR_LOG_PATH_API', 'icon' => 'bi-hdd-network'],
        'admin'  => ['label' => 'Admin', 'env' => 'ERROR_LOG_PATH_ADMIN', 'icon' => 'bi-shield-lock'],
        'client' => ['label' => 'Client', 'env' => 'ERROR_LOG_PATH_CLIENT', 'icon' => 'bi-people'],
        'mart'   => ['label' => 'Marketplace', 'env' => 'ERROR_LOG_PATH_MART', 'icon' => 'bi-bag-check'],
    ];

    /** A log file as CodeIgniter's FileHandler names it */
    public const FILE_PATTERN = '/^log-(\d{4}-\d{2}-\d{2})\.log$/';

    /** The first line of an entry: "ERROR - 2026-11-12 01:49:12 --> message"; the lines after it, up to the next, belong to it */
    private const ENTRY_PATTERN = '/^(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG) - (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) --> ?(.*)$/';

    /** The most of a file the page reads, from its end; a larger file is cut at the first entry within it */
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const LEVELS = [
        'EMERGENCY' => 'bg-danger',
        'ALERT'     => 'bg-danger',
        'CRITICAL'  => 'bg-danger',
        'ERROR'     => 'bg-warning text-dark',
        'WARNING'   => 'bg-info text-dark',
        'NOTICE'    => 'bg-secondary',
        'INFO'      => 'bg-primary',
        'DEBUG'     => 'bg-light text-dark border',
    ];

    /**
     * The apps, each with how many log files it has and its latest one
     * @return string
     */
    public function sites(): string
    {
        $sites = [];
        foreach (self::SITES as $site => $info) {
            $dir   = $this->folder($site);
            $files = $dir === null ? [] : $this->logFiles($dir);
            $sites[$site] = $info + [
                'configured' => (string) getenv($info['env']) !== '',
                'readable'   => $dir !== null,
                'count'      => count($files),
                'size'       => array_sum(array_column($files, 'size')),
                'latest'     => $files[0] ?? null,
            ];
        }

        return view('admin_logs_error_sites', [
            'page_title' => 'Error Logs',
            'slug'       => 'logs-error',
            'sites'      => $sites,
        ]);
    }

    /**
     * One app's log files, newest first
     * @param string $site
     * @return string
     */
    public function files(string $site): string
    {
        $info = $this->site($site);
        $dir  = $this->folder($site);

        return view('admin_logs_error_files', [
            'page_title'  => $info['label'] . ' Error Logs',
            'slug'        => 'logs-error',
            'breadcrumbs' => [base_url('admin/logs/errors') => 'Error Logs'],
            'site'        => $site,
            'info'        => $info,
            'readable'    => $dir !== null,
            'files'       => $dir === null ? [] : $this->logFiles($dir),
        ]);
    }

    /**
     * One log file's entries, newest first; ?download=1 saves the file as it is
     * @param string $site
     * @param string $name
     * @return ResponseInterface|string
     */
    public function file(string $site, string $name): ResponseInterface|string
    {
        $info = $this->site($site);
        $dir  = $this->folder($site);
        $path = $dir === null || preg_match(self::FILE_PATTERN, $name) !== 1 ? false : realpath($dir . DIRECTORY_SEPARATOR . $name);
        // only a file directly in the folder: a link pointing elsewhere is not followed
        if ($path === false || ! is_file($path) || ! is_readable($path) || dirname($path) !== $dir) {
            throw PageNotFoundException::forPageNotFound('This log file could not be found.');
        }

        if ($this->request->getGet('download') === '1') {
            return $this->response->download($path, null)->setFileName($site . '-' . $name)->setContentType('text/plain');
        }

        $size = (int) filesize($path);
        [$entries, $cut] = self::parse($this->readTail($path, $size));
        // level => how many, most severe first, the levels in the file only
        $found  = array_count_values(array_column($entries, 'level'));
        $counts = [];
        foreach (array_keys(self::LEVELS) as $level) {
            if (isset($found[$level])) {
                $counts[$level] = $found[$level];
            }
        }

        return view('admin_logs_error_file', [
            'page_title'  => $name,
            'slug'        => 'logs-error',
            'breadcrumbs' => [base_url('admin/logs/errors') => 'Error Logs', base_url('admin/logs/errors/' . $site) => $info['label']],
            'site'        => $site,
            'info'        => $info,
            'name'        => $name,
            'size'        => $size,
            'modified'    => gmdate('Y-m-d H:i:s', (int) filemtime($path)),
            'truncated'   => $cut || $size > self::MAX_BYTES,
            'entries'     => array_reverse($entries),
            'counts'      => $counts,
        ]);
    }

    /**
     * The entries of a log's text, oldest first, and whether text came before the first entry (a file read from the
     * middle, or lines that are not CodeIgniter's)
     * @param string $text
     * @return array{list<array{level: string, time: string, message: string, details: string}>, bool}
     */
    public static function parse(string $text): array
    {
        $entries = [];
        $orphan  = false;
        foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
            if (preg_match(self::ENTRY_PATTERN, $line, $m) === 1) {
                $entries[] = ['level' => $m[1], 'time' => $m[2], 'message' => $m[3], 'details' => ''];
            } elseif ($entries === []) {
                $orphan = $orphan || trim($line) !== '';
            } else {
                $entries[count($entries) - 1]['details'] .= $line . "\n";
            }
        }
        foreach ($entries as &$entry) {
            $entry['details'] = rtrim($entry['details']);
        }
        unset($entry);

        return [$entries, $orphan];
    }

    /**
     * A file size as "2.4 MB"
     * @param int $bytes
     * @return string
     */
    public static function size(int $bytes): string
    {
        foreach (['GB' => 1 << 30, 'MB' => 1 << 20, 'KB' => 1 << 10] as $unit => $factor) {
            if ($bytes >= $factor) {
                return number_format($bytes / $factor, 1) . ' ' . $unit;
            }
        }

        return $bytes . ' B';
    }

    /**
     * @param string $site
     * @return array{label: string, env: string, icon: string}
     */
    private function site(string $site): array
    {
        if (! isset(self::SITES[$site])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return self::SITES[$site];
    }

    /**
     * The app's log folder from .env, resolved; null when it is not set or cannot be read
     * @param string $site
     * @return string|null
     */
    private function folder(string $site): ?string
    {
        $configured = trim((string) getenv(self::SITES[$site]['env']));
        $dir        = $configured === '' ? false : realpath($configured);
        if ($dir === false || ! is_dir($dir) || ! is_readable($dir)) {
            if ($configured !== '') {
                log_message('warning', 'Error Logs: ' . self::SITES[$site]['env'] . ' (' . $configured . ') is not a readable folder');
            }
            return null;
        }

        return $dir;
    }

    /**
     * The log files of a folder, newest first
     * @param string $dir
     * @return list<array{name: string, date: string, size: int, modified: string}>
     */
    private function logFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (preg_match(self::FILE_PATTERN, $name, $m) === 1 && is_file($path)) {
                $files[] = ['name' => $name, 'date' => $m[1], 'size' => (int) filesize($path), 'modified' => gmdate('Y-m-d H:i:s', (int) filemtime($path))];
            }
        }
        usort($files, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $files;
    }

    /**
     * The file's text, or its last MAX_BYTES when it is larger
     * @param string $path
     * @param int    $size
     * @return string
     */
    private function readTail(string $path, int $size): string
    {
        if ($size <= self::MAX_BYTES) {
            return (string) file_get_contents($path);
        }

        return (string) file_get_contents($path, false, null, $size - self::MAX_BYTES);
    }
}
