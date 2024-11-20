jQuery(document).ready(function ($) {
    function updateProgress(type, completed, total, message = '') {
        const progressBar = $(`#${type}-progress-bar`);
        const progressText = $(`#${type}-progress-text`);

        if (message) {
            progressBar.val(100);
            progressText.text(message);
            return;
        }

        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

        progressBar.val(percentage);
        progressText.text(`${completed} of ${total} completed`);

        if (completed >= total) {
            progressText.text('Success!');
            $(`#${type}-progress`).hide();
        }
    }

    function processBatch(type, offset = 0, total = 0) {
        const action = type === 'order' ? 'generate_order_barcodes' : 'generate_product_barcodes';

        $.ajax({
            url: barcode_ajax.ajaxurl,
            method: 'POST',
            data: { action, offset },
            success: function (response) {
                if (response.success) {
                    const { updated, remaining, message } = response.data;

                    if (message) {
                        updateProgress(type, 0, 0, message);
                        return;
                    }

                    if (total === 0) {
                        total = updated + remaining;
                    }

                    const completed = total - remaining;

                    updateProgress(type, completed, total);

                    if (remaining > 0) {
                        processBatch(type, offset + updated, total);
                    }
                } else {
                    console.error('Error:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
            },
        });
    }

    $('#generate-order-barcodes').on('click', function () {
        $('#order-progress').show();
        processBatch('order');
    });

    $('#generate-product-barcodes').on('click', function () {
        $('#product-progress').show();
        processBatch('product');
    });
});
