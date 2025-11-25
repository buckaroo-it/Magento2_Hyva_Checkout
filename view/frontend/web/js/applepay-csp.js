/**
 * CSP-Compliant Alpine component for Buckaroo Apple Pay
 * Follows Alpine.js CSP restrictions and Magewire patterns
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('buckarooApplepay', () => {
        return {
            config: null,
            canDisplay: false,
            isClientSide: false,
            
            init() {
                // Read isClientSide from data attribute
                this.isClientSide = this.$el.dataset.isClientSide === 'true';
                
                if (!this.isClientSide) {
                    return;
                }
                
                // Initialize Apple Pay using the buckaroo global function
                if (window.buckaroo && window.buckaroo.applePay) {
                    const jsSdkUrl = this.$el.dataset.jsSdkUrl || '';
                    
                    if (!jsSdkUrl) {
                        console.warn('[Buckaroo Apple Pay] SDK URL not found');
                        return;
                    }
                    
                    // Get the Apple Pay instance and merge its methods
                    const applePayInstance = window.buckaroo.applePay(jsSdkUrl);
                    
                    if (applePayInstance) {
                        // Merge all Apple Pay methods and properties into this component
                        Object.assign(this, applePayInstance);
                        
                        // $wire is automatically available as a magic property from Magewire
                        // No need to explicitly set it - just ensure it's accessible
                        
                        // Call register to initialize Apple Pay
                        if (this.register && typeof this.register === 'function') {
                            this.register();
                        }
                    }
                } else {
                    console.warn('[Buckaroo Apple Pay] window.buckaroo.applePay not available');
                }
            }
        };
    });
});

