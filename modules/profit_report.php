<?php
$page_title = "گزارش سود و زیان";
require_once '../includes/header.php';
require_once '../includes/jdate.php'; // ✅ اضافه کردن تاریخ شمسی

// پارامترهای گزارش
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'detailed';

try {
    // محاسبه سود و زیان
    $profit_data = [];
    
    // فروش کل
    $sales_query = "SELECT 
                    COUNT(*) as invoice_count,
                    SUM(total) as total_sales,
                    SUM(subtotal) as total_subtotal,
                    SUM(tax) as total_tax,
                    SUM(discount) as total_discount
                   FROM invoices 
                   WHERE invoice_date BETWEEN :start_date AND :end_date 
                   AND status = 'paid'";
    
    $sales_stmt = $db->prepare($sales_query);
    $sales_stmt->bindParam(':start_date', $start_date);
    $sales_stmt->bindParam(':end_date', $end_date);
    $sales_stmt->execute();
    $sales_data = $sales_stmt->fetch();
    
    // هزینه خرید محصولات فروخته شده
    $cogs_query = "SELECT 
                    SUM(ii.quantity * p.purchase_price) as total_cost
                   FROM invoice_items ii
                   JOIN invoices i ON ii.invoice_id = i.id
                   JOIN products p ON ii.product_id = p.id
                   WHERE i.invoice_date BETWEEN :start_date AND :end_date 
                   AND i.status = 'paid'";
    
    $cogs_stmt = $db->prepare($cogs_query);
    $cogs_stmt->bindParam(':start_date', $start_date);
    $cogs_stmt->bindParam(':end_date', $end_date);
    $cogs_stmt->execute();
    $cogs_data = $cogs_stmt->fetch();
    
    // محاسبات سود
    $total_sales = $sales_data['total_sales'] ?? 0;
    $total_cost = $cogs_data['total_cost'] ?? 0;
    $gross_profit = $total_sales - $total_cost;
    $profit_margin = $total_sales > 0 ? ($gross_profit / $total_sales) * 100 : 0;
    
    // محصولات پرفروش
    $top_products_query = "SELECT 
                            p.name,
                            p.sku,
                            SUM(ii.quantity) as total_sold,
                            SUM(ii.total_price) as total_revenue,
                            SUM(ii.quantity * p.purchase_price) as total_cost,
                            (SUM(ii.total_price) - SUM(ii.quantity * p.purchase_price)) as total_profit
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
    
} catch (Exception $e) {
    $error = "خطا در محاسبه گزارش: " . $e->getMessage();
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">گزارش سود و زیان</h5>
    </div>
    <div class="card-body">
        <!-- خلاصه سود و زیان -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h6>فروش کل</h6>
                        <h4><?php echo number_format($total_sales); ?> تومان</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h6>هزینه کالا</h6>
                        <h4><?php echo number_format($total_cost); ?> تومان</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h6>سود ناخالص</h6>
                        <h4><?php echo number_format($gross_profit); ?> تومان</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h6>درصد سود</h6>
                        <h4><?php echo number_format($profit_margin, 2); ?>%</h4>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- محصولات پرفروش -->
        <h6>محصولات پرفروش</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>محصول</th>
                        <th>تعداد فروش</th>
                        <th>درآمد</th>
                        <th>هزینه</th>
                        <th>سود</th>
                        <th>درصد سود</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_products as $product): ?>
                    <tr>
                        <td><?php echo $product['name']; ?></td>
                        <td><?php echo number_format($product['total_sold']); ?></td>
                        <td><?php echo number_format($product['total_revenue']); ?></td>
                        <td><?php echo number_format($product['total_cost']); ?></td>
                        <td class="text-success"><?php echo number_format($product['total_profit']); ?></td>
                        <td><?php echo number_format(($product['total_profit'] / $product['total_revenue']) * 100, 2); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>