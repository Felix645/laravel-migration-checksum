<?php

declare(strict_types=1);

namespace Neon\Migration\Checksum;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Neon\Migration\Checksum\Exceptions\KeyNotRegistered;

class Checksum
{
    /** @var array<string, Checksum> */
    private static array $instances = [];

    /** @var array<string, array<string, string>> */
    private static array $paths = [];

    private static string $migrations_path_base = 'migrations';
    
    private static string $checksum_path_base = 'checksum';

    private string $db_connection;

    private string $migrations_path;

    private string $checksum_path;

    private string $checksum_filepath;

    private ?bool $has_new_migrations = null;

    private string $checksum = '';

    private bool $is_up_to_date = false;

    private array $tables = [];

    public static function registerDir(string $key, string $path, string $db_connection): void
    {
        self::$paths[$key]['path'] = $path;
        self::$paths[$key]['db_connection'] = $db_connection;
    }

    public static function setMigrationsPath(string $path): void
    {
        self::$migrations_path_base = $path;
    }

    public static function setChecksumPath(string $path): void
    {
        self::$checksum_path_base = $path;
    }

    public static function dir(string $key): Checksum
    {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($key);
        }

        return self::$instances[$key];
    }

    public static function clearInstances(): void
    {
        self::$instances = [];
    }

    public static function reset(): void
    {
        foreach (self::$paths as $key => $key_config) {
            Checksum::dir($key)->resetChecksum();
        }
    }

    /**
     * @throws KeyNotRegistered
     */
    private function __construct(string $key)
    {
        if (!isset(self::$paths[$key])) {
            KeyNotRegistered::throw($key);
        }

        $this->migrations_path = self::$migrations_path_base . '/' . self::$paths[$key]['path'];
        $this->migrations_path = database_path($this->migrations_path);

        $this->checksum_path = database_path(self::$checksum_path_base);
        $this->checksum_filepath = $this->checksum_path . '/' . $key . '.json';

        $this->db_connection = self::$paths[$key]['db_connection'];
    }

    public function resetChecksum(): void
    {
        if (!File::exists($this->checksum_filepath)) {
            return;
        }

        File::delete($this->checksum_filepath);
    }

    public function truncateTables(): void
    {
        DB::connection($this->db_connection)->statement('SET FOREIGN_KEY_CHECKS = 0');

        if (empty($this->tables)) {
            $databaseName = DB::connection($this->db_connection)->getDatabaseName();
            $this->tables = DB::connection($this->db_connection)->select("SELECT * FROM information_schema.tables WHERE table_schema = '$databaseName'");
        }

        foreach ($this->tables as $table) {
            $name = $table->TABLE_NAME;

            if ($name == 'migrations') {
                continue;
            }

            DB::connection($this->db_connection)->table($name)->truncate();
        }

        DB::connection($this->db_connection)->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function hasNewMigrations(): bool
    {
        if ($this->has_new_migrations !== null) {
            return $this->has_new_migrations;
        }

        $has_new_migrations = false;

        $checksum_current = $this->getCurrentChecksum();
        $checksum_new = $this->createNewChecksum();

        if ($checksum_current === '' || $checksum_new !== $checksum_current) {
            $has_new_migrations = true;
            $this->checksum = $checksum_new;
        }

        return $has_new_migrations;
    }

    public function updateChecksum(): void
    {
        $this->has_new_migrations = false;

        if ($this->is_up_to_date) {
            return;
        }

        $this->pushChecksumToFile($this->checksum);

        $this->is_up_to_date = true;
    }

    public function createNewChecksum(): string
    {
        $migrations = File::files($this->migrations_path);

        $data = [];

        foreach ($migrations as $migration) {
            $data[$migration->getFilename()] = $migration->getSize();
        }

        $data_string = serialize($data);

        return md5($data_string);
    }

    private function pushChecksumToFile(string $checksum): void
    {
        File::isDirectory($this->checksum_path) or File::makeDirectory($this->checksum_path, 0777, true, true);

        $json_data = [
            'checksum' => $checksum,
        ];

        $json = json_encode($json_data, JSON_UNESCAPED_SLASHES);

        File::put($this->checksum_filepath, $json);
    }

    private function getCurrentChecksum(): string
    {
        if (!File::exists($this->checksum_filepath)) {
            return '';
        }

        $json = File::get($this->checksum_filepath);

        $data = json_decode($json, true);

        return $data['checksum'];
    }
}