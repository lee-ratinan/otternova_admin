<?php

namespace App\Libraries;

/**
 * A ZIP archive of small text files, built in memory without the zip extension (which the server's PHP does not
 * have): every file is stored as it is, uncompressed, which every unzip tool and operating system opens.
 */
final class ZipWriter
{
    /** @var list<array{name: string, crc: int, size: int, offset: int}> */
    private array $entries = [];

    private string $data = '';

    /**
     * @param string $name    Path inside the archive, with / between folders
     * @param string $content
     * @return $this
     */
    public function add(string $name, string $content): self
    {
        $crc    = crc32($content);
        $size   = strlen($content);
        $offset = strlen($this->data);
        // local file header: version 2.0, flag 0x0800 (UTF-8 names), stored, DOS time and date of 1980-01-01
        $this->data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, 0, 0x21, $crc, $size, $size, strlen($name), 0) . $name . $content;
        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size, 'offset' => $offset];

        return $this;
    }

    /**
     * The archive's bytes
     * @return string
     */
    public function build(): string
    {
        $directory = '';
        foreach ($this->entries as $entry) {
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, 0, 0x21, $entry['crc'], $entry['size'], $entry['size'],
                strlen($entry['name']), 0, 0, 0, 0, 0, $entry['offset']) . $entry['name'];
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($this->entries), count($this->entries), strlen($directory), strlen($this->data), 0);

        return $this->data . $directory . $end;
    }
}
