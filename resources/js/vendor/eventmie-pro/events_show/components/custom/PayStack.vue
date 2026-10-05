<template>
    <div v-if="pay_stack" class="paystack-checkout">
        <p class="text-muted">Paystack {{ mode }} mode</p>
        <form v-if="!reference" @submit.prevent="startMobileMoney">
            <div class="form-group">
                <label for="paystack-phone">Safaricom M-Pesa phone number</label>
                <input
                    id="paystack-phone"
                    v-model.trim="phone"
                    class="form-control"
                    type="tel"
                    autocomplete="tel"
                    placeholder="07XX XXX XXX or +2547XX XXX XXX"
                    required
                >
            </div>
            <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
            <button class="btn btn-success btn-lg btn-block" type="submit" :disabled="loading">
                {{ loading ? 'Sending prompt…' : 'Pay with M-Pesa' }}
            </button>
        </form>

        <div v-else aria-live="polite">
            <p>{{ statusMessage }}</p>
            <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
            <button v-if="!loading" class="btn btn-outline-primary" type="button" @click="resetPayment">
                Change number / retry
            </button>
        </div>

        <form :action="redirectRoute" method="POST" class="mt-3">
            <input type="hidden" name="_token" :value="csrfToken">
            <button class="btn btn-link" type="submit" :disabled="loading">Use Paystack checkout instead</button>
        </form>
    </div>
</template>

<script>
export default {
    data() {
        return {
            phone: '',
            pay_stack: 0,
            loading: false,
            reference: null,
            statusUrl: null,
            redirectRoute: null,
            csrfToken: null,
            mode: 'test',
            statusMessage: 'Check your phone and enter your M-Pesa PIN.',
            error: null,
            pollTimer: null,
            pollCount: 0,
        }
    },

    beforeDestroy() {
        this.stopPolling();
    },

    methods: {
        PayStack(bookingData) {
            this.error = null;
            this.loading = true;
            axios.post(route('eventmie.bookings_book_tickets'), bookingData)
                .then((response) => {
                    const payment = response.data && response.data.paystack;
                    if (!response.data.status || !payment || !payment.paystack) {
                        this.error = response.data.message || 'Unable to start checkout.';
                        return;
                    }

                    this.redirectRoute = payment.redirect_route;
                    this.csrfToken = payment.csrf_token;
                    this.mode = payment.mode;
                    this.pay_stack = 1;
                    this.$parent.close();
                })
                .catch((error) => {
                    const errors = Vue.helpers.axiosErrors(error);
                    this.error = errors.length ? errors.join(' ') : 'Unable to start checkout.';
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        startMobileMoney() {
            this.loading = true;
            this.error = null;

            axios.post(route('paystack.charge'), { phone: this.phone })
                .then((response) => {
                    this.reference = response.data.reference;
                    this.statusUrl = response.data.status_url;
                    this.statusMessage = response.data.message;
                    this.pollCount = 0;
                    this.pollStatus();
                })
                .catch((error) => {
                    this.error = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : 'Unable to send the M-Pesa prompt. Please check the number and retry.';
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        pollStatus() {
            if (!this.statusUrl || this.pollCount >= 45) {
                this.stopPolling();
                this.statusMessage = 'Payment is still pending. You can retry or use Paystack checkout.';
                return;
            }

            this.pollCount += 1;
            axios.get(this.statusUrl)
                .then((response) => {
                    if (response.data.status === true && response.data.url) {
                        window.location.assign(response.data.url);
                        return;
                    }
                    if (response.data.status === 'success') {
                        this.stopPolling();
                        this.statusMessage = response.data.message || 'Payment confirmed.';
                        return;
                    }
                    if (['failed', 'expired', 'mismatch', 'refunded'].includes(response.data.status)) {
                        this.stopPolling();
                        this.error = 'Payment was not confirmed. Please retry or use Paystack checkout.';
                        return;
                    }

                    this.pollTimer = setTimeout(this.pollStatus, 4000);
                })
                .catch(() => {
                    this.statusMessage = 'Payment status is temporarily unavailable; checking again shortly.';
                    this.pollTimer = setTimeout(this.pollStatus, 4000);
                });
        },

        resetPayment() {
            this.stopPolling();
            this.reference = null;
            this.statusUrl = null;
            this.error = null;
            this.statusMessage = 'Check your phone and enter your M-Pesa PIN.';
        },

        stopPolling() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },
    },
}
</script>
