<?php

declare(strict_types=1);

namespace JoeyMcKenzie\Sqlighter\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use JoeyMcKenzie\Sqlighter\Commands\RunDatabaseBackup;

describe(RunDatabaseBackup::class, function (): void {
    beforeEach(function (): void {
        $this->backupPath = database_path('backups/');
        $this->databasePath = database_path('database.sqlite');
    });

    test('creates backup directory if it does not exist', function (): void {
        // Arrange
        expect(File::exists($this->backupPath))->toBeFalse();

        // Act
        $this->artisan(RunDatabaseBackup::class)
            ->assertSuccessful();

        // Assert
        expect(File::exists($this->backupPath))->toBeTrue();
        expect(File::get($this->backupPath.'/.gitignore'))->toContain('backup-*.sql');
    });

    test('creates backup with correct filename pattern', function (): void {
        // Act
        $this->artisan(RunDatabaseBackup::class)
            ->assertSuccessful();

        // Assert
        $files = File::files($this->backupPath);
        expect(count($files))->toBe(1);

        $filename = basename($files[0]->getFilename());
        $this->assertMatchesRegularExpression('/backup-\d+\.sql/', $filename);
    });

    test('creates backup with correct filename prefix', function (): void {
        // Arrange
        Config::set('sqlighter.file_prefix', 'db_backup');

        // Act
        $this->artisan(RunDatabaseBackup::class)
            ->assertSuccessful();

        // Act & Assert
        $files = File::files($this->backupPath);
        expect(count($files))->toBe(1);

        $filename = basename($files[0]->getFilename());
        $this->assertMatchesRegularExpression('/db_backup-\d+\.sql/', $filename);
    });

    test('maintains correct number of backup copies', function (): void {
        // Arrange
        Config::set('sqlighter.copies_to_maintain', 2);

        // Act - Run backup command multiple times
        for ($i = 0; $i < 4; $i++) {
            $this->artisan(RunDatabaseBackup::class)
                ->assertSuccessful();

            // Add small delay to ensure different timestamps
            sleep(1);
        }

        // Assert
        $files = File::files($this->backupPath);
        expect(count($files))->toBe(2);
    });

    test('ensure backups are skipped when not using sqlite', function (): void {
        // Arrange
        Config::set('database.default', 'mysql');

        // Act & Assert
        $this->artisan(RunDatabaseBackup::class)
            ->expectsOutput('The configured database is not using SQLite, bypassing database backup')
            ->assertFailed();
    });

    test('ensure backups are skipped when configuration option is disabled', function (): void {
        // Arrange
        Config::set('sqlighter.enabled', false);

        // Act & Assert
        $this->artisan(RunDatabaseBackup::class)
            ->expectsOutput('Database backups are not enabled, bypassing file copy')
            ->assertSuccessful();

        $this->assertFalse(File::exists($this->backupPath));
    });
});