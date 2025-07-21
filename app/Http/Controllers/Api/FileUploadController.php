<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class FileUploadController extends Controller
{
    /**
     * Umumiy rasm yuklash
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB
            'folder' => 'sometimes|string|in:products,avatars,reviews,categories',
            'resize' => 'sometimes|boolean',
            'width' => 'sometimes|integer|min:100|max:2000',
            'height' => 'sometimes|integer|min:100|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $image = $request->file('image');
            $folder = $request->get('folder', 'uploads');
            $resize = $request->get('resize', false);
            $width = $request->get('width', 800);
            $height = $request->get('height', 600);

            // Unique fayl nomi yaratish
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = $folder . '/' . $filename;

            // Rasm o'lchamini o'zgartirish (agar kerak bo'lsa)
            if ($resize) {
                $resizedImage = Image::make($image)
                                   ->resize($width, $height, function ($constraint) {
                                       $constraint->aspectRatio();
                                       $constraint->upsize();
                                   })
                                   ->encode('jpg', 85);

                Storage::disk('public')->put($path, $resizedImage);
            } else {
                $path = $image->store($folder, 'public');
            }

            // Thumbnail yaratish (kichik rasm)
            $this->createThumbnail($path, $folder);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'thumbnail_url' => asset('storage/thumbnails/' . $path),
                    'filename' => $filename,
                    'size' => Storage::disk('public')->size($path),
                    'mime_type' => Storage::disk('public')->mimeType($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Multiple rasmlar yuklash
     */
    public function uploadMultipleImages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB har biri
            'folder' => 'sometimes|string|in:products,reviews,galleries'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $folder = $request->get('folder', 'uploads');
            $uploadedImages = [];

            foreach ($request->file('images') as $index => $image) {
                $filename = time() . '_' . $index . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs($folder, $filename, 'public');

                // Thumbnail yaratish
                $this->createThumbnail($path, $folder);

                $uploadedImages[] = [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'thumbnail_url' => asset('storage/thumbnails/' . $path),
                    'filename' => $filename,
                    'size' => Storage::disk('public')->size($path),
                    'sort_order' => $index
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Images uploaded successfully',
                'data' => $uploadedImages
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rasmni o'chirish
     */
    public function deleteImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->path;

            // Asosiy rasmni o'chirish
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            // Thumbnail'ni o'chirish
            $thumbnailPath = 'thumbnails/' . $path;
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rasmni crop qilish
     */
    public function cropImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'x' => 'required|integer|min:0',
            'y' => 'required|integer|min:0',
            'width' => 'required|integer|min:10',
            'height' => 'required|integer|min:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->path;
            $x = $request->x;
            $y = $request->y;
            $width = $request->width;
            $height = $request->height;

            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            // Yangi fayl nomi
            $pathInfo = pathinfo($path);
            $newFilename = $pathInfo['filename'] . '_cropped_' . time() . '.' . $pathInfo['extension'];
            $newPath = $pathInfo['dirname'] . '/' . $newFilename;

            // Rasmni crop qilish
            $image = Image::make(storage_path('app/public/' . $path))
                         ->crop($width, $height, $x, $y)
                         ->encode('jpg', 85);

            Storage::disk('public')->put($newPath, $image);

            // Yangi thumbnail yaratish
            $this->createThumbnail($newPath, $pathInfo['dirname']);

            return response()->json([
                'success' => true,
                'message' => 'Image cropped successfully',
                'data' => [
                    'path' => $newPath,
                    'url' => asset('storage/' . $newPath),
                    'thumbnail_url' => asset('storage/thumbnails/' . $newPath)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to crop image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rasmni resize qilish
     */
    public function resizeImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'width' => 'required|integer|min:50|max:2000',
            'height' => 'required|integer|min:50|max:2000',
            'maintain_aspect_ratio' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->path;
            $width = $request->width;
            $height = $request->height;
            $maintainAspectRatio = $request->get('maintain_aspect_ratio', true);

            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            // Yangi fayl nomi
            $pathInfo = pathinfo($path);
            $newFilename = $pathInfo['filename'] . '_resized_' . $width . 'x' . $height . '_' . time() . '.' . $pathInfo['extension'];
            $newPath = $pathInfo['dirname'] . '/' . $newFilename;

            // Rasmni resize qilish
            $image = Image::make(storage_path('app/public/' . $path));

            if ($maintainAspectRatio) {
                $image->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            } else {
                $image->resize($width, $height);
            }

            $image->encode('jpg', 85);
            Storage::disk('public')->put($newPath, $image);

            // Yangi thumbnail yaratish
            $this->createThumbnail($newPath, $pathInfo['dirname']);

            return response()->json([
                'success' => true,
                'message' => 'Image resized successfully',
                'data' => [
                    'path' => $newPath,
                    'url' => asset('storage/' . $newPath),
                    'thumbnail_url' => asset('storage/thumbnails/' . $newPath),
                    'dimensions' => [
                        'width' => $image->width(),
                        'height' => $image->height()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resize image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rasm haqida ma'lumot olish
     */
    public function getImageInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->path;

            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            $fullPath = storage_path('app/public/' . $path);
            $imageInfo = getimagesize($fullPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'size' => Storage::disk('public')->size($path),
                    'mime_type' => $imageInfo['mime'] ?? Storage::disk('public')->mimeType($path),
                    'dimensions' => [
                        'width' => $imageInfo[0] ?? null,
                        'height' => $imageInfo[1] ?? null
                    ],
                    'last_modified' => Storage::disk('public')->lastModified($path),
                    'exists' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get image info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Thumbnail yaratish (private method)
     */
    private function createThumbnail($imagePath, $folder)
    {
        try {
            $thumbnailPath = 'thumbnails/' . $imagePath;
            $thumbnailDir = 'thumbnails/' . $folder;

            // Thumbnail directory yaratish
            if (!Storage::disk('public')->exists($thumbnailDir)) {
                Storage::disk('public')->makeDirectory($thumbnailDir);
            }

            // Thumbnail yaratish (300x300 max)
            $thumbnail = Image::make(storage_path('app/public/' . $imagePath))
                             ->resize(300, 300, function ($constraint) {
                                 $constraint->aspectRatio();
                                 $constraint->upsize();
                             })
                             ->encode('jpg', 80);

            Storage::disk('public')->put($thumbnailPath, $thumbnail);

        } catch (\Exception $e) {
            // Thumbnail yaratishda xatolik bo'lsa, davom etamiz
            \Log::warning('Failed to create thumbnail: ' . $e->getMessage());
        }
    }

    /**
     * Storage cleanup (eski fayllarni o'chirish)
     */
    public function cleanup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days_old' => 'sometimes|integer|min:1|max:365',
            'folder' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $daysOld = $request->get('days_old', 30); // Default 30 kun
            $folder = $request->get('folder', 'uploads');
            $cutoffTime = now()->subDays($daysOld)->timestamp;

            $deletedFiles = 0;
            $deletedSize = 0;

            // Folder'dagi barcha fayllarni ko'rish
            $files = Storage::disk('public')->allFiles($folder);

            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);
                
                if ($lastModified < $cutoffTime) {
                    $fileSize = Storage::disk('public')->size($file);
                    
                    // Asosiy fayl va thumbnail'ni o'chirish
                    Storage::disk('public')->delete($file);
                    if (Storage::disk('public')->exists('thumbnails/' . $file)) {
                        Storage::disk('public')->delete('thumbnails/' . $file);
                    }
                    
                    $deletedFiles++;
                    $deletedSize += $fileSize;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Storage cleanup completed',
                'data' => [
                    'deleted_files' => $deletedFiles,
                    'deleted_size' => $this->formatFileSize($deletedSize),
                    'folder' => $folder,
                    'days_old' => $daysOld
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup storage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Storage statistics
     */
    public function storageStats()
    {
        try {
            $folders = ['avatars', 'products', 'reviews', 'categories', 'uploads'];
            $stats = [];
            $totalSize = 0;
            $totalFiles = 0;

            foreach ($folders as $folder) {
                if (Storage::disk('public')->exists($folder)) {
                    $files = Storage::disk('public')->allFiles($folder);
                    $folderSize = 0;
                    
                    foreach ($files as $file) {
                        $folderSize += Storage::disk('public')->size($file);
                    }
                    
                    $stats[$folder] = [
                        'files_count' => count($files),
                        'size' => $folderSize,
                        'size_formatted' => $this->formatFileSize($folderSize)
                    ];
                    
                    $totalSize += $folderSize;
                    $totalFiles += count($files);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'folders' => $stats,
                    'total' => [
                        'files_count' => $totalFiles,
                        'size' => $totalSize,
                        'size_formatted' => $this->formatFileSize($totalSize)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get storage stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * File size'ni format qilish
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}