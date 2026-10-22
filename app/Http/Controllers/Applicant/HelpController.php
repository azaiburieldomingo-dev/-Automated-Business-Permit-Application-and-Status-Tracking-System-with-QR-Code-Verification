<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethods;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        return view('applicant.help', [
            'paymentMethods' => PaymentMethods::all(),
            'municipality' => config('permit.municipality'),
            'province' => config('permit.province'),
            'validMonths' => config('permit.valid_months'),
            'reminderDays' => config('permit.renewal.reminder_days', 30),
            'support' => config('permit.support'),
        ]);
    }
}
