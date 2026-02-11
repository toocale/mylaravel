<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DailyOeeReportMail;
use App\Mail\ShiftReportMail;
use App\Models\Machine;
use App\Models\Plant;
use App\Models\ReportSchedule;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ReportDeliveryController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Display the report delivery settings page.
     */
    public function index()
    {
        $schedules = ReportSchedule::with(['plant', 'line', 'machine', 'user'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        $plants = Plant::with(['lines.machines'])->get();

        return Inertia::render('Admin/ReportDelivery/Index', [
            'schedules' => $schedules,
            'plants' => $plants,
        ]);
    }

    /**
     * Store a new report schedule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'report_type' => 'required|in:shift,daily_oee,downtime,production',
            'frequency' => 'required|in:daily,weekly,monthly,shift_end',
            'send_time' => 'required|date_format:H:i',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'plant_id' => 'nullable|exists:plants,id',
            'line_id' => 'nullable|exists:lines,id',
            'machine_id' => 'nullable|exists:machines,id',
            'is_active' => 'boolean',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['send_time'] = $validated['send_time'] . ':00';

        ReportSchedule::create($validated);

        return redirect()->back()->with('success', 'Report schedule created successfully.');
    }

    /**
     * Update an existing report schedule.
     */
    public function update(Request $request, ReportSchedule $reportSchedule)
    {
        $this->authorize('update', $reportSchedule);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'report_type' => 'required|in:shift,daily_oee,downtime,production',
            'frequency' => 'required|in:daily,weekly,monthly,shift_end',
            'send_time' => 'required|date_format:H:i',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'plant_id' => 'nullable|exists:plants,id',
            'line_id' => 'nullable|exists:lines,id',
            'machine_id' => 'nullable|exists:machines,id',
            'is_active' => 'boolean',
        ]);

        $validated['send_time'] = $validated['send_time'] . ':00';

        $reportSchedule->update($validated);

        return redirect()->back()->with('success', 'Report schedule updated successfully.');
    }

    /**
     * Delete a report schedule.
     */
    public function destroy(ReportSchedule $reportSchedule)
    {
        $this->authorize('delete', $reportSchedule);

        $reportSchedule->delete();

        return redirect()->back()->with('success', 'Report schedule deleted successfully.');
    }

    /**
     * Toggle the active status of a schedule.
     */
    public function toggle(ReportSchedule $reportSchedule)
    {
        $this->authorize('update', $reportSchedule);

        $reportSchedule->update([
            'is_active' => !$reportSchedule->is_active
        ]);

        return redirect()->back()->with('success', 'Schedule status updated.');
    }

    /**
     * Send a test email for a schedule.
     */
    public function sendTest(Request $request, ReportSchedule $reportSchedule)
    {
        $this->authorize('update', $reportSchedule);

        $testEmail = $request->input('email', auth()->user()->email);

        try {
            $reportData = $this->reportService->generateReport($reportSchedule);
            
            $mail = match($reportSchedule->report_type) {
                'shift' => new ShiftReportMail(
                    $reportData,
                    $reportSchedule->machine?->name ?? 'All Machines',
                    now()->toDateString()
                ),
                default => new DailyOeeReportMail(
                    $reportData,
                    now()->toDateString(),
                    $reportSchedule->plant?->name
                ),
            };

            Mail::to($testEmail)->send($mail);

            return redirect()->back()->with('success', "Test email sent to {$testEmail}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    /**
     * Send an immediate report (manual trigger).
     */
    public function sendNow(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:shift,daily_oee,downtime,production',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'plant_id' => 'nullable|exists:plants,id',
            'line_id' => 'nullable|exists:lines,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        try {
            $schedule = new ReportSchedule($request->only([
                'report_type', 'plant_id', 'line_id', 'machine_id'
            ]));
            
            $reportData = $this->reportService->generateReport($schedule);
            
            $mail = match($request->report_type) {
                'shift' => new ShiftReportMail(
                    $reportData,
                    Machine::find($request->machine_id)?->name ?? 'All Machines',
                    now()->toDateString()
                ),
                default => new DailyOeeReportMail(
                    $reportData,
                    now()->toDateString(),
                    Plant::find($request->plant_id)?->name
                ),
            };

            foreach ($request->recipients as $recipient) {
                Mail::to($recipient)->queue($mail);
            }

            return redirect()->back()->with('success', 'Report emails queued for delivery.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send report: ' . $e->getMessage());
        }
    }
}
