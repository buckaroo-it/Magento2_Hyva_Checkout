/**
 * Alpine.js CSP-compatible component for Buckaroo Apple Pay
 * Follows the same pattern as other Buckaroo components (creditcards, giftcards, etc.)
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('buckarooApplepay', () => {
        return {
            config: null,
            canDisplay: false,
            isClientSide: false,
            
            init() {
                // Get isClientSide from data attribute
                this.isClientSide = this.$el.dataset.isClientSide === 'true';
                
                if (!this.isClientSide) {
                    return;
                }
                
                // Initialize using the same pattern as other components
                if (window.buckaroo && window.buckaroo.applePay) {
                    const jsSdkUrl = this.$el.dataset.jsSdkUrl || '';
                    
                    if (!jsSdkUrl) {
                        console.warn('Apple Pay SDK URL not found');
                        return;
                    }
                    
                    // Get the applePay instance and merge it
                    Object.assign(this, window.buckaroo.applePay(jsSdkUrl));
                    
                    // Explicitly preserve $wire reference (critical for Magewire communication)
                    this.$wire = this.$wire;
                    
                    // Register the component
                    if (this.register && typeof this.register === 'function') {
                        this.register();
                    }
                }
            }
        };
    });
});

