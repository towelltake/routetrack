<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function upload(Request $request): string
    {
        $file = $request->file('file');

        if (! $file) {
            return 'There was an error uploading the file, please try again!';
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return (string) $file->getError();
        }

        $directory = public_path('customerimage');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file->move($directory, $file->getClientOriginalName());

        return 'The file ' . $file->getClientOriginalName() . ' has beenuploaded';
    }
}
