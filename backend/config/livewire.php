<?php

return [
    // The only upload features in this application are administrator catalog images.
    // Restrict temporary files too, before they reach a Filament resource form.
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        'directory' => 'livewire-tmp',
        'middleware' => ['auth:web', 'admin', 'throttle:uploads'],
        'preview_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
