<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminModeRequestsViewModelFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModeRequestsPageController extends Controller
{
    public function __construct(
        protected AdminModeRequestsViewModelFactory $modeRequestsViewModelFactory
    ) {}

    public function __invoke(Request $request)
    {
        $status = $request->string('status')->toString();
        $mode = $request->string('mode')->toString();
        $keyword = trim($request->string('q')->toString());
        $mitraStatus = $request->string('mitra_status')->toString();
        $mitraKeyword = trim($request->string('mitra_q')->toString());

        $rows = collect();
        $mitraRows = collect();

        if (Schema::hasTable('consumer_profiles') && Schema::hasTable('users')) {
            $query = DB::table('consumer_profiles')
                ->join('users', 'users.id', '=', 'consumer_profiles.user_id')
                ->select(
                    'users.id as user_id',
                    'users.name',
                    'users.email',
                    'consumer_profiles.mode',
                    'consumer_profiles.mode_status',
                    'consumer_profiles.requested_mode',
                    'consumer_profiles.updated_at'
                )
                ->orderByDesc('consumer_profiles.updated_at');

            if (in_array($status, ['pending', 'approved', 'rejected', 'none'], true)) {
                $query->where('consumer_profiles.mode_status', $status);
            }

            if (in_array($mode, ['affiliate', 'farmer_seller'], true)) {
                $query->where('consumer_profiles.requested_mode', $mode);
            }

            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('users.name', 'like', "%{$keyword}%")
                        ->orWhere('users.email', 'like', "%{$keyword}%");
                });
            }

            $rows = $query->paginate(15)->withQueryString();
        }

        if (Schema::hasTable('mitra_applications') && Schema::hasTable('users')) {
            $hasSubmittedAt = Schema::hasColumn('mitra_applications', 'submitted_at');
            $mitraQuery = DB::table('mitra_applications')
                ->join('users', 'users.id', '=', 'mitra_applications.user_id')
                ->leftJoin('users as decider', 'decider.id', '=', 'mitra_applications.decided_by')
                ->select(
                    'mitra_applications.id',
                    'mitra_applications.user_id',
                    'mitra_applications.full_name',
                    'mitra_applications.email',
                    'mitra_applications.region_id',
                    'mitra_applications.status',
                    'mitra_applications.notes',
                    'mitra_applications.warehouse_address',
                    'mitra_applications.products_managed',
                    'mitra_applications.warehouse_capacity',
                    'mitra_applications.ktp_url',
                    'mitra_applications.npwp_url',
                    'mitra_applications.nib_url',
                    'mitra_applications.warehouse_building_photo_url',
                    'mitra_applications.special_certification_url',
                    'mitra_applications.decided_at',
                    'mitra_applications.updated_at',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.role as user_role',
                    'decider.name as decided_by_name'
                )
                ->orderByRaw("CASE mitra_applications.status
                    WHEN 'pending' THEN 0
                    WHEN 'draft' THEN 1
                    WHEN 'rejected' THEN 2
                    WHEN 'approved' THEN 3
                    ELSE 4 END")
                ->orderByDesc('mitra_applications.updated_at');

            if ($hasSubmittedAt) {
                $mitraQuery->addSelect('mitra_applications.submitted_at');
            } else {
                $mitraQuery->addSelect(DB::raw('NULL as submitted_at'));
            }

            if (in_array($mitraStatus, ['draft', 'pending', 'approved', 'rejected'], true)) {
                $mitraQuery->where('mitra_applications.status', $mitraStatus);
            }

            if ($mitraKeyword !== '') {
                $mitraQuery->where(function ($sub) use ($mitraKeyword) {
                    $sub->where('users.name', 'like', "%{$mitraKeyword}%")
                        ->orWhere('users.email', 'like', "%{$mitraKeyword}%")
                        ->orWhere('mitra_applications.full_name', 'like', "%{$mitraKeyword}%");
                });
            }

            $mitraRows = $mitraQuery
                ->paginate(perPage: 10, pageName: 'mitra_page')
                ->withQueryString();
        }

        [
            'rows' => $rows,
            'mitraRows' => $mitraRows,
        ] = $this->modeRequestsViewModelFactory->make(
            rows: $rows,
            mitraRows: $mitraRows
        );

        return view('admin.mode-requests', [
            'rows' => $rows,
            'mitraRows' => $mitraRows,
            'filters' => [
                'status' => $status,
                'mode' => $mode,
                'q' => $keyword,
                'mitra_status' => $mitraStatus,
                'mitra_q' => $mitraKeyword,
            ],
        ]);
    }
}
