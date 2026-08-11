<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsController extends Controller
{
    /**
     * SMS notification history (sms_logs).
     */
    public function index(): View
    {
        $patients = User::where('role', User::ROLE_PATIENT)->orderBy('name')->get();

        return view('sms.index', compact('patients'));
    }

    /**
     * JSON source for the SMS logs DataTable.
     */
    public function data(): JsonResponse
    {
        $logs = SmsLog::latest('sent_at')->get()->map(fn (SmsLog $l) => [
            'id' => $l->id,
            // e() escapes stored user input before client-side rendering.
            'recipient_phone' => e($l->recipient_phone),
            'message' => e($l->message),
            'status' => $l->status,
            'sent_at' => $l->sent_at?->format('M d, Y h:i A') ?? '—',
        ]);

        return response()->json(['data' => $logs->values()]);
    }

    /**
     * Manually send an SMS reminder to a patient (also recorded in sms_logs).
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_phone' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:160'],
        ]);

        app(SmsService::class)->send($data['recipient_phone'], $data['message']);

        return back()->with('success', 'SMS sent and logged.');
    }
}
