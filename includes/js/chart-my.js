// نمودارهای داشبورد
document.addEventListener('DOMContentLoaded', function() {
    // نمودار فروش ماهانه
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        fetch('../modules/reports.php?action=sales_chart')
            .then(response => response.json())
            .then(data => {
                new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'فروش ماهانه',
                            data: data.values,
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'آمار فروش 30 روز گذشته'
                            }
                        }
                    }
                });
            });
    }

    // نمودار موجودی محصولات
    const inventoryCtx = document.getElementById('inventoryChart');
    if (inventoryCtx) {
        fetch('../modules/reports.php?action=inventory_chart')
            .then(response => response.json())
            .then(data => {
                new Chart(inventoryCtx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'موجودی',
                            data: data.values,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'موجودی محصولات'
                            }
                        }
                    }
                });
            });
    }
});

// توابع کمکی برای نمودارها
function formatNumber(number) {
    return new Intl.NumberFormat('fa-IR').format(number);
}

function formatCurrency(amount) {
    return formatNumber(amount) + ' تومان';
}