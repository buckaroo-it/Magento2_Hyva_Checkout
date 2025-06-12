document.addEventListener('DOMContentLoaded', function () {

    window.updatePosition = function () {
        // Select the elements by their class names
        const alreadyPaidElement = document.querySelector('.remaining_amount');
        const grandTotalElement = document.querySelector('.grand_total');

        // Ensure both elements exist before trying to move them
        if (alreadyPaidElement && grandTotalElement) {
            // Move the 'already-paid' block after the 'grand-total' block
            grandTotalElement.insertAdjacentElement('afterend', alreadyPaidElement);
        }
    };

    // Call updatePosition on page load
    updatePosition();

    // Reapply position when payment method changes
    document.querySelectorAll('input[name="payment-method-option"]').forEach((input) => {
        input.addEventListener('change', function () {
            setTimeout(function () {
                updatePosition();
            }, 2000);
        });
    });
});