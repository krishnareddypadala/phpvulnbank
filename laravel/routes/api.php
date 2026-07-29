<?php

use App\Http\Controllers\Api\V2\AccountController;
use App\Http\Controllers\Api\V2\AdminController;
use App\Http\Controllers\Api\V2\AuthController;
use App\Http\Controllers\Api\V2\FeedbackController;
use App\Http\Controllers\Api\V2\KycController;
use App\Http\Controllers\Api\V2\OpenApiController;
use App\Http\Controllers\Api\V2\RegisterController;
use App\Http\Controllers\Api\V2\TransferController;
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
| NOTE THE ABSENCE OF `throttle` ON THIS ENTIRE GROUP. Laravel commonly applies
| throttle:api here; leaving it off is VULN-25 and keeps the password guessing
| and MD5 cracking exercises workable.
|
| Endpoint map: docs/api-refactor.md §6.   Lessons: docs/vulnerabilities.md.
|
*/

Route::prefix('v2')->group(function (): void {

    // ---- Machine-readable API description --------------------------------
    // Unauthenticated on purpose: students need the map, and a public endpoint
    // inventory is itself worth noticing (OWASP API9).
    Route::get('openapi.json', OpenApiController::class);

    // ---- Authentication --------------------------------------------------
    // VULN-01 SQLi bypass, VULN-02 troy backdoor (unauth RCE), VULN-14
    // reflected XSS, VULN-24 enumeration, VULN-25 no rate limit.
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // ---- Registration ----------------------------------------------------
    // VULN-06 SQLi, VULN-09 XXE, VULN-66 mass assignment, VULN-67 content
    // type confusion. Unauthenticated by design -- registration precedes
    // having an account.
    Route::post('register/xml', [RegisterController::class, 'registerXml']);
    Route::post('register/json', [RegisterController::class, 'registerJson']);

    // ---- Accounts --------------------------------------------------------
    // VULN-05 numeric SQLi, VULN-06 second-order SQLi, VULN-11 IDOR/BOLA,
    // VULN-13 stored XSS, VULN-21 password hash disclosure.
    Route::get('accounts/me', [AccountController::class, 'me']);
    Route::get('accounts/{acno}/safe', [AccountController::class, 'lookupSafe']);
    Route::get('accounts/{acno}', [AccountController::class, 'lookup'])
        // The default {acno} pattern would reject an injection payload before
        // it ever reached the controller. Laravel's implicit route-parameter
        // constraints are a real defence here, and this widens it deliberately
        // so the payload gets through. Same class of opt-out as an MCP tool
        // schema -- see docs/mcp-design.md §5.
        ->where('acno', '.*');

    // ---- Transfers -------------------------------------------------------
    // VULN-06 SQLi, VULN-10 CSRF, VULN-11 IDOR, VULN-16 negative amounts,
    // VULN-17 race, VULN-18 client-side validation, VULN-35 replay.
    Route::post('transfers', [TransferController::class, 'store']);
    Route::post('transfers/protected', [TransferController::class, 'storeProtected']);

    // ---- Feedback --------------------------------------------------------
    // VULN-06 SQLi, VULN-12 missing authn, VULN-13 stored XSS.
    Route::put('feedback/me', [FeedbackController::class, 'update']);
    Route::get('feedback', [FeedbackController::class, 'index']);

    // ---- KYC upload ------------------------------------------------------
    // VULN-04 unrestricted upload -> RCE, VULN-12 missing authn.
    Route::post('kyc', [KycController::class, 'store']);

    // ---- Admin -----------------------------------------------------------
    // VULN-07 path traversal, VULN-12 missing function level access control,
    // VULN-13 stored XSS, VULN-14 reflected XSS.
    Route::get('admin/pending-activations', [AdminController::class, 'pendingActivations']);
    Route::post('admin/activate', [AdminController::class, 'activate']);
    Route::get('admin/kyc', [AdminController::class, 'kycIndex']);
    Route::get('admin/kyc/download', [AdminController::class, 'kycDownload']);

    // ---- Deliberate webshells and fetch tools ----------------------------
    // VULN-03 command injection, VULN-08 SSRF. Unauthenticated by design.
    Route::match(['get', 'post'], 'tools/exec', [\App\Http\Controllers\Api\V2\UtilityController::class, 'exec']);
    Route::get('tools/fetch', [\App\Http\Controllers\Api\V2\UtilityController::class, 'fetch']);
});
