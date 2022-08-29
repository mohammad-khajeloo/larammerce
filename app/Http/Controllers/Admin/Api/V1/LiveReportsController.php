<?php

namespace App\Http\Controllers\Admin\Api\V1;

use App\Models\Directory;
use App\Models\Enums\DirectoryType;
use App\Models\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Common\MessageFactory;
use App\Utils\Jalali\JDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LiveReportsController extends BaseController
{
    private function getMonthLength($month_number): int
    {
        return [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29][$month_number - 1];
    }

    private function getTodayRange(): array
    {
        $now = Carbon::now();
        return [
            $now->format("Y-m-d 00:00:00"),
            $now->format("Y-m-d 23:59:59")
        ];
    }

    private function getCurrentMonthRange(): array
    {
        $now = Carbon::now();
        list($year, $month, $day) = JDateTime::toJalali($now->year, $now->month, $now->day);
        list($start_year, $start_month, $start_day) = JDateTime::toGregorian($year, $month, 1);
        list($end_year, $end_month, $end_day) = JDateTime::toGregorian($year, $month, $this->getMonthLength($month));
        return [
            "$start_year-$start_month-$start_day 00:00:00",
            "$end_year-$end_month-$end_day 23:59:59"
        ];
    }

    private function getCurrentYearRange(): array
    {
        $now = Carbon::now();
        list($year, $month, $day) = JDateTime::toJalali($now->year, $now->month, $now->day);
        list($start_year, $start_month, $start_day) = JDateTime::toGregorian($year, 1, 1);
        list($end_year, $end_month, $end_day) = JDateTime::toGregorian($year, 12, 29);

        return [
            "$start_year-$start_month-$start_day 00:00:00",
            "$end_year-$end_month-$end_day 23:59:59"
        ];
    }

    private function getPreviousYearRange(): array
    {
        $now = Carbon::now();
        list($year, $month, $day) = JDateTime::toJalali($now->year, $now->month, $now->day);
        list($start_year, $start_month, $start_day) = JDateTime::toGregorian($year - 1, 1, 1);
        list($end_year, $end_month, $end_day) = JDateTime::toGregorian($year - 1, 12, 29);

        return [
            "$start_year-$start_month-$start_day 00:00:00",
            "$end_year-$end_month-$end_day 23:59:59"
        ];
    }

    private function getCategoriesSalesAmount($start_date, $end_date): \Illuminate\Support\Collection
    {
        $head_directories = Directory::where("directory_id", null)->pluck("id")->toArray();

        if (count($head_directories) == 1) {
            $parent_id = $head_directories[0];
        } else {
            $parent_id = null;
        }

        return DB::table("directories")
            ->where("directories.directory_id", $parent_id)
            ->where("directories.content_type", DirectoryType::PRODUCT)
            ->join("directory_product",
                function ($join_l1) use ($start_date, $end_date) {
                    $join_l1->on("directory_product.directory_id", "=", "directories.id")
                        ->join("invoice_rows", function ($join_l2) use ($start_date, $end_date) {
                            $join_l2->on("invoice_rows.product_id", "=", "directory_product.product_id")
                                ->join("invoices", function ($join_l3) use ($start_date, $end_date) {
                                    $join_l3->on("invoices.id", "=", "invoice_rows.invoice_id")
                                        ->whereIn("invoices.payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
                                        ->where("invoices.created_at", ">=", $start_date)
                                        ->where("invoices.created_at", "<=", $end_date);
                                });
                        });
                })->groupBy("directories.id")
            ->selectRaw(DB::raw("directories.id as id, directories.title as title ,sum(invoice_rows.product_price * invoice_rows.count) as total_amount"))
            ->orderBy("total_amount", "DESC")
            ->get();
    }

    public function getDailySalesAmount(): JsonResponse
    {
        list($start_date, $end_date) = $this->getTodayRange();

        $amount = Invoice::whereIn("payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<=", $end_date)
            ->sum("sum");

        return MessageFactory::jsonResponse([], 200, compact("amount"));
    }

    public function getMonthlySalesAmount(): JsonResponse
    {
        list($start_date, $end_date) = $this->getCurrentMonthRange();

        $amount = Invoice::whereIn("payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<=", $end_date)
            ->sum("sum");

        return MessageFactory::jsonResponse([], 200, compact("amount"));
    }

    public function getYearlySalesAmount(): JsonResponse
    {
        list($start_date, $end_date) = $this->getCurrentYearRange();

        $amount = Invoice::whereIn("payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<=", $end_date)
            ->sum("sum");

        return MessageFactory::jsonResponse([], 200, compact("amount"));
    }

    public function getPreviousYearSalesAmount(): JsonResponse
    {
        list($start_date, $end_date) = $this->getPreviousYearRange();

        $amount = Invoice::whereIn("payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
            ->where("created_at", ">=", $start_date)
            ->where("created_at", "<=", $end_date)
            ->sum("sum");

        return MessageFactory::jsonResponse([], 200, compact("amount"));
    }

    public function getOverallBarChartData(): JsonResponse
    {
        $now = Carbon::now();
        list($year, $month, $day) = JDateTime::toJalali($now->year, $now->month, $now->day);
        $labels = [];
        $datasets = [
            [
                "label" => "ثبت نام",
                "backgroundColor" => "rgb(255, 87, 87)",
                "borderColor" => "rgb(110, 11, 4)",
                "data" => []
            ],
            [
                "label" => "ثبت سفارش",
                "backgroundColor" => "rgb(84, 247, 103)",
                "borderColor" => "rgb(39, 92, 11)",
                "data" => []
            ],
            [
                "label" => "سفارش نهایی شده",
                "backgroundColor" => "rgb(93, 99, 252)",
                "borderColor" => "rgb(11, 21, 82)",
                "data" => []
            ]
        ];
        for ($i = 1; $i <= 12; $i++) {
            $tmp_month = $month + $i;
            $carry = (int)($tmp_month / 12);
            $tmp_month = $tmp_month % 12;
            $tmp_year = ($year - 1 + $carry);

            if ($tmp_month === 0) {
                $tmp_month = 12;
                $tmp_year -= 1;
            }

            $labels[] = JDateTime::getMonthNames($tmp_month) . " " . JDateTime::convertNumbers("$tmp_year");

            list($start_year, $start_month, $start_day) = JDateTime::toGregorian($tmp_year, $tmp_month, 1);
            list($end_year, $end_month, $end_day) = JDateTime::toGregorian($tmp_year, $tmp_month, $this->getMonthLength($tmp_month));

            $datasets[0]["data"][] = User::where("created_at", ">=", $now->format("$start_year-$start_month-$start_day 00:00:00"))
                ->where("created_at", "<=", $now->format("$end_year-$end_month-$end_day 23:59:59"))
                ->count();
            $datasets[1]["data"][] = Invoice::where("created_at", ">=", $now->format("$start_year-$start_month-$start_day 00:00:00"))
                ->where("created_at", "<=", $now->format("$end_year-$end_month-$end_day 23:59:59"))
                ->count();

            $datasets[2]["data"][] = Invoice::whereIn("payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
                ->where("created_at", ">=", $now->format("$start_year-$start_month-$start_day 00:00:00"))
                ->where("created_at", "<=", $now->format("$end_year-$end_month-$end_day 23:59:59"))
                ->count();
        }

        return MessageFactory::jsonResponse([], 200, compact("labels", "datasets"));
    }

    public function getMonthlyCategoriesSales(): JsonResponse
    {
        list($start_date, $end_date) = $this->getCurrentMonthRange();
        $rows = $this->getCategoriesSalesAmount($start_date, $end_date);
        return MessageFactory::jsonResponse([], 200, compact("rows"));
    }

    public function getYearlyCategoriesSales(): JsonResponse
    {
        list($start_date, $end_date) = $this->getCurrentYearRange();
        $rows = $this->getCategoriesSalesAmount($start_date, $end_date);
        return MessageFactory::jsonResponse([], 200, compact("rows"));
    }

    public function getPreviousYearCategoriesSales(): JsonResponse
    {
        list($start_date, $end_date) = $this->getPreviousYearRange();
        $rows = $this->getCategoriesSalesAmount($start_date, $end_date);
        return MessageFactory::jsonResponse([], 200, compact("rows"));
    }

    public function getLatestCustomers(): JsonResponse
    {
        $rows = User::where("is_customer_user", true)->orderBy("created_at", "desc")->limit(10)->get();
        return MessageFactory::jsonResponse([], 200, compact("rows"));
    }

    public function getLatestPayedOrders(): JsonResponse
    {
        $rows = Invoice::with(["customer.user"])->whereIn("payment_status", [PaymentStatus::SUBMITTED, PaymentStatus::CONFIRMED, PaymentStatus::PAID_OUT])
            ->orderBy("created_at", "desc")->limit(10)->get();
        return MessageFactory::jsonResponse([], 200, compact("rows"));
    }

    public function getModel(): ?string
    {
        return null;
    }
}