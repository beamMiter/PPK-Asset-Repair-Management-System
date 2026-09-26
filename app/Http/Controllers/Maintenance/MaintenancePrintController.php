<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaintenanceRequest as MR;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class MaintenancePrintController extends Controller
{
    public function printWorkOrder(Request $request, MR $req)
    {
        Gate::authorize('view', $req);

        // exactly what resources/views/maintenance/pdf/work_order.blade.php reads
        $req->loadMissing([
            'department',
            'asset',
            'reporter:id,name,email',
            'technician:id,name',
            'operationLog',
            'assignments',
            'workers:id,name,role',
        ]);

        $hospital = [
            'name_th'  => 'โรงพยาบาลพระปกเกล้า',
            'name_en'  => 'PHRAPOKKLAO HOSPITAL',
            'subtitle' => 'Maintenance Work Order',
            'logo'     => public_path('images/logoppk1.png'),
        ];

        $fileName = sprintf('maintenance-work-order-%s.pdf', $req->request_no ?? $req->id);

        $paperData = [
            'req'      => $req,
            'hospital' => $hospital,
            'print_at' => now(),
            'user'     => $request->user(),
        ];

        if ($request->has('html')) {
            return view('maintenance.pdf.work_order', $paperData);
        }

        $pdf = Pdf::loadView('maintenance.pdf.work_order', $paperData)
            ->setPaper('A4', 'portrait')
            ->setWarnings(false)
            // merge with config/dompdf.php — without `true` the config (font_dir, font_cache, ...) is thrown away
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
                'defaultFont'          => 'Sarabun',
                'chroot'               => public_path(),
            ], true);

        return $pdf->stream($fileName);
    }
}
