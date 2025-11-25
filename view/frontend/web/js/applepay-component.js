/**
 * Alpine.js CSP-compatible component for Buckaroo Apple Pay
 * This component is registered globally and can be used in templates
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('buckarooApplepay', () => ({
        config: null,
        isClientSide: false,
        
        init() {
            // Get isClientSide from data attribute
            this.isClientSide = this.$el.dataset.isClientSide === 'true';
            
            // Get config from wire
            if (this.$wire && typeof this.$wire.get === 'function') {
                try {
                    this.config = this.$wire.get('config');
                } catch (e) {
                    console.warn('Could not get config from $wire:', e);
                }
            }
            // Initialize Apple Pay
            this.initApplePay();
        },
        
        initApplePay() {
            if (!this.isClientSide) {
                return;
            }
            
            if (window.buckaroo && window.buckaroo.applePay) {
                const jsSdkUrl = this.getJsSdkUrl();
                if (!jsSdkUrl) {
                    console.warn('Apple Pay SDK URL not found');
                    return;
                }
                
                const applePayInstance = window.buckaroo.applePay(jsSdkUrl);
                
                // Merge applePay methods into this component
                if (applePayInstance) {
                    Object.assign(this, applePayInstance);
                    if (this.register && typeof this.register === 'function') {
                        this.register();
                    }
                }
            }
        },
        
        getJsSdkUrl() {
            // Get the URL from the form's data attribute
            return this.$el.dataset.jsSdkUrl || '';
        }
    }));
});

