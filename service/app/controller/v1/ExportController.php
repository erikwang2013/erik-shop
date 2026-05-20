<?php
namespace app\controller\v1;
use app\common\ApiResponse;
use app\model\Orders;
use Webman\Http\Request;

class ExportController extends \app\controller\BaseApiController
{
    /**
     * 导出订单（Excel/CSV）
     * GET /api/export/orders?format=xlsx&date_from=2026-01-01&date_to=2026-06-01
     */
    public function orders(Request $request): \support\Response
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Orders::where('user_id', $request->userId);
        if ($dateFrom) $query->where('created_at', '>=', $dateFrom);
        if ($dateTo) $query->where('created_at', '<=', $dateTo . ' 23:59:59');

        $orders = $query->orderBy('id','desc')->limit(1000)->get();

        // TODO: PhpSpreadsheet 生成Excel
        // return Excel::download($orders, 'orders.xlsx');

        $csv = "Order No,Date,Amount,Currency,Status\n";
        foreach ($orders as $o) {
            $statusText = ['待付款','已付款','已发货','已收货','已完成','已取消'][$o->status] ?? '';
            $csv .= "{$o->order_no},{$o->created_at},{$o->pay_amount},{$o->currency_code},{$statusText}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ]);
    }
}
