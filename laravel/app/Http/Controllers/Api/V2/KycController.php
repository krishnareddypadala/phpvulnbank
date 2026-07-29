<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Ported from src/fileupload.php.
 *
 * Lessons: VULN-04, VULN-12.
 */
class KycController extends Controller
{
    /**
     * POST /api/v2/kyc   (multipart)
     *
     * [VULN-04: Unrestricted file upload -> RCE] Intentional.
     * DO NOT ADD VALIDATION.
     *
     * The legacy page had an extension blocklist (php, jsp, asp) and a 2 MB
     * size check, and both were commented out, so the error array was always
     * empty and every upload was accepted. That is reproduced here.
     *
     * Laravel would normally stop this dead with one line:
     *
     *     $request->validate(['image' => 'required|image|max:2048']);
     *
     * and storing through Storage::putFile() would place the file outside the
     * document root with a generated name. Both are deliberately bypassed:
     * the file keeps its client-supplied name and is written into
     * public/images, which is inside the document root.
     *
     * The lesson only completes if the web server EXECUTES PHP in that
     * directory. Under the lab's Apache + mod_php container it does, which is
     * why upload.php becomes a webshell. This is called out in the Docker
     * configuration -- on a stock nginx setup the file would be served as
     * text and the RCE half of the lesson would quietly disappear.
     *
     * [VULN-12: Missing authentication] Intentional -- no session check, as
     * in the legacy page.
     *
     * Note the client-supplied filename is used verbatim, so this is also a
     * path traversal sink: a name like ../../routes/web.php escapes the
     * uploads directory entirely.
     */
    public function store(Request $request)
    {
        $file = $request->file('image');

        if ($file === null) {
            return response()->json(['error' => 'no file supplied'], 400);
        }

        $dir = public_path('images');

        if (! is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        // Client-controlled name, no sanitisation, no extension check.
        $name = $file->getClientOriginalName();

        $file->move($dir, $name);

        return response()->json([
            'status' => 'ok',
            'message' => 'Success',
            'stored_as' => 'images/'.$name,
            'url' => '/images/'.$name,
        ]);
    }
}
