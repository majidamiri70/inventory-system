// js/script.js - سیستم مدیریت انبار
$(document).ready(function() {
    console.log('✅ سیستم مدیریت انبار بارگذاری شد');
    
    // مدیریت CSRF Token
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    
    // ==================== مدیریت حذف آیتم‌ها ====================
    
    // حذف محصول
    $(document).on('click', '.delete-product', function(e) {
        e.preventDefault();
        const productId = $(this).data('id');
        const productName = $(this).data('name');
        
        showConfirmModal(
            'حذف محصول',
            `آیا از حذف محصول "<strong>${productName}</strong>" اطمینان دارید؟`,
            function() {
                deleteItem('product', productId, productName);
            }
        );
    });

    // حذف مشتری
    $(document).on('click', '.delete-customer', function(e) {
        e.preventDefault();
        const customerId = $(this).data('id');
        const customerName = $(this).data('name');
        
        showConfirmModal(
            'حذف مشتری',
            `آیا از حذف مشتری "<strong>${customerName}</strong>" اطمینان دارید؟`,
            function() {
                deleteItem('customer', customerId, customerName);
            }
        );
    });

    // حذف فاکتور
    $(document).on('click', '.delete-invoice', function(e) {
        e.preventDefault();
        const invoiceId = $(this).data('id');
        
        showConfirmModal(
            'حذف فاکتور',
            `آیا از حذف فاکتور شماره <strong>#${invoiceId}</strong> اطمینان دارید؟<br><small class="text-warning">موجودی محصولات به صورت خودکار بازگردانی می‌شود</small>`,
            function() {
                deleteItem('invoice', invoiceId);
            }
        );
    });

    // حذف چک
    $(document).on('click', '.delete-check', function(e) {
        e.preventDefault();
        const checkId = $(this).data('id');
        const checkNumber = $(this).data('number');
        
        showConfirmModal(
            'حذف چک',
            `آیا از حذف چک شماره <strong>${checkNumber}</strong> اطمینان دارید؟`,
            function() {
                deleteItem('check', checkId, checkNumber);
            }
        );
    });
    
    // ==================== مدیریت تغییر وضعیت فاکتور ====================
    
    $(document).on('click', '.change-status', function(e) {
        e.preventDefault();
        const invoiceId = $(this).data('id');
        const currentStatus = $(this).data('current-status');
        const newStatus = currentStatus === 'paid' ? 'pending' : 'paid';
        
        const statusText = newStatus === 'paid' ? 'پرداخت شده' : 'در انتظار پرداخت';
        const button = $(this);
        
        showConfirmModal(
            'تغییر وضعیت فاکتور',
            `آیا از تغییر وضعیت فاکتور به "<strong>${statusText}</strong>" اطمینان دارید؟`,
            function() {
                changeInvoiceStatus(invoiceId, newStatus, button);
            }
        );
    });

    // ==================== توابع اصلی ====================
    
    // تابع اصلی برای حذف آیتم‌ها
    function deleteItem(type, id, name = '') {
        const button = $(`.delete-${type}[data-id="${id}"]`);
        const originalHTML = button.html();
        
        // نمایش حالت loading
        button.html('<span class="loading"></span> در حال حذف...');
        button.prop('disabled', true);
        
        $.ajax({
            url: `/modules/delete/delete_${type}.php`,
            method: 'POST',
            data: { 
                id: id,
                action: 'delete',
                csrf_token: csrfToken
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.success) {
                        showNotification(result.message, 'success', 3000);
                        
                        // رفرش صفحه پس از 2 ثانیه
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotification(result.message, 'warning', 5000);
                        button.html(originalHTML);
                        button.prop('disabled', false);
                    }
                } catch (e) {
                    console.error('❌ خطای پردازش JSON:', e);
                    showNotification('خطا در پردازش پاسخ سرور', 'danger');
                    button.html(originalHTML);
                    button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                handleAjaxError(xhr, status, error, type, button, originalHTML);
            }
        });
    }
    
    // تغییر وضعیت فاکتور
    function changeInvoiceStatus(invoiceId, newStatus, button) {
        const originalHTML = button.html();
        button.html('<span class="loading"></span> در حال تغییر...');
        button.prop('disabled', true);
        
        $.ajax({
            url: '/modules/change_invoice_status.php',
            method: 'POST',
            data: { 
                id: invoiceId,
                status: newStatus,
                action: 'change_status',
                csrf_token: csrfToken
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.success) {
                        showNotification(result.message, 'success', 3000);
                        
                        // بروزرسانی دکمه بدون رفرش صفحه
                        const newStatusText = newStatus === 'paid' ? 'پرداخت شده' : 'در انتظار پرداخت';
                        const newStatusClass = newStatus === 'paid' ? 'btn-success' : 'btn-warning';
                        const newIcon = newStatus === 'paid' ? 'bi-check-circle' : 'bi-x-circle';
                        const oppositeStatus = newStatus === 'paid' ? 'pending' : 'paid';
                        
                        button.removeClass('btn-warning btn-success')
                              .addClass(newStatusClass)
                              .data('current-status', newStatus)
                              .html(`<i class="bi ${newIcon}"></i> ${newStatus === 'paid' ? 'بازگشت به pending' : 'علامت پرداخت شده'}`);
                        
                        // بروزرسانی badge وضعیت
                        const statusBadge = button.closest('tr').find('.badge');
                        statusBadge.removeClass('bg-warning bg-success')
                                  .addClass(newStatus === 'paid' ? 'bg-success' : 'bg-warning')
                                  .text(newStatusText);
                        
                    } else {
                        showNotification(result.message, 'danger');
                        button.html(originalHTML);
                        button.prop('disabled', false);
                    }
                } catch (e) {
                    console.error('❌ خطای پردازش JSON:', e);
                    showNotification('خطا در پردازش پاسخ سرور', 'danger');
                    button.html(originalHTML);
                    button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ خطای AJAX:', error);
                showNotification('خطا در ارتباط با سرور', 'danger');
                button.html(originalHTML);
                button.prop('disabled', false);
            }
        });
    }
    
    // مدیریت خطاهای AJAX
    function handleAjaxError(xhr, status, error, type, button = null, originalHTML = null) {
        console.error(`❌ خطای AJAX برای ${type}:`, status, error);
        
        let errorMessage = 'خطا در ارتباط با سرور';
        let notificationType = 'danger';
        
        switch (xhr.status) {
            case 403:
                errorMessage = 'دسترسی غیر مجاز';
                break;
            case 404:
                errorMessage = 'منبع یافت نشد';
                break;
            case 409:
                try {
                    const result = JSON.parse(xhr.responseText);
                    errorMessage = result.message;
                    notificationType = 'warning';
                } catch (e) {
                    errorMessage = 'این آیتم در جای دیگری استفاده شده و قابل حذف نیست';
                    notificationType = 'warning';
                }
                break;
            case 500:
                errorMessage = 'خطای سرور داخلی';
                break;
            case 0:
                errorMessage = 'اتصال به سرور برقرار نشد';
                break;
        }
        
        showNotification(errorMessage, notificationType, 5000);
        
        if (button && originalHTML) {
            button.html(originalHTML);
            button.prop('disabled', false);
        }
    }
    
    // نمایش نوتیفیکیشن
    function showNotification(message, type = 'info', duration = 5000) {
        // حذف نوتیفیکیشن‌های قبلی
        $('.custom-notification').remove();
        
        const icons = {
            'success': '✅',
            'warning': '⚠️',
            'danger': '❌',
            'info': 'ℹ️'
        };
        
        const icon = icons[type] || icons['info'];
        
        const notification = $(`
            <div class="alert alert-${type} alert-dismissible fade show custom-notification" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;">
                <strong>${icon}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('body').append(notification);
        
        // نمایش انیمیشن
        notification.hide().slideDown(300);
        
        // حذف خودکار پس از مدت مشخص
        setTimeout(() => {
            notification.slideUp(300, function() {
                $(this).alert('close');
            });
        }, duration);
    }
    
    // نمایش مودال تأیید
    function showConfirmModal(title, message, confirmCallback) {
        // حذف مودال‌های قبلی
        $('#confirmModal').remove();
        
        const modal = $(`
            <div class="modal fade" id="confirmModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="button" class="btn btn-danger" id="confirmButton">تأیید حذف</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        const modalInstance = new bootstrap.Modal(modal[0]);
        modalInstance.show();
        
        // مدیریت کلیک روی دکمه تأیید
        modal.on('click', '#confirmButton', function() {
            modalInstance.hide();
            confirmCallback();
        });
        
        // حذف مودال پس از بسته شدن
        modal.on('hidden.bs.modal', function() {
            modal.remove();
        });
    }
    
    // ==================== مدیریت فرم‌ها ====================
    
    // جلوگیری از ارسال دوباره فرم
    $(document).on('submit', 'form', function() {
        const submitBtn = $(this).find('button[type="submit"]');
        if (submitBtn.length > 0) {
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="loading"></span> در حال پردازش...');
        }
    });
    
    // مدیریت inputهای عددی
    $(document).on('input', 'input[type="number"]', function() {
        this.value = this.value.replace(/[^\d]/g, '');
    });
    
    // مدیریت تاریخ‌ها
    $(document).on('focus', 'input[type="date"]', function() {
        if (!this.value) {
            this.value = new Date().toISOString().split('T')[0];
        }
    });
    
    // ==================== ویژگی‌های اضافی ====================
    
    // مدیریت جستجو در جداول
    $(document).on('keyup', '.table-search', function() {
        const value = $(this).val().toLowerCase();
        const table = $(this).data('table');
        $(`#${table} tbody tr`).filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // مدیریت انتخاب تمامی آیتم‌ها
    $(document).on('change', '.select-all', function() {
        const isChecked = $(this).prop('checked');
        $(this).closest('table').find('.select-item').prop('checked', isChecked);
    });
    
    // مدیریت hover روی کارت‌ها
    $(document).on('mouseenter', '.card', function() {
        $(this).addClass('card-hover');
    }).on('mouseleave', '.card', function() {
        $(this).removeClass('card-hover');
    });
    
    // ==================== توابع کمکی ====================
    
    // فرمت کردن اعداد
    function formatNumber(number) {
        return new Intl.NumberFormat('fa-IR').format(number);
    }
    
    // تبدیل تاریخ به شمسی
    function toPersianDate(dateString) {
        // پیاده‌سازی ساده - می‌توانید از کتابخانه‌های کامل استفاده کنید
        const date = new Date(dateString);
        return date.toLocaleDateString('fa-IR');
    }
    
    // بررسی اینکه آیا کاربر در حال اسکرول کرده است
    let scrollTimer;
    $(window).scroll(function() {
        $('body').addClass('scrolling');
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function() {
            $('body').removeClass('scrolling');
        }, 100);
    });
    
    // مدیریت responsive منو
    $('.navbar-toggler').click(function() {
        $('.sidebar').toggleClass('mobile-open');
    });
    
    console.log('🚀 سیستم مدیریت انبار آماده است');
});

// توابع全局 برای استفاده در سایر فایل‌ها
window.inventorySystem = {
    showNotification: function(message, type, duration) {
        // پیاده‌سازی مشابه تابع showNotification بالا
    },
    
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('fa-IR').format(amount) + ' تومان';
    },
    
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    validatePhone: function(phone) {
        const re = /^09\d{9}$/;
        return re.test(phone);
    },
    
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// CSS برای loading spinner
const style = document.createElement('style');
style.textContent = `
    .loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 5px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .card-hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .scrolling {
        pointer-events: none;
    }
    
    .scrolling * {
        pointer-events: none;
    }
    
    .table-search {
        transition: all 0.3s ease;
    }
    
    .table-search:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }
`;
document.head.appendChild(style);