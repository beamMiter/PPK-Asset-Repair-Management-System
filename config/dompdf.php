<?php

return [

    'show_warnings'    => false,
    'public_path'      => null,
    'convert_entities' => true,

    'options' => [
        'font_dir'   => public_path('images/fonts'),   // ใช้ public/images/fonts
        'font_cache' => storage_path('fonts'),  // cache จะเก็บใน storage ก็ได้
        'temp_dir'   => sys_get_temp_dir(),
        'chroot'     => realpath(base_path()),

        'allowed_protocols' => [
            'data://'  => ['rules' => []],
            'file://'  => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'enable_font_subsetting' => false,
        'pdf_backend'            => 'CPDF',
        'default_media_type'     => 'screen',
        'default_paper_size'     => 'a4',
        'default_paper_orientation' => 'portrait',

        // 👈 ตรงนี้สำคัญ ให้ใช้ sarabun เป็น default
        'default_font'           => 'sarabun',

        'dpi'                    => 96,
        'enable_php'             => false,
        'enable_javascript'      => true,
        'enable_remote'          => false,
        'allowed_remote_hosts'   => null,
        'font_height_ratio'      => 1.1,
        'enable_html5_parser'    => true,
    ],

    // *** ตรงนี้คือส่วนที่เมื่อก่อนนายใส่ผิดที่ ***
    // ต้องอยู่นอก options แบบนี้
    'font_dir'   => public_path('images/fonts'),
    'font_cache' => storage_path('fonts'),

    'fonts' => [
        'sarabun' => [
            'normal'      => public_path('images/fonts/Sarabun-Regular.ttf'),
            'bold'        => public_path('images/fonts/Sarabun-Bold.ttf'),
            'italic'      => public_path('images/fonts/Sarabun-Regular.ttf'),
            'bold_italic' => public_path('images/fonts/Sarabun-Bold.ttf'),
        ],
    ],

];
