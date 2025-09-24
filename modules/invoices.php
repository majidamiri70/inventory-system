<?php
$page_title = "مدیریت فاکتورها";
$datatable = true;
require_once '../includes/header.php';

// ایجاد شیء Notification
$notification = new Notification($db);

$success = $error = '';

// افزودن فاکتور جدید
if (isset($_POST['add_invoice'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        $error = "توکن امنیتی نامعتبر است";
    } else {
        $customer_id = (int)$_POST['customer_id'];
        $invoice_date = sanitize_input($_POST['invoice_date']);
        $tax = (float)$_POST['tax'] ?? 0;
        $discount = (float)$_POST['discount'] ?? 0;
        $notes = sanitize_input($_POST['notes'] ?? '');
        
        // اعتبارسنجی
        if ($customer_id <= 0 || empty($invoice_date)) {
            $error = "لطفا اطلاعات ضروری را وارد کنید";
        } else {
            try {
                $db->beginTransaction();
                
                // محاسبه جمع کل
                $subtotal = 0;
                $product_ids = $_POST['product_id'] ?? [];
                $quantities = $_POST['quantity'] ?? [];
                
                // بررسی موجودی کافی
                foreach ($product_ids as $index => $product_id) {
                    if (!empty($product_id) && !empty($quantities[$index])) {
                        $product_id = (int)$product_id;
                        $quantity = (int)$quantities[$index];
                        
                        // بررسی موجودی
                        $stock_query = "SELECT stock, name FROM products WHERE id = :id";
                        $stock_stmt = $db->prepare($stock_query);
                        $stock_stmt->bindParam(':id', $product_id);
                        $stock_stmt->execute();
                        $product = $stock_stmt->fetch();
                        
                        if (!$product) {
                            throw new Exception("محصول یافت نشد");
                        }
                        
                        if ($product['stock'] < $quantity) {
                            throw new Exception("موجودی محصول '{$product['name']}' کافی نیست (موجودی: {$product['stock']})");
                        }
                    }
                }
                
                // محاسبه جمع کل
                for ($i = 0; $i < count($product_ids); $i++) {
                    if (!empty($product_ids[$i]) && !empty($quantities[$i])) {
                        $product_id = (int)$product_ids[$i];
                        $quantity = (int)$quantities[$i];
                        
                        $price_query = "SELECT selling_price FROM products WHERE id = :id";
                        $price_stmt = $db->prepare($price_query);
                        $price_stmt->bindParam(':id', $product_id);
                        $price_stmt->execute();
                        $product_price = $price_stmt->fetch()['selling_price'];
                        
                        $subtotal += $quantity * $product_price;
                    }
                }
                
                $total = $subtotal + $tax - $discount;
                
                // ذخیره فاکتور
                $query = "INSERT INTO invoices (customer_id, invoice_date, subtotal, tax, discount, total, notes) 
                          VALUES (:customer_id, :invoice_date, :subtotal, :tax, :discount, :total, :notes)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':customer_id', $customer_id);
                $stmt->bindParam(':invoice_date', $invoice_date);
                $stmt->bindParam(':subtotal', $subtotal);
                $stmt->bindParam(':tax', $tax);
                $stmt->bindParam(':discount', $discount);
                $stmt->bindParam(':total', $total);
                $stmt->bindParam(':notes', $notes);
                $stmt->execute();
                
                $invoice_id = $db->lastInsertId();
                
                // ذخیره آیتم‌های فاکتور و کاهش موجودی
                for ($i = 0; $i < count($product_ids); $i++) {
                    if (!empty($product_ids[$i]) && !empty($quantities[$i])) {
                        $product_id = (int)$product_ids[$i];
                        $quantity = (int)$quantities[$i];
                        
                        $price_query = "SELECT selling_price, name FROM products WHERE id = :id";
                        $price_stmt = $db->prepare($price_query);
                        $price_stmt->bindParam(':id', $product_id);
                        $price_stmt->execute();
                        $product = $price_stmt->fetch();
                        
                        $unit_price = $product['selling_price'];
                        $total_price = $quantity * $unit_price;
                        
                        // ذخیره آیتم
                        $item_query = "INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, total_price) 
                                       VALUES (:invoice_id, :product_id, :quantity, :unit_price, :total_price)";
                        $item_stmt = $db->prepare($item_query);
                        $item_stmt->bindParam(':invoice_id', $invoice_id);
                        $item_stmt->bindParam(':product_id', $product_id);
                        $item_stmt->bindParam(':quantity', $quantity);
                        $item_stmt->bindParam(':unit_price', $unit_price);
                        $item_stmt->bindParam(':total_price', $total_price);
                        $item_stmt->execute();
                        
                        // کاهش موجودی
                        $update_query = "UPDATE products SET stock = stock - :quantity WHERE id = :product_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':quantity', $quantity);
                        $update_stmt->bindParam(':product_id', $product_id);
                        $update_stmt->execute();
                        
                        // بررسی موجودی کم
                        $check_stock_query = "SELECT stock, min_stock FROM products WHERE id = :product_id";
                        $check_stmt = $db->prepare($check_stock_query);
                        $check_stmt->bindParam(':product_id', $product_id);
                        $check_stmt->execute();
                        $stock_info = $check_stmt->fetch();
                        
                        if ($stock_info['stock'] <= $stock_info['min_stock']) {
                            $notification->createNotification(
                                $_SESSION['user_id'],
                                'موجودی کم',
                                'موجودی محصول ' . $product['name'] . ' به ' . $stock_info['stock'] . ' کاهش یافت',
                                'warning'
                            );
                        }
                    }
                }
                
                $db->commit();
                
                // ایجاد اعلان
                $notification->createNotification(
                    $_SESSION['user_id'],
                    'فاکتور جدید',
                    'فاکتور شماره ' . $invoice_id . ' به مبلغ ' . number_format($total) . ' تومان ثبت شد',
                    'info'
                );
                
                $success = "فاکتور شماره #{$invoice_id} با موفقیت ثبت شد.";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "خطا در ثبت فاکتور: " . $e->getMessage();
            }
        }
    }
}

// دریافت لیست فاکتورها
try {
    $query = "SELECT i.*, c.name as customer_name, u.username as created_by_name 
          FROM invoices i 
          LEFT JOIN customers c ON i.customer_id = c.id 
          LEFT JOIN users u ON i.created_by = u.id 
          ORDER BY i.id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $invoices = [];
    error_log("Invoices error: " . $e->getMessage());
}

// دریافت لیست مشتریان و محصولات
try {
    $customers_query = "SELECT id, name FROM customers ORDER BY name";
    $customers_stmt = $db->prepare($customers_query);
    $customers_stmt->execute();
    $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $products_query = "SELECT id, name, selling_price, stock FROM products WHERE stock > 0 ORDER BY name";
    $products_stmt = $db->prepare($products_query);
    $products_stmt->execute();
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $customers = $products = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-receipt me-2"></i>مدیریت فاکتورها</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
        <i class="bi bi-plus-circle me-1"></i>افزودن فاکتور
    </button>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">لیست فاکتورها</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="dataTable" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>شماره فاکتور</th>
                        <th>مشتری</th>
                        <th>تاریخ</th>
                        <th>جمع کل</th>
                        <th>مالیات</th>
                        <th>تخفیف</th>
                        <th>مبلغ نهایی</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): 
                        $status_badge = $invoice['status'] == 'paid' ? 
                            'bg-success' : 'bg-warning';
                        $status_text = $invoice['status'] == 'paid' ? 
                            'پرداخت شده' : 'در انتظار پرداخت';
                    ?>
                    <tr>
                        <td><strong>#<?php echo $invoice['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                        <td><?php echo date('Y/m/d', strtotime($invoice['invoice_date'])); ?></td>
                        <td><?php echo number_format($invoice['subtotal']); ?> تومان</td>
                        <td><?php echo number_format($invoice['tax']); ?> تومان</td>
                        <td><?php echo number_format($invoice['discount']); ?> تومان</td>
                        <td><strong><?php echo number_format($invoice['total']); ?> تومان</strong></td>
                        <td>
                            <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" 
                                   class="btn btn-info" title="مشاهده">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button class="btn <?php echo $invoice['status'] == 'paid' ? 'btn-warning' : 'btn-success'; ?> change-status" 
                                        data-id="<?php echo $invoice['id']; ?>"
                                        data-current-status="<?php echo $invoice['status']; ?>"
                                        title="تغییر وضعیت">
                                    <i class="bi <?php echo $invoice['status'] == 'paid' ? 'bi-x-circle' : 'bi-check-circle'; ?>"></i>
                                </button>
                                <button class="btn btn-danger delete-invoice" 
                                        data-id="<?php echo $invoice['id']; ?>"
                                        title="حذف">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مودال افزودن فاکتور -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن فاکتور جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="invoiceForm">
                <?php echo CSRF::getTokenField(); ?>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">مشتری <span class="text-danger">*</span></label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">انتخاب مشتری</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="invoice_date" class="form-label">تاریخ فاکتور <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="border-bottom pb-2">آیتم‌های فاکتور</h6>
                    <div id="invoiceItems">
                        <div class="row item-row mb-3">
                            <div class="col-md-5">
                                <select class="form-select product-select" name="product_id[]" onchange="updatePrice(this)" required>
                                    <option value="">انتخاب محصول</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            data-price="<?php echo $product['selling_price']; ?>"
                                            data-stock="<?php echo $product['stock']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?> - 
                                        قیمت: <?php echo number_format($product['selling_price']); ?> - 
                                        موجودی: <?php echo $product['stock']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control quantity" name="quantity[]" 
                                       placeholder="تعداد" min="1" onchange="calculateTotal()" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control price" placeholder="قیمت واحد" readonly>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addNewItem()">
                        <i class="bi bi-plus-circle me-1"></i>افزودن آیتم
                    </button>
                    
                    <div class="row mt-4">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="notes" class="form-label">توضیحات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="توضیحات اختیاری"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="subtotal" class="form-label">جمع کل</label>
                                <input type="text" class="form-control" id="subtotal" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="tax" class="form-label">مالیات (تومان)</label>
                                <input type="number" class="form-control" id="tax" name="tax" value="0" 
                                       min="0" onchange="calculateFinalTotal()">
                            </div>
                            <div class="mb-3">
                                <label for="discount" class="form-label">تخفیف (تومان)</label>
                                <input type="number" class="form-control" id="discount" name="discount" value="0" 
                                       min="0" onchange="calculateFinalTotal()">
                            </div>
                            <div class="mb-3">
                                <label for="total" class="form-label">مبلغ نهایی</label>
                                <input type="text" class="form-control fw-bold text-success" id="total" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_invoice" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>ثبت فاکتور
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let itemCount = 1;

function addNewItem() {
    itemCount++;
    const newItem = document.querySelector('.item-row').cloneNode(true);
    newItem.querySelector('.product-select').value = '';
    newItem.querySelector('.quantity').value = '';
    newItem.querySelector('.price').value = '';
    newItem.querySelector('.quantity').setAttribute('onchange', 'calculateTotal()');
    newItem.querySelector('.product-select').setAttribute('onchange', 'updatePrice(this)');
    document.getElementById('invoiceItems').appendChild(newItem);
}

function removeItem(button) {
    if (document.querySelectorAll('.item-row').length > 1) {
        button.closest('.item-row').remove();
        calculateTotal();
    }
}

function updatePrice(select) {
    const price = select.options[select.selectedIndex].getAttribute('data-price');
    const priceInput = select.closest('.item-row').querySelector('.price');
    priceInput.value = price ? numberFormat(price) + ' تومان' : '';
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const select = row.querySelector('.product-select');
        const quantityInput = row.querySelector('.quantity');
        const price = select.options[select.selectedIndex]?.getAttribute('data-price') || 0;
        const quantity = quantityInput.value || 0;
        
        if (price && quantity) {
            subtotal += parseFloat(price) * parseInt(quantity);
        }
    });
    
    document.getElementById('subtotal').value = numberFormat(subtotal) + ' تومان';
    calculateFinalTotal();
}

function calculateFinalTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value.replace(/[^\d]/g, '')) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    
    const total = subtotal + tax - discount;
    document.getElementById('total').value = numberFormat(total) + ' تومان';
}

function numberFormat(number) {
    return new Intl.NumberFormat('fa-IR').format(number);
}

// مدیریت تغییر وضعیت و حذف
$(document).on('click', '.change-status', function() {
    const invoiceId = $(this).data('id');
    const currentStatus = $(this).data('current-status');
    const newStatus = currentStatus === 'paid' ? 'pending' : 'paid';
    
    const statusText = newStatus === 'paid' ? 'پرداخت شده' : 'در انتظار پرداخت';
    
    if (confirm(`آیا از تغییر وضعیت فاکتور به "${statusText}" اطمینان دارید؟`)) {
        changeInvoiceStatus(invoiceId, newStatus, $(this));
    }
});

$(document).on('click', '.delete-invoice', function() {
    const invoiceId = $(this).data('id');
    
    if (confirm(`آیا از حذف این فاکتور اطمینان دارید؟`)) {
        deleteItem('invoice', invoiceId);
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>