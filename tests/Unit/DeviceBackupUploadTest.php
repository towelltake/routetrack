<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DeviceBackupUploadTest extends TestCase
{
    public function test_legacy_device_backup_upload_endpoint_stores_file_in_expected_folder(): void
    {
        $basePath = storage_path('framework/testing/device-backups');
        $expectedFile = $basePath . DIRECTORY_SEPARATOR . 'tab_db_safat' . DIRECTORY_SEPARATOR . now()->format('Ymd') . DIRECTORY_SEPARATOR . 'device-backup.db';

        if (is_file($expectedFile)) {
            unlink($expectedFile);
        }

        config()->set('device-backups.base_path', $basePath);

        $response = $this->post('/upload/upload.php?id=2', [
            'uploaded_file' => UploadedFile::fake()->create('device-backup.db', 32, 'application/octet-stream'),
        ]);

        $response->assertOk();
        $response->assertSeeText('sucess');
        $this->assertFileExists($expectedFile);
    }

    public function test_legacy_device_backup_upload_endpoint_returns_fail_when_file_is_missing(): void
    {
        config()->set('device-backups.base_path', storage_path('framework/testing/device-backups'));

        $response = $this->post('/upload/upload.php?id=3');

        $response->assertOk();
        $response->assertSeeText('fail');
    }
}
