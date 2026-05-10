<?php

namespace Karim\SmartBackup\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Karim\SmartBackup\Http\Resources\BackupResource;
use Karim\SmartBackup\Models\Backup;
use Karim\SmartBackup\Services\BackupCleaner;
use Karim\SmartBackup\Services\BackupManager;
use Karim\SmartBackup\Enums\BackupType;
use Karim\SmartBackup\Jobs\RunSmartBackupJob;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    /**
     * Display a paginated list of backup records.
     */
    public function index(Request $request)
    {
        // Return paginated backups list.
        $backups = Backup::query()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return BackupResource::collection($backups);
    }

    /**
     * Create a new backup record and backup archive.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in([
                BackupType::FULL->value,
                BackupType::DATABASE->value,
                BackupType::STORAGE->value,
            ])],
        ]);

        $type = BackupType::tryFrom(
            $request->input('type', BackupType::FULL->value)
        ) ?? BackupType::FULL;

        try {
            RunSmartBackupJob::dispatch($type);

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'تم وضع النسخة الاحتياطية في الطابور بنجاح وسيتم تنفيذها في الخلفية.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إضافة النسخة الاحتياطية للطابور.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the selected backup archive.
     */
    public function download(Backup $backup): JsonResponse|StreamedResponse
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($backup->disk ?: config('smart-backup.disk', 'local'));

        // Make sure the backup file still exists before downloading it.
        if (! $backup->path || ! $disk->exists($backup->path)) {
            return response()->json([
                'success' => false,
                'message' => 'ملف النسخة الاحتياطية غير موجود.',
            ], 404);
        }

        // Download the backup file using its stored name or fallback to the path basename.
        return $disk->download($backup->path, $backup->name ?: basename($backup->path));
    }

    /**
     * Delete a single backup archive and its database record.
     */
    public function destroy(Backup $backup, BackupCleaner $backupCleaner): JsonResponse
    {
        try {
            // Delete the backup file and its database record.
            $backupCleaner->delete($backup);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف النسخة الاحتياطية بنجاح.',
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete multiple backup archives and their database records.
     */
    public function bulkDestroy(Request $request, BackupCleaner $backupCleaner): JsonResponse
    {
        // Get selected backup IDs from the request body.
        $ids = $request->input('ids', []);

        // Validate that at least one backup ID was selected.
        if (! is_array($ids) || $ids === []) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تحديد أي نسخ احتياطية للحذف.',
            ], 422);
        }

        try {
            // Delete all matching backups and return the number of deleted records.
            $deletedCount = $backupCleaner->bulkDelete($ids);

            if ($deletedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على أي نسخ احتياطية مطابقة.',
                    'deleted_count' => 0,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف النسخ الاحتياطية المحددة بنجاح.',
                'deleted_count' => $deletedCount,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
