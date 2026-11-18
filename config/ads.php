<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 자체 배너 광고
    |--------------------------------------------------------------------------
    |
    | 메인 페이지(/transactions) 배너 슬롯 설정. 노출/클릭 이벤트는
    | services.tracker.endpoint 로 전송된다.
    |
    */

    'main_banner' => [
        'enabled' => (bool) env('AD_MAIN_BANNER_ENABLED', false),
        'id' => env('AD_MAIN_BANNER_ID', 'main-top-1'),
        'image' => env('AD_MAIN_BANNER_IMAGE'),
        'link' => env('AD_MAIN_BANNER_LINK'),
        'alt' => env('AD_MAIN_BANNER_ALT', '광고'),
    ],

];
