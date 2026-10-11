<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrganisationProfileController
{
    private const DOCUMENT_TYPES = [
        'company_registration' => 'Company registration certificate',
        'employer_liability' => 'Employer liability insurance',
        'public_liability' => 'Public liability insurance',
        'premises_agreement' => 'Premises agreement or lease',
        'vat_certificate' => 'VAT certificate',
        'business_licence' => 'Business licence or certificate',
        'health_safety' => 'Health and safety certificate',
        'regulatory_certificate' => 'Regulatory body certificate',
        'audited_accounts' => 'Audited accounts',
        'other' => 'Other organisation document',
    ];

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $business = DB::table('businesses')->where('id', $businessId)->where('active', true)->firstOrFail();
        $profile = DB::table('client_profiles')->where('business_id', $businessId)->first();
        if (!$profile) {
            abort(404, 'Organisation profile is not available.');
        }

        $hours = $profile->operating_hours ? json_decode($profile->operating_hours, true) : [];
        $workingHours = [];
        foreach (self::DAYS as $day) {
            $workingHours[$day] = [
                'closed' => (bool) ($hours[$day]['closed'] ?? false),
                'opens_at' => $hours[$day]['opens_at'] ?? '09:00',
                'closes_at' => $hours[$day]['closes_at'] ?? '17:00',
            ];
        }
        $documents = DB::table('client_organisation_documents')->where('business_id', $businessId)->orderByDesc('created_at')->get();
        $logo = $documents->firstWhere('category', 'logo');
        $businessDocuments = $documents->where('category', '!=', 'logo');

        return view('company.profile', [
            'business' => $business,
            'profile' => $profile,
            'workingHours' => $workingHours,
            'documents' => $documents,
            'businessDocuments' => $businessDocuments,
            'logo' => $logo,
            'documentTypes' => self::DOCUMENT_TYPES,
            'days' => self::DAYS,
        ]);
    }

    public function update(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $values = $request->validate([
            'organisation_name' => 'required|string|max:180',
            'organisation_type' => ['required', Rule::in(['limited_company', 'sole_trader', 'partnership', 'charity', 'public_limited', 'other'])],
            'registration_number' => 'nullable|string|max:80',
            'registered_office' => 'nullable|string|max:500',
            'company_type' => 'nullable|string|max:100',
            'organisation_email' => 'nullable|email|max:255',
            'landline_number' => 'nullable|string|max:40',
            'website' => 'nullable|url:https|max:255',
            'company_status' => 'nullable|string|max:80',
            'incorporation_date' => 'nullable|date|before_or_equal:today',
            'sic_code' => 'nullable|string|max:20',
            'nature_of_business' => 'nullable|string|max:3000',
            'current_officer' => 'nullable|string|max:180',
            'psc_name' => 'nullable|string|max:180',
            'psc_dob' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'psc_nationality' => 'nullable|string|max:100',
            'psc_residence' => 'nullable|string|max:100',
            'psc_id_verification_status' => 'nullable|string|max:100',
            'psc_nature_of_control' => 'nullable|string|max:1500',
            'first_accounts_made_up_to' => 'nullable|date',
            'accounts_due_by' => 'nullable|date',
            'next_accounts_due' => 'nullable|date',
            'year_end_date' => 'nullable|date',
            'last_confirmation_statement_date' => 'nullable|date',
            'next_confirmation_statement_date' => 'nullable|date',
            'trading_name' => 'nullable|string|max:180',
            'trading_period' => ['nullable', Rule::in(['under_1_year', '1_to_5_years', 'over_5_years', 'not_applicable'])],
            'business_sector' => 'nullable|string|max:120',
            'trading_name_changed' => 'required|boolean',
            'trading_name_change_details' => 'nullable|string|max:2000',
            'authorised_first_name' => 'nullable|string|max:100',
            'authorised_last_name' => 'nullable|string|max:100',
            'authorised_designation' => 'nullable|string|max:120',
            'authorised_phone' => 'nullable|string|max:40',
            'authorised_email' => 'nullable|email|max:255',
            'key_contact_first_name' => 'nullable|string|max:100',
            'key_contact_last_name' => 'nullable|string|max:100',
            'key_contact_designation' => 'nullable|string|max:120',
            'key_contact_phone' => 'nullable|string|max:40',
            'key_contact_email' => 'nullable|email|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'address_line_3' => 'nullable|string|max:255',
            'city_county' => 'nullable|string|max:180',
            'postcode' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
        ]);

        $hoursRules = [];
        foreach (self::DAYS as $day) {
            $hoursRules["working_hours.$day.closed"] = 'required|boolean';
            $hoursRules["working_hours.$day.opens_at"] = 'nullable|date_format:H:i';
            $hoursRules["working_hours.$day.closes_at"] = 'nullable|date_format:H:i';
        }
        $hoursValues = $request->validate($hoursRules)['working_hours'] ?? [];
        foreach (self::DAYS as $day) {
            $closed = filter_var($hoursValues[$day]['closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $opens = $hoursValues[$day]['opens_at'] ?? null;
            $closes = $hoursValues[$day]['closes_at'] ?? null;
            if (!$closed && (!$opens || !$closes || $opens >= $closes)) {
                throw ValidationException::withMessages(["working_hours.$day.closes_at" => 'Enter a closing time after the opening time, or mark this day closed.']);
            }
            $hoursValues[$day] = ['closed' => $closed, 'opens_at' => $opens ?: '09:00', 'closes_at' => $closes ?: '17:00'];
        }

        $values['operating_hours'] = json_encode($hoursValues, JSON_THROW_ON_ERROR);
        $values['trading_name_changed'] = (bool) $values['trading_name_changed'];
        $values['updated_at'] = now('UTC');
        DB::transaction(function () use ($request, $businessId, $values) {
            DB::table('businesses')->where('id', $businessId)->lockForUpdate()->firstOrFail();
            DB::table('client_profiles')->where('business_id', $businessId)->update($values);
            DB::table('client_organisation_events')->insert([
                'business_id' => $businessId,
                'actor_id' => $request->user()->id,
                'action' => 'profile_updated',
                'details' => json_encode(['fields' => array_keys($values)], JSON_THROW_ON_ERROR),
                'created_at' => now('UTC'),
            ]);
        });

        return back()->with('status', 'Organisation profile saved.');
    }

    public function upload(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $values = $request->validate([
            'category' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:15360',
        ]);
        return $this->storeDocument($request, $businessId, $values['category'], $values['document']);
    }

    public function uploadLogo(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $values = $request->validate(['logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072']);
        return $this->storeDocument($request, $businessId, 'logo', $values['logo']);
    }

    private function storeDocument(Request $request, int $businessId, string $category, $file)
    {
        $path = $file->store("organisation/{$businessId}", 'local');
        $oldLogoPaths = [];
        try {
            DB::transaction(function () use ($request, $businessId, $category, $file, $path, &$oldLogoPaths) {
                if ($category === 'logo') {
                    $oldLogoPaths = DB::table('client_organisation_documents')->where('business_id', $businessId)
                        ->where('category', 'logo')->pluck('storage_path')->all();
                    DB::table('client_organisation_documents')->where('business_id', $businessId)->where('category', 'logo')->delete();
                }
                $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', basename($file->getClientOriginalName())) ?: 'organisation-document';
                DB::table('client_organisation_documents')->insert([
                    'business_id' => $businessId,
                    'uploaded_by' => $request->user()->id,
                    'category' => $category,
                    'original_name' => mb_substr($originalName, 0, 255),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
                DB::table('client_organisation_events')->insert([
                    'business_id' => $businessId,
                    'actor_id' => $request->user()->id,
                    'action' => $category === 'logo' ? 'logo_uploaded' : 'document_uploaded',
                    'details' => json_encode(['category' => $category], JSON_THROW_ON_ERROR),
                    'created_at' => now('UTC'),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        foreach ($oldLogoPaths as $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }
        return back()->with('status', $category === 'logo' ? 'Organisation logo updated.' : 'Organisation document uploaded.');
    }

    public function download(Request $request, int $documentId)
    {
        $document = DB::table('client_organisation_documents')
            ->where('business_id', (int) $request->user()->business_id)->where('id', $documentId)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
        return Storage::disk('local')->download($document->storage_path, $document->original_name);
    }

    public function viewLogo(Request $request, int $documentId): BinaryFileResponse
    {
        $document = DB::table('client_organisation_documents')->where('business_id', (int) $request->user()->business_id)
            ->where('id', $documentId)->where('category', 'logo')->firstOrFail();
        abort_unless(str_starts_with($document->mime_type, 'image/') && Storage::disk('local')->exists($document->storage_path), 404);
        return response()->file(Storage::disk('local')->path($document->storage_path), [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function delete(Request $request, int $documentId)
    {
        $businessId = (int) $request->user()->business_id;
        $document = DB::table('client_organisation_documents')->where('business_id', $businessId)->where('id', $documentId)->firstOrFail();
        DB::transaction(function () use ($request, $businessId, $document) {
            DB::table('client_organisation_documents')->where('business_id', $businessId)->where('id', $document->id)->delete();
            DB::table('client_organisation_events')->insert([
                'business_id' => $businessId,
                'actor_id' => $request->user()->id,
                'action' => 'document_deleted',
                'details' => json_encode(['category' => $document->category], JSON_THROW_ON_ERROR),
                'created_at' => now('UTC'),
            ]);
        });
        Storage::disk('local')->delete($document->storage_path);
        return back()->with('status', 'Organisation document removed.');
    }
}
