const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('promptpay_data', {});

const defaultLabel = __('PromptPay', 'wc-promptpay');

const label = settings.title || defaultLabel;

/**
 * Content component
 */
const Content = () => {
    return createElement('div', {
        className: 'wc-promptpay-payment-method'
    }, settings.description || '');
};

/**
 * Label component
 */
const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return createElement(PaymentMethodLabel, {
        text: label,
    });
};

/**
 * PromptPay payment method config object
 */
const PromptPayPaymentMethod = {
    name: 'promptpay',
    label: createElement(Label),
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod(PromptPayPaymentMethod);
