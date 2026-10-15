<?php

/*
|--------------------------------------------------------------------------
| PDF documents (mPDF) — certificates, ID cards, admit cards, mark sheets
|--------------------------------------------------------------------------
| Bangla text is shaped by mPDF's OpenType engine. mPDF ships with FreeSerif, which has Bangla glyphs, so
| documents work out of the box. For a cleaner look put a Bangla TrueType font into `font_dir` (storage/fonts)
| and name its files here (e.g. Noto Sans Bengali: NotoSansBengali-Regular.ttf / NotoSansBengali-Bold.ttf).
*/

return [
    'font_dir' => env('PDF_FONT_DIR', storage_path('fonts')),
    'temp_dir' => env('PDF_TEMP_DIR', storage_path('app/mpdf')),

    // Optional custom font family used for every document when the regular file exists in font_dir.
    'font' => [
        'regular' => env('PDF_FONT_REGULAR', 'NotoSansBengali-Regular.ttf'),
        'bold' => env('PDF_FONT_BOLD', 'NotoSansBengali-Bold.ttf'),
    ],
    'fallback_font' => 'freeserif',

    // ID card size (CR80 / ISO 7810 ID-1, portrait), in millimetres.
    'card' => ['width' => 54.0, 'height' => 85.6],
];
