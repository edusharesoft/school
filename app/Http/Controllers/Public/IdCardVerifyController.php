<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Documents\IdCard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public check of an ID card (the QR on the back opens this page): shows only whether the card is valid, the holder's name and role. */
class IdCardVerifyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $card = null;
        $code = strtoupper(trim((string) $request->query('code')));

        if ($code !== '') {
            $request->validate(['code' => ['string', 'max:20', 'regex:/^[A-Za-z0-9]+$/']]);
            $card = IdCard::query()->with(['student:id,name,student_code', 'employee:id,name,employee_code,designation_id', 'employee.designation:id,name'])->where('verification_code', $code)->first();
        }

        return view('public.id-card-verify', ['card' => $card, 'code' => $code, 'branch' => tenant()->branch()]);
    }
}
