<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where to store product_images on disk
    |--------------------------------------------------------------------------
    |
    | "public" — public_path('product_images') …/your-app/public/product_images
    |           (default Laravel; document root should be the /public folder)
    |
    | "base"   — base_path('product_images') …/your-app/product_images
    |           Use on shared hosting when the whole project sits in public_html
    |           and the site URL serves /product_images/... from that folder
    |           (not from /public/product_images).
    |
    */

    'storage' => env('PRODUCT_IMAGE_STORAGE', 'public'),

];
