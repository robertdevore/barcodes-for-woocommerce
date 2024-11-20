jQuery(document).ready(function ($) {
    $('.color-field').wpColorPicker();

    // Manual Input Barcode Scanning
    $('#scan-barcode').on('click', function () {
        const barcode = $('#barcode-input').val();
        if (!barcode) {
            alert('Please enter or scan a barcode.');
            return;
        }
        fetchOrderData(barcode);
    });

    // Fetch Order Data via AJAX
    function fetchOrderData(barcode) {
        $.ajax({
            url: barcode_ajax.ajaxurl,
            method: 'POST',
            data: {
                action: 'lookup_barcode',
                barcode: barcode,
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    $('#scan-results').html(`
                        <div class="order-receipt">
                            <h3>Order #${data.order_id}</h3>
                            <p><strong>Order Date:</strong> ${data.order_date}</p>
                            <p><strong>Status:</strong> 
                                <span class="order-status" style="color: ${data.status_color}; font-weight: bold;">
                                    ${capitalizeFirstLetter(data.order_status)}
                                </span>
                            </p>
                            <h4>Customer Information</h4>
                            <p><strong>Name:</strong> 
                                <a href="${data.customer_link}" target="_blank">${data.customer_name}</a>
                            </p>
                            <p><strong>Phone:</strong> ${data.customer_phone}</p>
                            <p><strong>Email:</strong> ${data.customer_email}</p>
                            <p><strong>Address:</strong><br>${data.customer_address}</p>
                            <a href="${data.order_link}" target="_blank" class="view-order-link">View Order</a>
                        </div>
                    `);
                } else {
                    $('#scan-results').html(`<p>${response.data}</p>`);
                }
            },
            error: function () {
                $('#scan-results').html('<p>An error occurred. Please try again.</p>');
            },
        });
    }

    // Capitalize the first letter of the order status
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Initialize Camera Scanner
    const html5QrCode = new Html5Qrcode("reader");

    let scannerActive = false;

    $('#toggle-scanner').on('click', function () {
        if (scannerActive) {
            html5QrCode.stop().then(() => {
                $('#reader').html(''); // Clear scanner view
                $('#toggle-scanner').text('Start Scanner'); // Update button text
                scannerActive = false;
            }).catch((err) => {
                console.error('Failed to stop scanner:', err);
            });
        } else {
            Html5Qrcode.getCameras().then((devices) => {
                if (devices && devices.length) {
                    html5QrCode.start(
                        { facingMode: "environment" }, // Use rear-facing camera
                        {
                            fps: 10, // Scans per second
                            qrbox: { width: 250, height: 250 }, // Scanning box size
                        },
                        (decodedText) => {
                            fetchOrderData(decodedText); // Automatically fetch order data
                        },
                        (errorMessage) => {
                            console.log("Scanning error:", errorMessage);
                        }
                    );
                    $('#toggle-scanner').text('Stop Scanner'); // Update button text
                    scannerActive = true;
                } else {
                    $('#camera-scanner').html('<p>No cameras found on this device.</p>');
                }
            }).catch((err) => {
                console.error(err);
                $('#camera-scanner').html('<p>Error initializing camera scanner.</p>');
            });
        }
    });
});
