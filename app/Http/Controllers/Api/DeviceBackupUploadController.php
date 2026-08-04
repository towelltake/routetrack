<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class DeviceBackupUploadController extends LegacyApiController
{
    private const DESTINATIONS = [
        '1' => 'sfaind',
        '2' => 'tab_db_safat',
        '3' => 'tab_db_gtrc',
    ];

    public function upload(Request $request)
    {
        $file = $request->file('uploaded_file') ?? $request->file('file');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return $this->legacyText('fail');
        }

        $id = (string) $request->input('id', '');
        $folder = self::DESTINATIONS[$id] ?? 'tab_db_other';
        $dateFolder = now()->format('Ymd');
        $basePath = rtrim(
            (string) config('device-backups.base_path', storage_path('app/device-backups')),
            DIRECTORY_SEPARATOR
        );
        $targetDirectory = $basePath . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $dateFolder;

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            return $this->legacyText('fail');
        }

        $targetName = $file->getClientOriginalName();

        try {
            $file->move($targetDirectory, $targetName);
        } catch (\Throwable) {
            return $this->legacyText('fail');
        }

        return $this->legacyText('sucess');
    }
}
