<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Cookie;
use App\Http\Resources\CookieResource;
use Illuminate\Http\JsonResponse;

class CookieUpload extends BaseController
{
    public function index(): JsonResponse
    {
        $cookie = Cookie::all();

        return $this->sendResponse(CookieResource::collection($cookie), 'Cookies retrieved successfully.');
    }

    public function justUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:txt,zip|max:2048'
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', $fileName, 'public');

        // return $filePath;
        $filePath2 = storage_path() . '/app/public/' . $filePath;

        if (is_file(($filePath2))) {
            return $fileName;
        }

        return false;
    }

    public function fileUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:txt,zip|max:2048'
        ]);

        $fileModel = new Cookie;
        if ($request->file()) {
            $fileName = time() . '_' . $request->file->getClientOriginalName();
            $filePath = $request->file('file')->storeAs('uploads', $fileName, 'public');

            $fileModel->unit_id = $request->unit_id;
            $fileModel->task_id = $request->task_id;
            $fileModel->name = time() . '_' . $request->file->getClientOriginalName();
            $fileModel->file_path = '/storage/' . $filePath;
            // $fileModel->save();

            if ($fileModel->save()) {
                // $lastInsertedId = $fileModel->id;
                return $this->sendResponse(
                    [
                        'status' => true,
                        'file_name' => $fileModel->name,
                        'file_path' => $fileModel->file_path
                    ],
                    'Cookie uploded successfully.'
                );
            } else {
                return $this->sendError('Cookie Upload error.', ['status' => false, 'file name' => $fileModel->name, 'file path' => $fileModel->file_path]);
            }
        }
    }

    public function fileDownload(Request $request)
    {
        $file = Cookie::where('unit_id', $request->unit_id)->firstWhere('task_id', $request->task_id);

        $headers = array(
            'Content-Type: application/zip',
        );

        $filePath = storage_path() . '/app/public/uploads/' . $file->name;

        return response()->download($filePath, $file->name, $headers, 'inline');
    }

    public function fileDelete(Request $request): JsonResponse
    {
        $file = Cookie::where('unit_id', $request->unit_id)->firstWhere('task_id', $request->task_id);
        $filePath = storage_path() . '/app/public/uploads/' . $file->name;

        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        return $this->sendResponse([], 'Cookie deleted successfully.');
    }
}
