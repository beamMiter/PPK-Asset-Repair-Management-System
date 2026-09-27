<?php

return [
    /**
     * Max upload size in kilobytes (default 10 MB)
     */
    'max_kb' => env('UPLOAD_MAX_KB', 10240),

    /**
     * What may be attached to a repair request: pictures and PDF. Used with the `mimes:` rule, which judges a file by what it IS (its
     * content), not by the name it was given. SVG, HTML, XML, scripts and archives are NOT here on purpose: an SVG or HTML file opened
     * directly runs its script on the site's own address, as whoever opened it.
     */
    'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf'],

    /**
     * What may be attached to an ASSET: the same, and the documents that belong with equipment (manuals, data sheets, price lists).
     * A .docx / .xlsx / .pptx is judged by its content, and one that the server only recognises as a ZIP is refused: `zip` is not here.
     */
    'document_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],

    /**
     * The types a browser may show INSIDE a page when a private attachment is opened (everything else is sent as a download). A picture,
     * a PDF and plain text cannot run script; an SVG, an HTML page or an XML file can.
     */
    'inline_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif', 'application/pdf', 'text/plain'],
];
