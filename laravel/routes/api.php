<?php

use App\Http\Controllers\Api\V2\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PHPVulnBank API v2
|--------------------------------------------------------------------------
|
| The system of record. All business logic -- and therefore every server-side
| vulnerability -- lives behind these endpoints. The Blade client in web.php is
| a thin shell that calls them.
|
| NOTE THE ABSENCE OF `throttle`. Laravel commonly applies throttle:api to this
| group; leaving it off is VULN-25 (no rate limiting) and keeps the password
| guessing and OTP brute-force exercises workable.
|
| See docs/api-refactor.md for the endpoint map and docs/vulnerabilities.md for
| the lesson behind each one.
|
*/

Route::prefix('v2')->group(function (): void {

    // ---- Authentication -------------------------------------------------
    // VULN-01 SQLi auth bypass, VULN-02 troy backdoor (unauthenticated RCE),
    // VULN-14 reflected XSS, VULN-24 user enumeration, VULN-25 no rate limit.
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
});
