<?php

namespace Neon\Migration\Checksum\Tests;

use Illuminate\Support\Facades\File;
use Neon\Migration\Checksum\Checksum;

class ChecksumTest extends \Neon\Migration\Checksum\Tests\TestCase
{
    private static string $base_json_path;

    private static string $another_json_path;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        Checksum::registerDir(key: 'base', path: 'base', db_connection: 'base_db');
        Checksum::registerDir(key: 'another', path: 'another', db_connection: 'another_db');
    }

    public function setUp(): void
    {
        parent::setUp();

        self::$base_json_path = database_path('checksum/base.json');
        self::$another_json_path = database_path('checksum/another.json');

        $path_base = database_path('migrations/base');
        $path_another = database_path('migrations/another');

        File::isDirectory($path_base) or File::makeDirectory($path_base, 0777, true, true);
        File::isDirectory($path_another) or File::makeDirectory($path_another, 0777, true, true);
    }

    public function test_checksums_are_created(): void
    {
        $checksum_base = Checksum::dir('base');
        $checksum_another = Checksum::dir('another');

        $checksum_base->hasNewMigrations();
        $checksum_another->hasNewMigrations();

        $checksum_base->updateChecksum();
        $checksum_another->updateChecksum();

        $base_checksum_exists = File::exists(self::$base_json_path);
        $another_checksum_exists = File::exists(self::$another_json_path);

        $this->assertTrue($base_checksum_exists);
        $this->assertTrue($another_checksum_exists);
    }

    public function test_checksum_files_contain_expected_json(): void
    {
        $checksum_base = Checksum::dir('base');
        $checksum_another = Checksum::dir('another');

        $checksum_base->hasNewMigrations();
        $checksum_another->hasNewMigrations();

        $checksum_base->updateChecksum();
        $checksum_another->updateChecksum();

        $json_base = File::get(self::$base_json_path);
        $json_another = File::get(self::$another_json_path);

        $this->assertJson($json_base);
        $this->assertJson($json_another);

        $data_base = json_decode($json_base, true);
        $data_another = json_decode($json_another, true);

        $this->assertIsArray($data_base);
        $this->assertIsArray($data_another);

        $this->assertArrayHasKey('checksum', $data_base);
        $this->assertArrayHasKey('checksum', $data_another);

        $this->assertNotEmpty($data_base['checksum']);
        $this->assertNotEmpty($data_another['checksum']);
    }

    public function test_adding_file_changes_checksum(): void
    {
        $checksum = Checksum::dir('base');
        $checksum->updateChecksum();

        $checksum_before = json_decode(File::get(self::$base_json_path), true)['checksum'];

        File::put(database_path('migrations/base/test.json'), json_encode(['test']));

        Checksum::clearInstances();

        $checksum = Checksum::dir('base');

        $this->assertTrue($checksum->hasNewMigrations());

        $checksum->updateChecksum();

        $checksum_after = json_decode(File::get(self::$base_json_path), true)['checksum'];

        $this->assertNotEquals($checksum_before, $checksum_after);
    }

    public function test_changing_file_changes_checksum(): void
    {
        File::put(database_path('migrations/base/test.json'), json_encode(['test']));

        $checksum = Checksum::dir('base');
        $checksum->updateChecksum();

        $checksum_before = json_decode(File::get(self::$base_json_path), true)['checksum'];

        File::put(database_path('migrations/base/test.json'), json_encode(['test', 'second']));

        Checksum::clearInstances();

        $checksum = Checksum::dir('base');

        $this->assertTrue($checksum->hasNewMigrations());

        $checksum->updateChecksum();

        $checksum_after = json_decode(File::get(self::$base_json_path), true)['checksum'];

        $this->assertNotEquals($checksum_before, $checksum_after);
    }

    public function test_reset_command_removes_checksum_files(): void
    {
        Checksum::dir('base')->updateChecksum();
        Checksum::dir('another')->updateChecksum();

        $base_checksum_exists = File::exists(self::$base_json_path);
        $another_checksum_exists = File::exists(self::$another_json_path);

        $this->assertTrue($base_checksum_exists);
        $this->assertTrue($another_checksum_exists);

        $this->artisan('neon:reset-migration-checksum');

        $base_checksum_exists = File::exists(self::$base_json_path);
        $another_checksum_exists = File::exists(self::$another_json_path);

        $this->assertFalse($base_checksum_exists);
        $this->assertFalse($another_checksum_exists);
    }

    public function tearDown(): void
    {
        $path_base = database_path('migrations/base');
        $path_another = database_path('migrations/another');

        if (File::isDirectory($path_base)) {
            File::deleteDirectory($path_base);
        }

        if (File::isDirectory($path_another)) {
            File::deleteDirectory($path_another);
        }

        if (File::isDirectory(database_path('checksum'))) {
            File::deleteDirectory(database_path('checksum'));
        }

        Checksum::clearInstances();

        parent::tearDown();
    }
}