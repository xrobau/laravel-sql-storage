<?php

// Note that there is ALSO a table created called '$table_blobstore'
// which is used to actually store the blobs. Performance tests implied
// that it was slightly faster. YMMV.

return [
    'disks' => [
        'database' => [
            'driver' => 'sqlstore',
            'table' => 'sqlstorage',
            'connection' => env('DB_CONNECTION', 'mysql'),
        ],
    ],
];
