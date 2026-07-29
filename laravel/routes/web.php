<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Browser client
|--------------------------------------------------------------------------
|
| Thin Blade shells only. They hold no business logic -- every one of them
| calls the v2 API in routes/api.php and renders the result.
|
| The client is deliberately kept minimal and framework-free. A React or Vue
| client would auto-escape by default, which would mean fighting the XSS
| lessons back INTO it, and a build step would obscure the innerHTML sinks
| that those lessons now depend on. See docs/api-refactor.md §10.2.
|
*/

Route::view('/', 'auth.login');
Route::view('/profile', 'account.profile');
Route::view('/transfer', 'transfer.form');
Route::view('/feedback', 'feedback.index');
Route::view('/admin', 'admin.index');
Route::view('/lookup', 'account.lookup');
Route::view('/kyc', 'kyc.upload');
Route::view('/docs', 'docs');

Route::get('/register/{mode}', fn (string $mode) => view('auth.register', [
    'mode' => in_array($mode, ['xml', 'json'], true) ? $mode : 'json',
]));

/*
|--------------------------------------------------------------------------
| Legacy URL compatibility
|--------------------------------------------------------------------------
|
| The archived DAST report, the Jenkins/ZAP pipeline and every published
| exploit write-up for this lab reference the original .php URLs. Keeping them
| resolvable means existing material does not break on the port.
|
*/

$legacy = [
    'login.php' => '/',
    'profile.php' => '/profile',
    'transfer.php' => '/transfer',
    'transfer_csrftoken.php' => '/transfer',
    'feedback.php' => '/feedback',
    'feedback_user.php' => '/feedback',
    'feedback_admin.php' => '/feedback',
    'displaydata.php' => '/lookup',
    'displaydata_safe.php' => '/lookup',
    'fileupload.php' => '/kyc',
    'validatekyc.php' => '/admin',
    'activateform.php' => '/admin',
    'logout.php' => '/',
];

foreach ($legacy as $old => $new) {
    Route::redirect('/'.$old, $new, 301);
}
