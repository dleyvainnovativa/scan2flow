<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Admin SDK credentials
    |--------------------------------------------------------------------------
    | Path to the service-account JSON downloaded from the Firebase console
    | (Project settings → Service accounts → Generate new private key).
    |
    | SECURITY: keep this file OUTSIDE the public web root. On Hostinger, store
    | it above public_html and point FIREBASE_CREDENTIALS at the absolute path,
    | or keep it in storage/ (which is not web-accessible). Never commit it.
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase/service-account.json')),

    /*
    |--------------------------------------------------------------------------
    | Public web config (safe to expose to the browser)
    |--------------------------------------------------------------------------
    | These are injected into the login page so the Firebase JS SDK can run the
    | email/password sign-in on the client. The apiKey here is NOT a secret.
    */
    'web' => [
        'apiKey'            => env('FIREBASE_WEB_API_KEY'),
        'authDomain'        => env('FIREBASE_AUTH_DOMAIN'),
        'projectId'         => env('FIREBASE_PROJECT_ID'),
        'storageBucket'     => env('FIREBASE_STORAGE_BUCKET'),
        'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'appId'             => env('FIREBASE_APP_ID'),
    ],

];
