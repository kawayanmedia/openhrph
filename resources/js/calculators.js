const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const decimal = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function parseAmount(value) {
    const numeric = Number(String(value ?? '').replace(/[^0-9.-]/g, ''));

    return Number.isFinite(numeric) ? Math.max(numeric, 0) : 0;
}

function formatAmountInput(input) {
    if (input.value.trim() === '') {
        return;
    }

    input.value = decimal.format(parseAmount(input.value));
}

function collectAmounts(form) {
    const payload = {};

    form.querySelectorAll('[data-amount]').forEach((input) => {
        payload[input.name] = parseAmount(input.value);
    });

    return payload;
}

async function postCalculator(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            Object.values(body.errors ?? {}).flat().find(Boolean) ||
            body.message ||
            'Unable to calculate right now. Please check your inputs and try again.';

        throw new Error(message);
    }

    return body.data;
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value;
    }
}

function setResultVisibility(id, visible) {
    document.getElementById(id)?.classList.toggle('hidden', !visible);
}

function showError(id, message) {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    element.textContent = message;
    element.classList.toggle('hidden', message === '');
}

function debounce(callback, wait) {
    let timer;

    const debounced = (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => callback(...args), wait);
    };

    debounced.cancel = () => window.clearTimeout(timer);

    return debounced;
}

function setupCalculator({ formId, errorId, resultId, endpoint, buildPayload, render }) {
    const form = document.getElementById(formId);

    if (!form) {
        return;
    }

    const submit = form.querySelector('[data-submit]');
    const defaultSubmitLabel = submit?.textContent ?? 'Calculate';
    let requestId = 0;

    const calculate = async ({ silent = false } = {}) => {
        const currentRequest = ++requestId;

        showError(errorId, '');

        if (submit && !silent) {
            submit.disabled = true;
            submit.textContent = 'Calculating…';
        }

        try {
            const data = await postCalculator(endpoint, buildPayload(form));

            if (currentRequest !== requestId) {
                return;
            }

            render(data);
            setResultVisibility(resultId, true);
        } catch (error) {
            if (currentRequest !== requestId) {
                return;
            }

            setResultVisibility(resultId, false);
            showError(errorId, error.message);
        } finally {
            if (submit && !silent) {
                submit.disabled = false;
                submit.textContent = defaultSubmitLabel;
            }
        }
    };

    const debouncedCalculate = debounce(() => calculate({ silent: true }), 650);

    form.querySelectorAll('[data-amount]').forEach((input) => {
        input.addEventListener('blur', () => formatAmountInput(input));
        input.addEventListener('input', debouncedCalculate);
    });

    form.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', debouncedCalculate);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        debouncedCalculate.cancel();
        calculate();
    });

    form.addEventListener('reset', () => {
        requestId++;
        debouncedCalculate.cancel();
        showError(errorId, '');
        setResultVisibility(resultId, false);

        if (submit) {
            submit.disabled = false;
            submit.textContent = defaultSubmitLabel;
        }
    });
}

setupCalculator({
    formId: 'tax-calculator-form',
    errorId: 'tax-calculator-error',
    resultId: 'tax-calculator-result',
    endpoint: '/api/calculators/withholding-tax',
    buildPayload: (form) => ({
        period: form.querySelector('[name="period"]')?.value ?? 'monthly',
        ...collectAmounts(form),
    }),
    render: (data) => {
        setText('tax-result-withholding', currency.format(data.withholding_tax));
        setText('tax-result-summary', `${data.period_label} payroll period`);
        setText('tax-result-regular', currency.format(data.regular_compensation));
        setText('tax-result-supplementary', currency.format(data.supplementary_compensation));
        setText('tax-result-gross', currency.format(data.gross_compensation_income));
        setText('tax-result-non-taxable', currency.format(data.non_taxable_benefits));
        setText('tax-result-taxable', currency.format(data.taxable_compensation_income));
        setText('tax-result-rate', `${(data.effective_tax_rate * 100).toFixed(2)}%`);
    },
});

setupCalculator({
    formId: 'take-home-form',
    errorId: 'take-home-error',
    resultId: 'take-home-result',
    endpoint: '/api/calculators/take-home-pay',
    buildPayload: (form) => ({
        sector: form.querySelector('[name="sector"]')?.value ?? 'Private',
        period: form.querySelector('[name="period"]')?.value ?? 'monthly',
        ...collectAmounts(form),
    }),
    render: (data) => {
        const agency = data.deductions.social_insurance.agency;

        setText('take-home-net', currency.format(data.net_pay));
        setText('take-home-period-label', `${data.sector} · ${data.period_label} payroll period`);
        setText('take-home-gross', currency.format(data.gross_pay));
        setText('take-home-regular', currency.format(data.regular_compensation));
        setText('take-home-supplementary', currency.format(data.supplementary_compensation));
        setText('take-home-non-taxable', currency.format(data.non_taxable_benefits));
        setText('take-home-taxable', currency.format(data.taxable_compensation_income));
        setText('take-home-social-insurance-label', `${agency} (employee share)`);
        setText('take-home-social-insurance', currency.format(data.deductions.social_insurance.employee));
        setText('take-home-philhealth', currency.format(data.deductions.philhealth.employee));
        setText('take-home-pagibig', currency.format(data.deductions.pagibig.employee));
        setText('take-home-tax', currency.format(data.deductions.withholding_tax));
        setText('take-home-total-deductions', currency.format(data.deductions.total_employee));
        setText('take-home-social-insurance-employer-label', `${agency} (employer share)`);
        setText('take-home-social-insurance-employer', currency.format(data.employer.social_insurance));
        setText('take-home-philhealth-employer', currency.format(data.employer.philhealth));
        setText('take-home-pagibig-employer', currency.format(data.employer.pagibig));
        setText('take-home-employer-cost', currency.format(data.employer.total_cost));
    },
});

const themeToggle = document.querySelector('[data-theme-toggle]');

themeToggle?.addEventListener('click', () => {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.theme = isDark ? 'dark' : 'light';
});
