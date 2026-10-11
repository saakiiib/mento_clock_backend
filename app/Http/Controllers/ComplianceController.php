<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ComplianceController
{
    private const CATEGORIES = [
        'right_to_work' => 'Right to work', 'passport' => 'Passport', 'visa_brp' => 'Visa / BRP',
        'cos' => 'Certificate of Sponsorship', 'contract' => 'Employment contract',
        'qualification' => 'Qualification', 'insurance' => 'Insurance', 'other' => 'Other',
    ];

    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $employees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->orderBy('name')->get(['id', 'name', 'employee_code', 'active']);
        $documents = DB::table('employee_documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.business_id', $businessId)->orderByRaw("CASE WHEN d.expiry_date IS NULL THEN 1 ELSE 0 END")
            ->orderBy('d.expiry_date')->orderByDesc('d.created_at')
            ->select('d.*', 'u.name as employee_name', 'u.employee_code')->get();
        $training = DB::table('employee_training_records as t')->join('users as u', 'u.id', '=', 't.user_id')
            ->where('t.business_id', $businessId)->orderByRaw("CASE WHEN t.expires_on IS NULL THEN 1 ELSE 0 END")
            ->orderBy('t.expires_on')->orderBy('u.name')
            ->select('t.*', 'u.name as employee_name', 'u.employee_code')->get();
        $timezone = DB::table('businesses')->where('id', $businessId)->value('timezone') ?: 'UTC';
        $localNow = now($timezone);
        $today = $localNow->toDateString();
        $cutoff = $localNow->copy()->addDays(60)->toDateString();
        $dueSoon = $documents->filter(fn ($row) => $row->expiry_date && $row->expiry_date <= $cutoff && $row->status !== 'rejected')->count()
            + $training->filter(fn ($row) => $row->expires_on && $row->expires_on <= $cutoff && in_array($row->status, ['valid', 'expired'], true))->count();
        return view('compliance.index', [
            'employees' => $employees, 'documents' => $documents, 'training' => $training,
            'categories' => self::CATEGORIES, 'dueSoon' => $dueSoon, 'today' => $today,
        ]);
    }

    public function upload(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $values = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)->where('role', 'employee')],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))], 'title' => 'required|string|max:160',
            'expiry_date' => 'nullable|date', 'notes' => 'nullable|string|max:2000',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360',
        ]);
        $file = $values['document'];
        $path = $file->store("employee-documents/{$businessId}/{$values['employee_id']}", 'local');
        try {
            DB::transaction(function () use ($request, $businessId, $values, $file, $path) {
                $id = DB::table('employee_documents')->insertGetId([
                    'business_id' => $businessId, 'user_id' => $values['employee_id'], 'category' => $values['category'],
                    'title' => $values['title'], 'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', basename($file->getClientOriginalName())) ?: 'employee-document', 0, 255),
                    'storage_path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(),
                    'expiry_date' => $values['expiry_date'] ?? null, 'notes' => $values['notes'] ?? null,
                    'status' => 'pending_review', 'uploaded_by' => $request->user()->id, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
                ]);
                $this->event($businessId, $request->user()->id, 'employee_document', $id, 'uploaded', ['category' => $values['category']]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return back()->with('status', 'Employee document uploaded for review.');
    }

    public function download(Request $request, int $documentId)
    {
        $document = DB::table('employee_documents')->where('business_id', (int) $request->user()->business_id)->where('id', $documentId)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
        $this->event((int) $request->user()->business_id, $request->user()->id, 'employee_document', $documentId, 'downloaded');
        return Storage::disk('local')->download($document->storage_path, $document->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, int $documentId)
    {
        $businessId = (int) $request->user()->business_id;
        $document = DB::table('employee_documents')->where('business_id', $businessId)->where('id', $documentId)->firstOrFail();
        $values = $request->validate(['status' => ['required', Rule::in(['accepted', 'rejected', 'pending_review'])], 'review_note' => 'nullable|string|max:1000']);
        DB::table('employee_documents')->where('business_id', $businessId)->where('id', $documentId)->update([
            'status' => $values['status'], 'review_note' => $values['review_note'] ?? null,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $this->event($businessId, $request->user()->id, 'employee_document', $documentId, 'reviewed', ['status' => $values['status']]);
        return back()->with('status', 'Document review saved.');
    }

    public function deleteDocument(Request $request, int $documentId)
    {
        $businessId = (int) $request->user()->business_id;
        $document = DB::table('employee_documents')->where('business_id', $businessId)->where('id', $documentId)->firstOrFail();
        DB::table('employee_documents')->where('business_id', $businessId)->where('id', $documentId)->delete();
        Storage::disk('local')->delete($document->storage_path);
        $this->event($businessId, $request->user()->id, 'employee_document', $documentId, 'deleted');
        return back()->with('status', 'Employee document deleted.');
    }

    public function saveTraining(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $values = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)->where('role', 'employee')],
            'course_name' => 'required|string|max:180', 'provider' => 'nullable|string|max:180',
            'completed_on' => 'nullable|date|before_or_equal:today', 'expires_on' => 'nullable|date|after_or_equal:completed_on',
            'status' => ['required', Rule::in(['planned', 'in_progress', 'valid', 'expired', 'not_required'])], 'notes' => 'nullable|string|max:2000',
        ]);
        $id = DB::table('employee_training_records')->insertGetId([
            'business_id' => $businessId, 'user_id' => $values['employee_id'], 'course_name' => $values['course_name'],
            'provider' => $values['provider'] ?? null, 'completed_on' => $values['completed_on'] ?? null,
            'expires_on' => $values['expires_on'] ?? null, 'status' => $values['status'], 'notes' => $values['notes'] ?? null,
            'created_by' => $request->user()->id, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $this->event($businessId, $request->user()->id, 'training_record', $id, 'created');
        return back()->with('status', 'Training record added.');
    }

    public function deleteTraining(Request $request, int $trainingId)
    {
        $businessId = (int) $request->user()->business_id;
        DB::table('employee_training_records')->where('business_id', $businessId)->where('id', $trainingId)->firstOrFail();
        DB::table('employee_training_records')->where('business_id', $businessId)->where('id', $trainingId)->delete();
        $this->event($businessId, $request->user()->id, 'training_record', $trainingId, 'deleted');
        return back()->with('status', 'Training record deleted.');
    }

    private function event(int $businessId, ?int $actorId, string $type, ?int $subjectId, string $action, array $details = []): void
    {
        DB::table('hr_module_events')->insert([
            'business_id' => $businessId, 'actor_id' => $actorId, 'subject_type' => $type, 'subject_id' => $subjectId,
            'action' => $action, 'details' => $details ? json_encode($details, JSON_THROW_ON_ERROR) : null, 'created_at' => now('UTC'),
        ]);
    }
}
