                </div> <!-- پایان محتوای اصلی -->
            </main>
        </div>
    </div>

    <!-- Modal برای نمایش نوتیفیکیشن -->
    <div class="modal fade" id="notificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">اعلان سیستم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="notificationMessage">
                    <!-- محتوای نوتیفیکیشن -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <!-- اسکریپت‌ها -->
    <script src="<?php echo $js_url; ?>/jquery-3.6.0.min.js"></script>
    <script src="<?php echo $js_url; ?>/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $js_url; ?>/jquery.dataTables.min.js"></script>
    <script src="<?php echo $js_url; ?>/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo $js_url; ?>/chart.js"></script>
    <script src="<?php echo $js_url; ?>/script.js"></script>

    <?php if ($datatable): ?>
    <script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            language: {
                url: '<?php echo $js_url; ?>/persian.json'
            },
            responsive: true,
            order: [[0, 'desc']],
            pageLength: 25,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });
    });
    </script>
    <?php endif; ?>

    <script>
    // مدیریت نوتیفیکیشن‌ها
    function showNotification(message, type = 'info') {
        const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
        const modalBody = document.getElementById('notificationMessage');
        
        let icon = 'ℹ️';
        let title = 'اطلاعات';
        
        switch(type) {
            case 'success':
                icon = '✅';
                title = 'موفقیت';
                break;
            case 'warning':
                icon = '⚠️';
                title = 'هشدار';
                break;
            case 'danger':
                icon = '❌';
                title = 'خطا';
                break;
        }
        
        modalBody.innerHTML = `
            <div class="alert alert-${type}">
                <h6>${icon} ${title}</h6>
                <p class="mb-0">${message}</p>
            </div>
        `;
        
        modal.show();
    }

    // مدیریت فرم‌ها
    document.addEventListener('DOMContentLoaded', function() {
        // جلوگیری از ارسال دوباره فرم
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال پردازش...';
                }
            });
        });

        // مدیریت inputهای عددی
        const numberInputs = document.querySelectorAll('input[type="number"]');
        numberInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d]/g, '');
            });
        });
    });
    </script>
</body>
</html>