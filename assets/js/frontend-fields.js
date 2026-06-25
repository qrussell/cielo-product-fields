/**
 * Cielo Custom Product Fields - Universal Frontend Engine
 * Handles Live Pricing, Conditional Logic, and UI Modals
 * (This unified file replaces both frontend-pricing.js and conditional-logic.js)
 */

document.addEventListener('DOMContentLoaded', function() {
    const fieldWrapper = document.querySelector('.cielo-product-fields-wrapper');
    if (!fieldWrapper) return;

    const allInputs = fieldWrapper.querySelectorAll('.cielo-input');
    const allWrappers = fieldWrapper.querySelectorAll('.cielo-field-wrapper');
    const previewBox = document.getElementById('cielo-live-price-preview');
    const previewVal = previewBox.querySelector('.val');
    const orderNotice = document.getElementById('cielo-per-order-notice');

    // 1. Live Price & Cart Fee Engine
    function calculateTotals() {
        let perItemTotal = 0;
        let perOrderTotal = 0;

        allInputs.forEach(input => {
            // CRUCIAL: Only calculate if the field's wrapper is visible (passes conditional logic)
            const wrapper = input.closest('.cielo-field-wrapper');
            if (wrapper && wrapper.style.display === 'none') return;

            if (input.hasAttribute('data-price')) {
                const priceValue = parseFloat(input.getAttribute('data-price')) || 0;
                const feeType = input.getAttribute('data-feetype');

                let isActive = false;
                if ((input.type === 'text' || input.type === 'number' || input.type === 'file') && input.value.trim() !== '') isActive = true;
                if ((input.type === 'checkbox' || input.type === 'radio') && input.checked) isActive = true;
                if (input.tagName === 'SELECT' && input.value !== '') isActive = true;

                if (isActive) {
                    if (feeType === 'per_order') {
                        perOrderTotal += priceValue;
                    } else {
                        perItemTotal += priceValue;
                    }
                }
            }
        });

        // Update UI
        if (perItemTotal > 0 || perOrderTotal > 0) {
            previewVal.innerText = perItemTotal.toFixed(2);
            
            if (perOrderTotal > 0) {
                orderNotice.innerText = `+ $${perOrderTotal.toFixed(2)} Flat Setup Fee will be added at checkout.`;
                orderNotice.style.display = 'block';
            } else {
                orderNotice.style.display = 'none';
            }
            previewBox.style.display = 'block';
        } else {
            previewBox.style.display = 'none';
        }
    }

    // 2. Conditional Logic Engine (Phase 7)
    function evaluateConditions() {
        allWrappers.forEach(wrapper => {
            if (wrapper.hasAttribute('data-rules')) {
                try {
                    const rules = JSON.parse(wrapper.getAttribute('data-rules'));
                    const targetInputId = 'cielo_' + rules.target;
                    const targetEl = document.getElementById(targetInputId);
                    
                    let targetVal = '';
                    if (targetEl) {
                        if (targetEl.tagName === 'SELECT' || targetEl.type === 'text') targetVal = targetEl.value;
                        if (targetEl.type === 'radio') {
                            const checkedRadio = document.querySelector(`input[name="${targetEl.name}"]:checked`);
                            if (checkedRadio) targetVal = checkedRadio.value;
                        }
                    }

                    // Toggle Visibility based on the rule
                    if (targetVal === rules.value) {
                        wrapper.style.display = 'block';
                    } else {
                        wrapper.style.display = 'none';
                        // Clear values if hidden so the customer isn't accidentally charged for an invisible field
                        const myInput = wrapper.querySelector('.cielo-input');
                        if(myInput && (myInput.type === 'text' || myInput.tagName === 'SELECT')) myInput.value = '';
                        if(myInput && myInput.type === 'radio') myInput.checked = false;
                    }
                } catch(e) { 
                    console.error("Rule parse error", e); 
                }
            }
        });
        
        // Recalculate prices immediately because hiding a field should drop its price
        calculateTotals(); 
    }

    // 3. File Upload Previews (Phase 6)
    const fileInputs = fieldWrapper.querySelectorAll('.cielo-file-input');
    const modal = document.getElementById('cielo-image-modal');
    const modalImg = document.getElementById('cielo-modal-img');
    const modalClose = document.getElementById('cielo-modal-close');

    fileInputs.forEach(fileInput => {
        fileInput.addEventListener('change', function(e) {
            const previewContainer = this.nextElementSibling;
            previewContainer.innerHTML = ''; // clear old preview

            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (file.type.match('image.*')) {
                    const url = URL.createObjectURL(file);
                    const img = document.createElement('img');
                    img.src = url;
                    img.style.cssText = 'height:60px; border-radius:4px; cursor:zoom-in; border: 2px solid #3b82f6;';
                    
                    // Open Modal on click
                    img.addEventListener('click', () => {
                        modalImg.src = url;
                        modal.style.display = 'flex';
                    });
                    
                    previewContainer.appendChild(img);
                }
            }
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', () => modal.style.display = 'none');
        modal.addEventListener('click', (e) => { if(e.target === modal) modal.style.display = 'none'; });
    }

    // Attach master listeners to all inputs to trigger the conditional check
    allInputs.forEach(input => {
        input.addEventListener('input', () => { evaluateConditions(); });
        input.addEventListener('change', () => { evaluateConditions(); });
    });

    // Run once on initial page load to hide anything that should be hidden by default
    evaluateConditions(); 
});