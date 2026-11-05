<?php

namespace backend\components;

/**
 * SafeUpload restricted to web images (product photos, category images).
 */
class ImageUpload extends SafeUpload
{
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    const MAX_BYTES = 5 * 1024 * 1024;
}
