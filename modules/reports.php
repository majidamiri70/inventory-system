<?php
$page_title = "گزارشات پیشرفته";
require_once '../includes/header.php';


// پارامترهای گزارش
$report_type = $_GET['report_type'] ?? 'profit';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    if ($report_type == 'profit') {
        // گزارش سود و زیان پیشرفته
        $profit_query = "SELECT 
                        COUNT(i.id) as total_invoices,
                        SUM(i.total) as total_sales,
                        SUM(i.subtotal) as total_subtotal,
                        SUM(i.tax) as total_tax,
                        SUM(i.discount) as total_discount,
                        SUM(ii.quantity * p.purchase_price) as total_cost,
                        (SUM(i.total) - SUM(ii.quantity * p.purchase_price)) as gross_profit,
                        CASE 
                            WHEN SUM(i.total) > 0 THEN 
                                ((SUM(i.total) - SUM(ii.quantity * p.purchase_price)) / SUM(i.total)) * 100 
                            ELSE 0 
                        END as profit_margin
                    FROM invoices i
                    JOIN invoice_items ii ON i.id = ii.invoice_id
                    JOIN products p ON ii.product_id = p.id
                    WHERE i.invoice_date BETWEEN :start_date AND :end_date 
                    AND i.status = 'paid'";
        
        $profit_stmt = $db->prepare($profit_query);
        $profit_stmt->bindParam(':start_date', $start_date);
        $profit_stmt->bindParam(':end_date', $end_date);
        $profit_stmt->execute();
        $profit_data = $profit_stmt->fetch();
        
        // محصولات پرفروش
        $top_products_query = "SELECT 
                                p.name,
                                p.sku,
                                SUM(ii.quantity) as total_sold,
                                SUM(ii.total_price) as total_revenue,
                                SUM(ii.quantity * p.purchase_price) as total_cost,
                                (SUM(ii.total_price) - SUM(ii.quantity * p.purchase_price)) as total_profit,
                                CASE 
                                    WHEN SUM(ii.total_price) > 0 THEN 
                                        ((SUM(ii.total_price) - SUM(ii.quantity * p.purchase_price)) / SUM(ii.total_price)) * 100 
                                    ELSE 0 
                                END as product_profit_margin
                            FROM invoice_items ii
                            JOIN invoices i ON ii.invoice_id = i.id
                            JOIN products p ON ii.product_id = p.id
                            WHERE i.invoice_date BETWEEN :start_date AND :end_date 
                            AND i.status = 'paid'
                            GROUP BY p.id
                            ORDER BY total_profit DESC
                            LIMIT 10";
        
        $top_products_stmt = $db->prepare($top_products_query);
        $top_products_stmt->bindParam(':start_date', $start_date);
        $top_products_stmt->bindParam(':end_date', $end_date);
        $top_products_stmt->execute();
        $top_products = $top_products_stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = "خطا در تولید گزارش: " . $e->getMessage();
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">
            <i class="bi bi-graph-up me-2"></i>گزارش سود و زیان پیشرفته
            <small class="float-end">تاریخ شمسی: <?php echo jdate('Y/m/d'); ?></small>
        </h5>
    </div>
    <div class="card-body">
        <!-- کارت‌های خلاصه -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">فروش کل</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($profit_data['total_sales'] ?? 0); ?> تومان
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">هزینه کالا</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($profit_data['total_cost'] ?? 0); ?> تومان
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">سود ناخالص</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($profit_data['gross_profit'] ?? 0); ?> تومان
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">درصد سود</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($profit_data['profit_margin'] ?? 0, 2); ?>%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">تعداد فاکتور</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($profit_data['total_invoices'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 mb-3">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">میانگین فاکتور</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format(($profit_data['total_sales'] ?? 0) / max(1, ($profit_data['total_invoices'] ?? 1))); ?> تومان
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- محصولات پرفروش -->
        <h5 class="mb-3">📊 10 محصول پرسود</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>محصول</th>
                        <th>تعداد فروش</th>
                        <th>درآمد</th>
                        <th>هزینه</th>
                        <th>سود</th>
                        <th>درصد سود</th>
                        <th>سود هر عدد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_products as $product): 
                        $profit_per_unit = $product['total_sold'] > 0 ? $product['total_profit'] / $product['total_sold'] : 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $product['name']; ?></strong>
                            <br><small class="text-muted"><?php echo $product['sku']; ?></small>
                        </td>
                        <td class="text-center"><?php echo number_format($product['total_sold']); ?></td>
                        <td class="text-success"><?php echo number_format($product['total_revenue']); ?></td>
                        <td class="text-danger"><?php echo number_format($product['total_cost']); ?></td>
                        <td class="text-primary"><strong><?php echo number_format($product['total_profit']); ?></strong></td>
                        <td class="text-info"><?php echo number_format($product['product_profit_margin'], 2); ?>%</td>
                        <td class="text-warning"><?php echo number_format($profit_per_unit); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- نمودار سود (جاوااسکریپت) -->
        <div class="mt-4">
            <h5 class="mb-3">📈 نمودار تحلیل سود</h5>
            <canvas id="profitChart" width="400" height="150"></canvas>
        </div>
    </div>
</div>

<script>
// نمودار سود و زیان
const ctx = document.getElementById('profitChart').getContext('2d');
const profitChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['فروش کل', 'هزینه کالا', 'سود ناخالص'],
        datasets: [{
            label: 'مبلغ (تومان)',
            data: [
                <?php echo $profit_data['total_sales'] ?? 0; ?>,
                <?php echo $profit_data['total_cost'] ?? 0; ?>,
                <?php echo $profit_data['gross_profit'] ?? 0; ?>
            ],
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return new Intl.NumberFormat('fa-IR').format(context.raw) + ' تومان';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fa-IR').format(value);
                    }
                }
            }
        }
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>