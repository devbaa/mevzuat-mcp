<?php

declare(strict_types=1);

return [
    'mevzuat_gov' => [
        'name' => 'mevzuat.gov.tr',
        'base_url' => 'https://www.mevzuat.gov.tr',
        'endpoints' => [
            'search' => '/Anasayfa/MevzuatDatatable',
            'content_doc' => '/MevzuatMetin/%d.%s.%s.doc',
            'content_pdf' => '/MevzuatMetin/%d.%s.%s.pdf',
            'content_html' => '/anasayfa/MevzuatFihristDetayIframe?MevzuatTur=%d&MevzuatNo=%s&MevzuatTertip=%s',
        ],
    ],
    'bedesten' => [
        'name' => 'bedesten.adalet.gov.tr',
        'base_url' => 'https://bedesten.adalet.gov.tr/mevzuat',
        'endpoints' => [
            'search_documents' => '/searchDocuments',
            'get_document_content' => '/getDocumentContent',
            'get_gerekce_content' => '/getGerekceContent',
            'get_article_tree' => '/mevzuatMaddeTree',
        ],
    ],
];
