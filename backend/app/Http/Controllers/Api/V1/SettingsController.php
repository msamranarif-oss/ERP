<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SmsSettings;
use App\Models\EmailSettings;
use App\Models\LabelTemplate;
use App\Models\InstallmentPlanTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    private function tid(): int { return auth()->user()->tenant_id; }

    // ── SMS Settings ────────────────────────────────────────────────────

    public function smsSettings(): JsonResponse
    {
        $s = SmsSettings::firstOrNew(['tenant_id' => $this->tid()]);
        return response()->json(['success' => true, 'data' => $s]);
    }

    public function updateSmsSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway'   => 'required|in:twilio,africas_talking,vonage,local_gateway',
            'api_key'   => 'nullable|string',
            'api_secret'=> 'nullable|string',
            'sender_id' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);
        $s = SmsSettings::updateOrCreate(['tenant_id' => $this->tid()], $data);
        return response()->json(['success' => true, 'data' => $s, 'message' => 'SMS settings saved.']);
    }

    public function testSms(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);
        // Placeholder — actual driver dispatch would read SmsSettings and call gateway SDK
        return response()->json(['success' => true, 'message' => 'Test SMS dispatched (gateway integration required).']);
    }

    // ── Email Settings ────────────────────────────────────────────────

    public function emailSettings(): JsonResponse
    {
        $s = EmailSettings::firstOrNew(['tenant_id' => $this->tid()]);
        return response()->json(['success' => true, 'data' => $s]);
    }

    public function updateEmailSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver'       => 'required|in:smtp,sendgrid,mailgun',
            'host'         => 'nullable|string',
            'port'         => 'nullable|integer',
            'username'     => 'nullable|string',
            'password'     => 'nullable|string',
            'encryption'   => 'nullable|in:tls,ssl,',
            'from_address' => 'nullable|email',
            'from_name'    => 'nullable|string|max:100',
            'is_active'    => 'boolean',
        ]);
        $s = EmailSettings::updateOrCreate(['tenant_id' => $this->tid()], $data);
        return response()->json(['success' => true, 'data' => $s, 'message' => 'Email settings saved.']);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $settings = EmailSettings::where('tenant_id', $this->tid())->first();
        if (!$settings || !$settings->is_active) {
            return response()->json(['success' => false, 'message' => 'Email settings not configured.'], 422);
        }
        // Dynamically override mailer config
        config(['mail.mailers.smtp.host'       => $settings->host,
                'mail.mailers.smtp.port'       => $settings->port,
                'mail.mailers.smtp.username'   => $settings->username,
                'mail.mailers.smtp.password'   => $settings->password,
                'mail.mailers.smtp.encryption' => $settings->encryption,
                'mail.from.address'            => $settings->from_address,
                'mail.from.name'               => $settings->from_name]);

        try {
            Mail::raw('This is a test email from your ERP system.', fn($m) => $m->to($request->email)->subject('ERP Test Email'));
            return response()->json(['success' => true, 'message' => 'Test email sent.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ── Label Templates ───────────────────────────────────────────────

    public function labelTemplates(): JsonResponse
    {
        $templates = LabelTemplate::where('tenant_id', $this->tid())->get();
        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function storeLabelTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'size'       => 'required|in:58mm,80mm,a4',
            'fields'     => 'required|array',
            'fields.*'   => 'string',
            'is_default' => 'boolean',
        ]);
        if ($request->boolean('is_default')) {
            LabelTemplate::where('tenant_id', $this->tid())->update(['is_default' => false]);
        }
        $data['tenant_id'] = $this->tid();
        $t = LabelTemplate::create($data);
        return response()->json(['success' => true, 'data' => $t], 201);
    }

    public function updateLabelTemplate(Request $request, LabelTemplate $labelTemplate): JsonResponse
    {
        abort_if($labelTemplate->tenant_id !== $this->tid(), 403);
        if ($request->boolean('is_default')) {
            LabelTemplate::where('tenant_id', $this->tid())->update(['is_default' => false]);
        }
        $labelTemplate->update($request->validate([
            'name'       => 'sometimes|string|max:100',
            'size'       => 'sometimes|in:58mm,80mm,a4',
            'fields'     => 'sometimes|array',
            'is_default' => 'boolean',
        ]));
        return response()->json(['success' => true, 'data' => $labelTemplate->fresh()]);
    }

    public function destroyLabelTemplate(LabelTemplate $labelTemplate): JsonResponse
    {
        abort_if($labelTemplate->tenant_id !== $this->tid(), 403);
        $labelTemplate->delete();
        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }

    // ── Installment Plan Templates ────────────────────────────────────

    public function installmentPlanTemplates(): JsonResponse
    {
        $templates = InstallmentPlanTemplate::where('tenant_id', $this->tid())->where('is_active', true)->get();
        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function storeInstallmentPlanTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'frequency'     => 'required|in:weekly,biweekly,monthly,quarterly',
            'term'          => 'required|integer|min:1',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'is_default'    => 'boolean',
        ]);
        if ($request->boolean('is_default')) {
            InstallmentPlanTemplate::where('tenant_id', $this->tid())->update(['is_default' => false]);
        }
        $data['tenant_id'] = $this->tid();
        $t = InstallmentPlanTemplate::create($data);
        return response()->json(['success' => true, 'data' => $t], 201);
    }

    public function updateInstallmentPlanTemplate(Request $request, InstallmentPlanTemplate $installmentPlanTemplate): JsonResponse
    {
        abort_if($installmentPlanTemplate->tenant_id !== $this->tid(), 403);
        $installmentPlanTemplate->update($request->validate([
            'name'          => 'sometimes|string|max:100',
            'frequency'     => 'sometimes|in:weekly,biweekly,monthly,quarterly',
            'term'          => 'sometimes|integer|min:1',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'is_default'    => 'boolean',
            'is_active'     => 'boolean',
        ]));
        return response()->json(['success' => true, 'data' => $installmentPlanTemplate->fresh()]);
    }

    public function destroyInstallmentPlanTemplate(InstallmentPlanTemplate $installmentPlanTemplate): JsonResponse
    {
        abort_if($installmentPlanTemplate->tenant_id !== $this->tid(), 403);
        $installmentPlanTemplate->delete();
        return response()->json(['success' => true, 'message' => 'Template deleted.']);
    }
}
