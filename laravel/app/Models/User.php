<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A legacy `banktable` row: user, account, balance and feedback in one record.
 *
 * Laravel's session guard handles the plumbing (see Api\V2\AuthController), but
 * the credential check itself stays in its original flawed form, so this model
 * is never asked to verify a password.
 *
 * Note what is ABSENT here, because absence is the lesson:
 *
 * [VULN-66: Mass assignment] Laravel 13 protects every attribute unless the
 * class carries a #[Fillable([...])] attribute. This class deliberately has
 * none, and sets $guarded = [] so that every attribute is writable -- including
 * `admin` and `active`. Combined with registration calling create() on raw
 * request input, a caller can register themselves as an activated admin.
 * The fix is one line: #[Fillable(['username', 'password', 'email', 'mobile'])]
 *
 * [VULN-21 / VULN-69: Sensitive data exposure] There is no #[Hidden(['password'])]
 * either, so the MD5 hash, the admin flag and the balance are serialised into
 * every JSON response that returns a User. That is the "the UI doesn't display
 * it, so it isn't exposed" mistake, and it is why the API lessons matter.
 *
 * Do not "fix" either of these -- see docs/vulnerabilities.md
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'banktable';

    protected $primaryKey = 'acno';

    /**
     * The legacy table has no created_at/updated_at columns, and adding them
     * would turn every nine-column UNION SELECT payload into a ten-column error.
     */
    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = [];

    /**
     * No 'password' => 'hashed' cast. The scaffold ships that cast so Eloquent
     * bcrypts on assignment; keeping it would silently upgrade the deliberately
     * weak MD5 storage and delete the offline-cracking lesson (VULN-15).
     */
    protected function casts(): array
    {
        return [];
    }

    public function isAdmin(): bool
    {
        return (int) $this->admin > 0;
    }
}
