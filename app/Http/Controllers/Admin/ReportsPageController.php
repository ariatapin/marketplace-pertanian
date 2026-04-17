<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminReportsViewModelFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsPageController extends Controller
{
    public function __construct(
        protected AdminReportsViewModelFactory $reportsViewModelFactory
    ) {}

    public function __invoke(Request $request)
    {
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();
        $keyword = trim($request->string('q')->toString());

        $summary = [
            'total_reports' => 0,
            'pending_reports' => 0,
            'under_review_reports' => 0,
            'resolved_reports' => 0,
        ];

        $categoryOptions = collect();
        $reportRows = collect();
        $productReportRows = collect();

        if (Schema::hasTable('disputes') && Schema::hasTable('users')) {
            $reporterPhotoSelect = Schema::hasColumn('users', 'profile_photo_url')
                ? 'reporter.profile_photo_url as reporter_photo'
                : DB::raw('NULL as reporter_photo');

            $summary['total_reports'] = DB::table('disputes')->count();
            $summary['pending_reports'] = DB::table('disputes')->where('status', 'pending')->count();
            $summary['under_review_reports'] = DB::table('disputes')->where('status', 'under_review')->count();
            $summary['resolved_reports'] = DB::table('disputes')
                ->whereIn('status', ['resolved_buyer', 'resolved_seller'])
                ->count();

            $categoryOptions = DB::table('disputes')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->map(fn ($item) => (string) $item)
                ->values();

            $query = DB::table('disputes')
                ->join('users as reporter', 'reporter.id', '=', 'disputes.opened_by')
                ->leftJoin('users as handler', 'handler.id', '=', 'disputes.handled_by')
                ->select(
                    'disputes.id',
                    'disputes.order_id',
                    'disputes.category',
                    'disputes.status',
                    'disputes.description',
                    'disputes.created_at',
                    'reporter.id as reporter_id',
                    'reporter.name as reporter_name',
                    'reporter.email as reporter_email',
                    $reporterPhotoSelect,
                    'handler.name as handler_name'
                )
                ->orderByDesc('disputes.id');

            if (in_array($status, ['pending', 'under_review', 'resolved_buyer', 'resolved_seller', 'cancelled'], true)) {
                $query->where('disputes.status', $status);
            }

            if ($category !== '' && $categoryOptions->contains($category)) {
                $query->where('disputes.category', $category);
            }

            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('reporter.name', 'like', "%{$keyword}%")
                        ->orWhere('reporter.email', 'like', "%{$keyword}%")
                        ->orWhere('disputes.id', 'like', "%{$keyword}%");
                });
            }

            $reportRows = $query->paginate(20)->withQueryString();
        }

        if (Schema::hasTable('product_reports') && Schema::hasTable('users')) {
            $summary['total_reports'] += DB::table('product_reports')->count();
            $summary['pending_reports'] += DB::table('product_reports')->where('status', 'pending')->count();
            $summary['under_review_reports'] += DB::table('product_reports')->where('status', 'under_review')->count();
            $summary['resolved_reports'] += DB::table('product_reports')->where('status', 'resolved')->count();

            $productCategoryOptions = DB::table('product_reports')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->map(fn ($item) => (string) $item)
                ->values();

            $categoryOptions = $categoryOptions
                ->merge($productCategoryOptions)
                ->unique()
                ->values();

            $query = DB::table('product_reports')
                ->join('users as reporter', 'reporter.id', '=', 'product_reports.reporter_id')
                ->join('users as reported_user', 'reported_user.id', '=', 'product_reports.reported_user_id')
                ->leftJoin('users as handler', 'handler.id', '=', 'product_reports.handled_by')
                ->select([
                    'product_reports.id',
                    'product_reports.product_type',
                    'product_reports.product_id',
                    'product_reports.product_name',
                    'product_reports.category',
                    'product_reports.status',
                    'product_reports.description',
                    'product_reports.created_at',
                    'reporter.id as reporter_id',
                    'reporter.name as reporter_name',
                    'reporter.email as reporter_email',
                    DB::raw('NULL as reporter_photo'),
                    'handler.name as handler_name',
                    'reported_user.name as reported_user_name',
                ])
                ->orderByDesc('product_reports.id');

            if (in_array($status, ['pending', 'under_review', 'resolved', 'cancelled'], true)) {
                $query->where('product_reports.status', $status);
            }

            if ($category !== '' && $productCategoryOptions->contains($category)) {
                $query->where('product_reports.category', $category);
            }

            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('reporter.name', 'like', "%{$keyword}%")
                        ->orWhere('reporter.email', 'like', "%{$keyword}%")
                        ->orWhere('product_reports.id', 'like', "%{$keyword}%")
                        ->orWhere('product_reports.product_name', 'like', "%{$keyword}%")
                        ->orWhere('reported_user.name', 'like', "%{$keyword}%");
                });
            }

            $productReportRows = $query->paginate(20, ['*'], 'product_page')->withQueryString();
        }

        [
            'summary' => $summary,
            'reportRows' => $reportRows,
            'filters' => $filters,
            'categoryOptions' => $categoryOptions,
        ] = $this->reportsViewModelFactory->make(
            summary: $summary,
            reportRows: $reportRows,
            filters: [
                'status' => $status,
                'category' => $category,
                'q' => $keyword,
            ],
            categoryOptions: $categoryOptions,
        );

        return view('admin.reports', [
            'summary' => $summary,
            'reportRows' => $reportRows,
            'productReportRows' => $this->reportsViewModelFactory->makeProductRows($productReportRows),
            'categoryOptions' => $categoryOptions,
            'filters' => [
                'status' => $filters['status'],
                'category' => $filters['category'],
                'q' => $filters['q'],
            ],
        ]);
    }

    public function show(int $reportId)
    {
        abort_unless(Schema::hasTable('disputes') && Schema::hasTable('users'), 404);

        $reporterPhotoSelect = Schema::hasColumn('users', 'profile_photo_url')
            ? 'reporter.profile_photo_url as reporter_photo'
            : DB::raw('NULL as reporter_photo');

        $report = DB::table('disputes')
            ->join('users as reporter', 'reporter.id', '=', 'disputes.opened_by')
            ->leftJoin('users as handler', 'handler.id', '=', 'disputes.handled_by')
            ->select(
                'disputes.*',
                'reporter.id as reporter_id',
                'reporter.name as reporter_name',
                'reporter.email as reporter_email',
                $reporterPhotoSelect,
                'handler.name as handler_name'
            )
            ->where('disputes.id', $reportId)
            ->first();

        abort_if(! $report, 404);

        $refund = null;
        if (Schema::hasTable('refunds')) {
            $refund = DB::table('refunds')
                ->leftJoin('users as processor', 'processor.id', '=', 'refunds.processed_by')
                ->where('refunds.order_id', (int) ($report->order_id ?? 0))
                ->first([
                    'refunds.id',
                    'refunds.amount',
                    'refunds.status',
                    'refunds.reason',
                    'refunds.refund_reference',
                    'refunds.refund_proof_url',
                    'refunds.notes',
                    'refunds.processed_at',
                    'processor.name as processed_by_name',
                ]);
        }

        return view('admin.report-detail', [
            'report' => $this->reportsViewModelFactory->makeDetail($report),
            'refund' => $refund,
        ]);
    }

    public function showProductReport(int $productReportId)
    {
        abort_unless(Schema::hasTable('product_reports') && Schema::hasTable('users'), 404);

        $report = DB::table('product_reports')
            ->join('users as reporter', 'reporter.id', '=', 'product_reports.reporter_id')
            ->join('users as reported_user', 'reported_user.id', '=', 'product_reports.reported_user_id')
            ->leftJoin('users as handler', 'handler.id', '=', 'product_reports.handled_by')
            ->where('product_reports.id', $productReportId)
            ->first([
                'product_reports.*',
                'reporter.id as reporter_id',
                'reporter.name as reporter_name',
                'reporter.email as reporter_email',
                DB::raw('NULL as reporter_photo'),
                'handler.name as handler_name',
                'reported_user.name as reported_user_name',
                'reported_user.email as reported_user_email',
            ]);

        abort_if(! $report, 404);

        return view('admin.report-detail-product', [
            'report' => $this->reportsViewModelFactory->makeProductDetail($report),
        ]);
    }

    public function reviewProductReport(Request $request, int $productReportId)
    {
        abort_unless(Schema::hasTable('product_reports'), 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,under_review,resolved,cancelled'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'status' => strtolower((string) $data['status']),
            'resolution_notes' => trim((string) ($data['resolution_notes'] ?? '')) ?: null,
            'updated_at' => now(),
        ];

        if ($payload['status'] === 'pending') {
            $payload['handled_by'] = null;
            $payload['handled_at'] = null;
        } else {
            $payload['handled_by'] = (int) $request->user()->id;
            $payload['handled_at'] = now();
        }

        DB::table('product_reports')
            ->where('id', $productReportId)
            ->update($payload);

        return redirect()
            ->route('admin.modules.reports.products.show', ['productReportId' => $productReportId])
            ->with('status', "Laporan produk #{$productReportId} berhasil diperbarui.");
    }

}
