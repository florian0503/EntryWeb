const checkboxes = document.querySelectorAll('.devis-checkbox');
const totalHtEl = document.getElementById('total-ht');
const totalMonthlyEl = document.getElementById('total-monthly');
const monthlySection = document.getElementById('monthly-section');
const categorySubtotals = document.querySelectorAll('.category-subtotal');
const selectedListEl = document.getElementById('selected-items-list');
const offerLabels = document.querySelectorAll('.offer-option:not(.subscription-option)');
const offerRadios = document.querySelectorAll('input[name="selected_offer"]');
const subscriptionLabels = document.querySelectorAll('.subscription-option');
const subscriptionRadios = document.querySelectorAll('input[name="selected_subscription"]');
const mockupCheckbox = document.querySelector('input[name="include_mockup"]');
const mockupLabel = document.getElementById('mockup-label');
const extraHoursInput = document.querySelector('input[name="extra_hours_count"]');
const extraHoursLabel = document.getElementById('extra-hours-label');

let selectedOfferPrice = 0;
let selectedOfferName = '';
let selectedSubscriptionPrice = 0;
let selectedSubscriptionName = '';
let selectedMockupPrice = 0;
let selectedMockupName = '';
let selectedExtraHoursPrice = 0;
let selectedExtraHoursName = '';

function formatPrice(n) {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function recalculate() {
    let ht = selectedOfferPrice;
    let monthly = selectedSubscriptionPrice;
    const catTotals = {};
    let selectedHtml = '';

    if (selectedOfferName) {
        selectedHtml += '<div class="d-flex justify-content-between small mb-1 fw-bold text-primary"><span>' + selectedOfferName + '</span><span>' + (selectedOfferPrice > 0 ? formatPrice(selectedOfferPrice) + ' \u20ac' : 'Sur devis') + '</span></div>';
    }

    if (selectedSubscriptionName) {
        selectedHtml += '<div class="d-flex justify-content-between small mb-1 fw-bold text-primary"><span>' + selectedSubscriptionName + '</span><span>' + formatPrice(selectedSubscriptionPrice) + ' \u20ac/mois</span></div>';
    }

    if (selectedMockupName) {
        ht += selectedMockupPrice;
        selectedHtml += '<div class="d-flex justify-content-between small mb-1 fw-bold text-primary"><span>' + selectedMockupName + '</span><span>' + formatPrice(selectedMockupPrice) + ' \u20ac</span></div>';
    }

    if (selectedExtraHoursName) {
        ht += selectedExtraHoursPrice;
        selectedHtml += '<div class="d-flex justify-content-between small mb-1 fw-bold text-primary"><span>' + selectedExtraHoursName + '</span><span>' + formatPrice(selectedExtraHoursPrice) + ' \u20ac</span></div>';
    }

    checkboxes.forEach(function(cb) {
        if (cb.checked) {
            const price = parseFloat(cb.dataset.price);
            const cat = cb.dataset.category;
            const label = cb.closest('.devis-item-row').querySelector('strong').textContent;
            if (cb.dataset.monthly === '1') {
                monthly += price;
                selectedHtml += '<div class="d-flex justify-content-between small mb-1"><span>' + label + '</span><span>' + formatPrice(price) + ' \u20ac/mois</span></div>';
            } else {
                ht += price;
                selectedHtml += '<div class="d-flex justify-content-between small mb-1"><span>' + label + '</span><span>' + formatPrice(price) + ' \u20ac</span></div>';
            }
            catTotals[cat] = (catTotals[cat] || 0) + price;
        }
    });

    totalHtEl.textContent = formatPrice(ht);
    totalMonthlyEl.textContent = formatPrice(monthly);
    monthlySection.style.display = monthly > 0 ? 'block' : 'none';

    if (selectedHtml) {
        selectedListEl.innerHTML = selectedHtml;
    } else {
        selectedListEl.innerHTML = '<p class="text-muted mb-0">Aucune prestation s\u00e9lectionn\u00e9e</p>';
    }

    categorySubtotals.forEach(function(el) {
        const cat = el.dataset.category;
        const val = catTotals[cat] || 0;
        el.textContent = formatPrice(val) + ' \u20ac';
        el.className = 'badge category-subtotal ' + (val > 0 ? 'bg-primary' : 'bg-secondary');
    });
}

offerLabels.forEach(function(label) {
    label.addEventListener('click', function() {
        offerLabels.forEach(l => l.classList.remove('selected'));
        this.classList.add('selected');
        const radio = this.querySelector('input[type=radio]');
        radio.checked = true;
        selectedOfferPrice = parseFloat(radio.dataset.offerPrice) || 0;
        selectedOfferName = radio.dataset.offerName;
        recalculate();
    });
});

subscriptionLabels.forEach(function(label) {
    label.addEventListener('click', function() {
        subscriptionLabels.forEach(l => l.classList.remove('selected'));
        this.classList.add('selected');
        const radio = this.querySelector('input[type=radio]');
        radio.checked = true;
        selectedSubscriptionPrice = parseFloat(radio.dataset.subscriptionPrice) || 0;
        selectedSubscriptionName = radio.dataset.subscriptionName;
        recalculate();
    });
});

if (mockupCheckbox && mockupLabel) {
    mockupCheckbox.addEventListener('change', function() {
        selectedMockupPrice = this.checked ? (parseFloat(this.dataset.mockupPrice) || 0) : 0;
        selectedMockupName = this.checked ? (this.dataset.mockupName || 'Maquette') : '';
        mockupLabel.classList.toggle('selected', this.checked);
        recalculate();
    });
}

if (extraHoursInput && extraHoursLabel) {
    extraHoursInput.addEventListener('input', function() {
        const hours = Math.max(0, parseInt(this.value || '0', 10) || 0);
        const hourlyPrice = parseFloat(this.dataset.hourlyPrice) || 30;
        this.value = String(hours);
        selectedExtraHoursPrice = hours * hourlyPrice;
        selectedExtraHoursName = hours > 0 ? ((this.dataset.hourlyName || 'Heures supplémentaires') + ' (' + hours + 'h)') : '';
        extraHoursLabel.classList.toggle('selected', hours > 0);
        recalculate();
    });
}

function updateCategoryLocks() {
    const categories = {};
    checkboxes.forEach(function(cb) {
        const cat = cb.dataset.category;
        if (!categories[cat]) categories[cat] = [];
        categories[cat].push(cb);
    });

    Object.values(categories).forEach(function(cbs) {
        const first = cbs[0];
        cbs.slice(1).forEach(function(cb) {
            const row = cb.closest('.devis-item-row');
            if (!first.checked) {
                cb.checked = false;
                cb.disabled = true;
                row.classList.remove('selected');
                row.style.opacity = '0.4';
                row.style.pointerEvents = 'none';
            } else {
                cb.disabled = false;
                row.style.opacity = '';
                row.style.pointerEvents = '';
            }
        });
    });
}

checkboxes.forEach(function(cb) {
    cb.addEventListener('change', function() {
        this.closest('.devis-item-row').classList.toggle('selected', this.checked);
        updateCategoryLocks();
        recalculate();
    });
});

updateCategoryLocks();
