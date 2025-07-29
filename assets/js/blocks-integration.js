const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, Fragment } = window.wp.element;
const { __ } = window.wp.i18n;
const { decodeEntities } = window.wp.htmlEntities;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('promptpay_n8n_data', {});

/**
 * PromptPay Payment Method Component
 */
const PromptPayComponent = () => {
    return createElement(
        Fragment,
        null,
        createElement(
            'div',
            {
                className: 'wc-block-promptpay-payment-method',
                style: {
                    padding: '20px',
                    border: '2px solid #F5A623',
                    borderRadius: '8px',
                    background: '#fff8f0',
                    animation: 'promptpay-glow 2s ease-in-out infinite alternate'
                }
            },
            createElement(
                'div',
                { style: { textAlign: 'center', marginBottom: '20px' } },
                createElement(
                    'div',
                    {
                        style: {
                            background: 'white',
                            padding: '20px',
                            borderRadius: '8px',
                            display: 'inline-block',
                            boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
                        }
                    },
                    createElement(
                        'div',
                        {
                            style: {
                                width: '200px',
                                height: '200px',
                                background: '#f0f0f0',
                                border: '2px dashed #ccc',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                margin: '0 auto 15px',
                                borderRadius: '8px'
                            }
                        },
                        createElement('span', { style: { color: '#666', fontSize: '14px' } }, 'QR Code Placeholder')
                    ),
                    createElement(
                        'p',
                        { style: { margin: '0', fontSize: '16px', fontWeight: 'bold', color: '#333' } },
                        'ยอดชำระ: ฿' + (window.wcBlocksData?.cartTotals?.total_price || '0.00')
                    )
                )
            ),
            createElement(
                'div',
                { style: { marginBottom: '20px' } },
                createElement(
                    'p',
                    { style: { margin: '0 0 10px', fontWeight: 'bold', color: '#333' } },
                    '📱 วิธีการชำระเงิน:'
                ),
                createElement(
                    'ol',
                    { style: { margin: '0', paddingLeft: '20px', color: '#666', lineHeight: '1.6' } },
                    createElement('li', null, 'เปิดแอปธนาคารบนมือถือ'),
                    createElement('li', null, 'สแกน QR Code ด้านบน'),
                    createElement('li', null, 'ตรวจสอบยอดเงินให้ถูกต้อง'),
                    createElement('li', null, 'ยืนยันการโอนเงิน'),
                    createElement('li', null, 'อัปโหลดสลิปการโอนด้านล่าง')
                )
            ),
            createElement(
                'div',
                {
                    style: {
                        border: '2px dashed #F5A623',
                        borderRadius: '8px',
                        padding: '20px',
                        background: 'white'
                    }
                },
                createElement(
                    'label',
                    {
                        htmlFor: 'promptpay-slip-upload',
                        style: {
                            display: 'block',
                            marginBottom: '10px',
                            fontWeight: 'bold',
                            color: '#333'
                        }
                    },
                    '📎 อัปโหลดสลิปการโอนเงิน:'
                ),
                createElement('input', {
                    type: 'file',
                    id: 'promptpay-slip-upload',
                    accept: 'image/*,.pdf',
                    style: {
                        width: '100%',
                        padding: '10px',
                        border: '1px solid #ddd',
                        borderRadius: '4px',
                        marginBottom: '15px'
                    }
                }),
                createElement(
                    'button',
                    {
                        type: 'button',
                        id: 'promptpay-upload-btn',
                        disabled: true,
                        style: {
                            width: '100%',
                            padding: '12px',
                            background: '#F5A623',
                            color: 'white',
                            border: 'none',
                            borderRadius: '4px',
                            fontWeight: 'bold',
                            cursor: 'pointer',
                            marginBottom: '10px',
                            opacity: '0.6'
                        }
                    },
                    '📤 อัปโหลดสลิป'
                ),
                createElement(
                    'p',
                    { style: { margin: '0', fontSize: '12px', color: '#666' } },
                    'รองรับไฟล์: JPG, PNG, PDF (ขนาดไม่เกิน 5MB)'
                ),
                createElement('div', {
                    id: 'promptpay-upload-status',
                    style: { marginTop: '10px' }
                })
            )
        )
    );
};

/**
 * PromptPay Payment Method Label
 */
const PromptPayLabel = () => {
    return createElement(
        'span',
        { style: { display: 'flex', alignItems: 'center' } },
        createElement('span', { style: { marginRight: '8px' } }, '💳'),
        decodeEntities(settings.title || __('PromptPay', 'woo-promptpay-n8n'))
    );
};

/**
 * Register PromptPay Payment Method
 */
registerPaymentMethod({
    name: 'promptpay_n8n',
    label: createElement(PromptPayLabel),
    content: createElement(PromptPayComponent),
    edit: createElement(PromptPayComponent),
    canMakePayment: () => true,
    ariaLabel: decodeEntities(settings.title || __('PromptPay', 'woo-promptpay-n8n')),
    supports: {
        features: settings.supports || ['products']
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes promptpay-glow {
        0% { 
            border-color: #F5A623; 
            box-shadow: 0 0 10px rgba(245, 166, 35, 0.3);
        }
        100% { 
            border-color: #FF8C00; 
            box-shadow: 0 0 20px rgba(255, 140, 0, 0.5);
        }
    }
    
    @keyframes promptpay-glow-success {
        0% { 
            border-color: #28a745; 
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
        }
        100% { 
            border-color: #00dd55; 
            box-shadow: 0 0 20px rgba(0, 221, 85, 0.5);
        }
    }
    
    .wc-block-promptpay-payment-method button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }
    
    .wc-block-promptpay-payment-method button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
`;
document.head.appendChild(style);

// Initialize upload functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // File upload handling
    document.addEventListener('change', function(e) {
        if (e.target.id === 'promptpay-slip-upload') {
            const uploadBtn = document.getElementById('promptpay-upload-btn');
            if (e.target.files && e.target.files.length > 0) {
                uploadBtn.disabled = false;
                uploadBtn.style.opacity = '1';
                uploadBtn.style.backgroundColor = '#F5A623';
            } else {
                uploadBtn.disabled = true;
                uploadBtn.style.opacity = '0.6';
                uploadBtn.style.backgroundColor = '#ccc';
            }
        }
    });
    
    // Upload button click handling
    document.addEventListener('click', function(e) {
        if (e.target.id === 'promptpay-upload-btn') {
            handleSlipUpload();
        }
    });
});

function handleSlipUpload() {
    const fileInput = document.getElementById('promptpay-slip-upload');
    const statusDiv = document.getElementById('promptpay-upload-status');
    const uploadBtn = document.getElementById('promptpay-upload-btn');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        statusDiv.innerHTML = '<p style="color: #dc3545; margin: 0;">⚠️ กรุณาเลือกไฟล์</p>';
        return;
    }
    
    const file = fileInput.files[0];
    
    // Validate file type
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    if (!validTypes.includes(file.type)) {
        statusDiv.innerHTML = '<p style="color: #dc3545; margin: 0;">⚠️ ไฟล์ต้องเป็น JPG, PNG หรือ PDF</p>';
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        statusDiv.innerHTML = '<p style="color: #dc3545; margin: 0;">⚠️ ไฟล์มีขนาดใหญ่เกิน 5MB</p>';
        return;
    }
    
    // Show uploading status
    uploadBtn.disabled = true;
    uploadBtn.textContent = '🔄 กำลังอัปโหลด...';
    uploadBtn.style.backgroundColor = '#6c757d';
    statusDiv.innerHTML = '<p style="color: #007cba; margin: 0;">🔄 กำลังส่งไฟล์ไปยัง n8n...</p>';
    
    // Simulate n8n upload (replace with actual n8n call)
    setTimeout(function() {
        // Simulate successful verification
        statusDiv.innerHTML = '<p style="color: #28a745; margin: 0;">✅ อัปโหลดสำเร็จ! การชำระเงินได้รับการยืนยัน</p>';
        uploadBtn.textContent = '✅ ยืนยันแล้ว';
        uploadBtn.style.backgroundColor = '#28a745';
        
        // Change glow color to green
        const paymentMethod = document.querySelector('.wc-block-promptpay-payment-method');
        if (paymentMethod) {
            paymentMethod.style.border = '2px solid #28a745';
            paymentMethod.style.background = '#f0fff0';
            paymentMethod.style.animation = 'promptpay-glow-success 2s ease-in-out infinite alternate';
        }
        
        // Set payment method as verified
        window.promptpayVerified = true;
        
        // Dispatch event to notify WooCommerce Blocks
        const event = new CustomEvent('promptpay-verified', {
            detail: { verified: true }
        });
        document.dispatchEvent(event);
        
    }, 2000); // Simulate 2 second upload time
}
