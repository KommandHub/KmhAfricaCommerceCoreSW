const { PluginBaseClass } = window;

/**
 * KmhAfricaCommerceCore — address-form enhancement.
 *
 * - Division cascade: when the state changes, fetch the divisions under that
 *   country_state and fill the division <select>; hide it when the state has none.
 * - Phone live-check: on blur, normalise the number for the selected country and
 *   show international format (valid) or a warning (invalid). Warn, never block.
 *
 * Scoped by the address prefix (billingAddress / shippingAddress / address), so
 * the register page's two address blocks each drive their own fields.
 */
export default class KmhAddressFormPlugin extends PluginBaseClass {
    init() {
        this._prefix = this.el.dataset.prefix;
        this._phoneUrl = this.el.dataset.phoneUrl;
        this._divisionsUrlTemplate = this.el.dataset.divisionsUrl;

        this._stateSelect = this._field('countryStateId');
        this._countrySelect = this._field('countryId');
        this._phoneInput = this._field('phoneNumber');
        this._divisionSelect = this.el.querySelector('[data-kmh-af-division]');
        this._selectedDivision = this.el.dataset.selectedDivision || '';

        this._lastLoadedState = null;

        if (this._stateSelect && this._divisionSelect) {
            this._stateSelect.addEventListener('change', this._loadDivisions.bind(this));
            this._loadDivisions();
            // On the edit form the saved state is filled asynchronously by core's
            // country-state JS, which sets it programmatically (no change event).
            // Poll briefly so the cascade catches that late value.
            this._watchStateValue();
        }

        if (this._phoneInput && this._countrySelect) {
            // Bootstrap feedback placed right after the phone input, so the
            // message sits under the field (is-valid/is-invalid ~ *-feedback),
            // not stranded at the bottom of the form.
            this._phoneFeedback = document.createElement('div');
            this._phoneInput.insertAdjacentElement('afterend', this._phoneFeedback);
            this._phoneInput.addEventListener('blur', this._checkPhone.bind(this));
        }
    }

    _field(name) {
        return document.querySelector(`[name="${this._prefix}[${name}]"]`);
    }

    _watchStateValue() {
        let tries = 0;
        const timer = setInterval(() => {
            tries += 1;
            const value = this._stateSelect.value;
            if (value && value !== this._lastLoadedState) {
                this._loadDivisions();
            }
            if (tries > 15 || value) {
                clearInterval(timer);
            }
        }, 200);
    }

    _loadDivisions() {
        const stateId = this._stateSelect ? this._stateSelect.value : '';
        if (!stateId) {
            this._lastLoadedState = null;
            this._hideDivision(false);
            return;
        }

        if (stateId === this._lastLoadedState) {
            return;
        }
        this._lastLoadedState = stateId;

        const url = this._divisionsUrlTemplate.replace('__STATE_ID__', encodeURIComponent(stateId));
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((data) => this._fillDivisions(data.divisions || []))
            .catch(() => this._hideDivision(true));
    }

    _fillDivisions(divisions) {
        const select = this._divisionSelect;
        const previous = select.value;

        while (select.options.length > 1) {
            select.remove(1);
        }

        divisions.forEach((division) => {
            const option = document.createElement('option');
            option.value = division.code;
            option.textContent = division.name;
            select.appendChild(option);
        });

        if (divisions.length === 0) {
            this._hideDivision(false);
            return;
        }

        select.disabled = false;
        const group = select.closest('.form-group');
        if (group) group.classList.remove('d-none');

        // Prefer the shopper's in-session choice; fall back to the saved value on
        // first load (edit form). Consume the saved value so it applies once.
        const target = previous || this._selectedDivision;
        this._selectedDivision = '';
        if (target && [...select.options].some((option) => option.value === target)) {
            select.value = target;
        }
    }

    /**
     * Hide the division field. A disabled select is not submitted, so the server
     * keeps the stored division (used when the lookup failed). An enabled, empty
     * one submits "" and clears it — right when the state has no divisions, so a
     * division from a previously chosen state cannot linger.
     */
    _hideDivision(disable) {
        const select = this._divisionSelect;
        while (select.options.length > 1) {
            select.remove(1);
        }
        select.value = '';
        select.disabled = disable;
        const group = select.closest('.form-group');
        if (group) group.classList.add('d-none');
    }

    _checkPhone() {
        const number = this._phoneInput.value.trim();
        if (!number) {
            this._setFeedback('', null);
            return;
        }

        const body = new FormData();
        body.append('number', number);
        body.append('countryId', this._countrySelect.value || '');

        fetch(this._phoneUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
            .then((response) => response.json())
            .then((result) => {
                if (result.enabled === false) {
                    return;
                }
                if (result.valid) {
                    this._setFeedback(result.international || result.e164, true);
                } else {
                    this._setFeedback(result.warning || '', false);
                }
            })
            .catch(() => {});
    }

    _setFeedback(text, valid) {
        if (!this._phoneInput || !this._phoneFeedback) {
            return;
        }

        // Bootstrap validation state on the field; the matching *-feedback is a
        // sibling of the input, so it renders directly beneath it.
        this._phoneInput.classList.remove('is-valid', 'is-invalid');
        this._phoneFeedback.className = '';
        this._phoneFeedback.textContent = text || '';

        if (!text) {
            return;
        }

        if (valid === true) {
            this._phoneInput.classList.add('is-valid');
            this._phoneFeedback.className = 'valid-feedback d-block';
        } else if (valid === false) {
            this._phoneInput.classList.add('is-invalid');
            this._phoneFeedback.className = 'invalid-feedback d-block';
        }
    }
}
