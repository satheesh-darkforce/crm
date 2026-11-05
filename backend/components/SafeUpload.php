<?php

namespace backend\components;

use Yii;
use yii\web\UploadedFile;

/**
 * Saves multi-file uploads into the public /uploads folder safely.
 *
 * Yii1 accepted any file into the web root; here only document / image /
 * archive types are accepted and files get random names, so nothing
 * executable can be uploaded and then requested from /uploads.
 */
class SafeUpload
{
    const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip'];
    const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * Save every file posted under $field into /uploads/$folder.
     *
     * @param callable $saveRecord fn(string $webPath, string $extension): void — persists the attachment row
     * @return string[] names of rejected files (wrong type / too large / failed to save)
     */
    public static function save(string $field, string $folder, string $prefix, callable $saveRecord): array
    {
        $rejected = [];
        $dir      = Yii::getAlias('@backend/web/uploads/' . $folder . '/');

        foreach (UploadedFile::getInstancesByName($field) as $file) {
            if ($file->error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $ext = strtolower($file->extension);
            if ($file->error !== UPLOAD_ERR_OK || !in_array($ext, static::ALLOWED_EXTENSIONS, true) || $file->size > static::MAX_BYTES) {
                $rejected[] = $file->name;
                continue;
            }
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $name = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if ($file->saveAs($dir . $name)) {
                $saveRecord('/uploads/' . $folder . '/' . $name, $ext);
            } else {
                $rejected[] = $file->name;
            }
        }
        return $rejected;
    }

    public static function acceptAttribute(): string
    {
        return '.' . implode(',.', static::ALLOWED_EXTENSIONS);
    }

    public static function flashRejected(array $rejected): void
    {
        if ($rejected) {
            Yii::$app->session->addFlash('warning',
                'These attachments were not saved (allowed: ' . implode(', ', static::ALLOWED_EXTENSIONS)
                . '; max ' . (static::MAX_BYTES / 1024 / 1024) . ' MB): ' . implode(', ', $rejected));
        }
    }
}
