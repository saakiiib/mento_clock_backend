@extends('layout')

@section('title', 'Compliance & training')

@section('content')
<div class="page-heading">
    <div>
        <span class="eyebrow">PEOPLE OPERATIONS</span>
        <h1>Compliance &amp; training</h1>
        <p>Keep employee evidence, review status, training and expiry dates together.</p>
    </div>
    <div class="actions">
        <span class="badge {{ $dueSoon ? 'warning' : 'neutral' }}">{{ $dueSoon }} due or expired</span>
    </div>
</div>

<div class="stats module-stats">
    <article class="card stat">
        <div class="stat-head">Employee documents</div>
        <div class="number">{{ $documents->count() }}</div>
        <small>{{ $documents->where('status', 'pending_review')->count() }} awaiting review</small>
    </article>
    <article class="card stat">
        <div class="stat-head">Training records</div>
        <div class="number">{{ $training->count() }}</div>
        <small>{{ $training->where('status', 'valid')->count() }} marked valid</small>
    </article>
    <article class="card stat">
        <div class="stat-head">Expiring soon</div>
        <div class="number">{{ $dueSoon }}</div>
        <small>Within the next 60 days</small>
    </article>
</div>

<div class="two equal">
    <section class="card">
        <div class="card-head">
            <div>
                <span class="eyebrow">PRIVATE RECORDS</span>
                <h2>Add employee document</h2>
                <p>Stored privately; only authorised client administrators can download it.</p>
            </div>
        </div>
        <form method="post" action="{{ url('/compliance/documents') }}" enctype="multipart/form-data">
            @csrf
            <label for="employee_id">Employee</label>
            <select name="employee_id" id="employee_id" required>
                <option value="">Select employee</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                        {{ $employee->name }}{{ $employee->employee_code ? ' · '.$employee->employee_code : '' }}
                    </option>
                @endforeach
            </select>
            <div class="form-two">
                <div>
                    <label for="category">Document category</label>
                    <select name="category" id="category" required>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="title">Document name</label>
                    <input name="title" id="title" maxlength="160" value="{{ old('title') }}" required>
                </div>
            </div>
            <div class="form-two">
                <div>
                    <label for="expiry_date">Expiry date</label>
                    <input type="date" name="expiry_date" id="expiry_date" value="{{ old('expiry_date') }}">
                </div>
                <div>
                    <label for="document">File (PDF, image or Office document; max 15 MB)</label>
                    <input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                </div>
            </div>
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
            <div class="form-actions"><button type="submit">Upload for review</button></div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <span class="eyebrow">LEARNING &amp; DEVELOPMENT</span>
                <h2>Add training record</h2>
                <p>Track required courses, providers and renewal dates.</p>
            </div>
        </div>
        <form method="post" action="{{ url('/compliance/training') }}">
            @csrf
            <label for="training_employee_id">Employee</label>
            <select name="employee_id" id="training_employee_id" required>
                <option value="">Select employee</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
            <label for="course_name">Course or certificate</label>
            <input name="course_name" id="course_name" maxlength="180" placeholder="e.g. First aid at work" required>
            <label for="provider">Training provider</label>
            <input name="provider" id="provider" maxlength="180">
            <div class="form-two">
                <div>
                    <label for="completed_on">Completed on</label>
                    <input type="date" name="completed_on" id="completed_on" max="{{ $today }}">
                </div>
                <div>
                    <label for="expires_on">Expires on</label>
                    <input type="date" name="expires_on" id="expires_on">
                </div>
            </div>
            <label for="training_status">Status</label>
            <select name="status" id="training_status">
                <option value="planned">Planned</option>
                <option value="in_progress">In progress</option>
                <option value="valid">Valid</option>
                <option value="expired">Expired</option>
                <option value="not_required">Not required</option>
            </select>
            <label for="training_notes">Notes</label>
            <textarea name="notes" id="training_notes" rows="2" maxlength="2000"></textarea>
            <div class="form-actions"><button type="submit">Add training record</button></div>
        </form>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <span class="eyebrow">DOCUMENT REGISTER</span>
            <h2>Employee documents</h2>
            <p>Review uploaded evidence and monitor expiry dates.</p>
        </div>
    </div>
    <div class="scroll">
        <table>
            <thead>
                <tr><th>Employee / document</th><th>Category</th><th>Expiry</th><th>Status</th><th>File / actions</th></tr>
            </thead>
            <tbody>
                @forelse ($documents as $doc)
                    <tr>
                        <td><strong>{{ $doc->employee_name }}</strong><small class="table-sub">{{ $doc->title }}</small></td>
                        <td>{{ $categories[$doc->category] ?? $doc->category }}</td>
                        <td>{{ $doc->expiry_date ? Carbon\Carbon::parse($doc->expiry_date)->format('d M Y') : 'No expiry recorded' }}</td>
                        <td>
                            <span class="badge {{ $doc->status === 'accepted' ? 'success' : ($doc->status === 'rejected' ? 'inactive' : 'warning') }}">
                                {{ str_replace('_', ' ', $doc->status) }}
                            </span>
                            @if ($doc->review_note)
                                <small class="table-sub">{{ $doc->review_note }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @if (app(\App\Services\WorkspacePermissions::class)->has(auth()->user(), 'compliance.documents.download'))
                                    <a href="{{ url('/compliance/documents/'.$doc->id.'/download') }}">Download</a>
                                @endif
                                @if ($doc->status === 'pending_review')
                                    <form method="post" action="{{ url('/compliance/documents/'.$doc->id.'/review') }}">
                                        @csrf
                                        <input type="hidden" name="status" value="accepted">
                                        <button class="secondary">Accept</button>
                                    </form>
                                    <details>
                                        <summary>Reject</summary>
                                        <form method="post" action="{{ url('/compliance/documents/'.$doc->id.'/review') }}">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <label>Reason</label>
                                            <input name="review_note" maxlength="1000" required>
                                            <button class="secondary">Save rejection</button>
                                        </form>
                                    </details>
                                @endif
                                <form method="post" action="{{ url('/compliance/documents/'.$doc->id.'/delete') }}" data-confirm="Permanently delete this private document?">
                                    @csrf
                                    <button class="secondary">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state"><h3>No employee documents yet.</h3><p>Upload right-to-work, contract or certificate records to start the register.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div><span class="eyebrow">RENEWALS</span><h2>Training and certification</h2></div>
    </div>
    <div class="scroll">
        <table>
            <thead>
                <tr><th>Employee</th><th>Course / certificate</th><th>Provider</th><th>Completed</th><th>Expires</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($training as $item)
                    <tr>
                        <td>{{ $item->employee_name }}</td>
                        <td>
                            <strong>{{ $item->course_name }}</strong>
                            @if ($item->notes)
                                <small class="table-sub">{{ $item->notes }}</small>
                            @endif
                        </td>
                        <td>{{ $item->provider ?: '—' }}</td>
                        <td>{{ $item->completed_on ? Carbon\Carbon::parse($item->completed_on)->format('d M Y') : '—' }}</td>
                        <td>{{ $item->expires_on ? Carbon\Carbon::parse($item->expires_on)->format('d M Y') : 'No expiry' }}</td>
                        <td>
                            <span class="badge {{ $item->status === 'valid' ? 'success' : ($item->status === 'expired' ? 'inactive' : 'neutral') }}">
                                {{ str_replace('_', ' ', $item->status) }}
                            </span>
                        </td>
                        <td>
                            <form method="post" action="{{ url('/compliance/training/'.$item->id.'/delete') }}" data-confirm="Delete this training record?">
                                @csrf
                                <button class="secondary">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><h3>No training recorded.</h3><p>Add required and completed training above.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<p class="caption">Expiry counts are operational reminders only. MentoClock does not determine legal right-to-work status or guarantee regulatory compliance.</p>
@endsection
