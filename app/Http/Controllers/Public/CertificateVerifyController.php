<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HR\StaffCertificate;
use App\Models\Students\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public certificate verification on the issuing branch's host: shows only what is printed on the certificate. */
class CertificateVerifyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $certificate = null;
        $code = strtoupper(trim((string) $request->query('code')));

        if ($code !== '') {
            $request->validate(['code' => ['string', 'max:20', 'regex:/^[A-Za-z0-9]+$/']]);
            $certificate = Certificate::query()->where('verification_code', $code)->first()
                ?? StaffCertificate::query()->where('verification_code', $code)->first();
        }

        return view('public.certificate-verify', ['certificate' => $certificate, 'code' => $code]);
    }
}
