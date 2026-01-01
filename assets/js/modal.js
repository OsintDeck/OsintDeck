/**
 * Modal functionality for input selection
 */
(function ($) {
    'use strict';

    var selectedInput = null;

    $(document).ready(function () {
        initModal();
    });

    /**
     * Initialize modal
     */
    function initModal() {
        // Close modal on overlay click
        $('.osint-modal-overlay').on('click', function (e) {
            if ($(e.target).hasClass('osint-modal-overlay')) {
                closeModal();
            }
        });

        // Cancel button
        $('.osint-modal-btn.secondary').on('click', function () {
            closeModal();
        });

        // Execute button
        $('.osint-modal-btn.primary').on('click', function () {
            executeWithSelectedInput();
        });

        // Option selection
        $(document).on('click', '.osint-modal-option', function () {
            $('.osint-modal-option').removeClass('selected');
            $(this).addClass('selected');
            selectedInput = $(this).data('input');
        });
    }

    /**
     * Show modal with options
     */
    function showModal(card, inputs) {
        var $modal = $('.osint-modal-overlay');
        var $options = $('.osint-modal-options');

        $options.empty();

        inputs.forEach(function (input) {
            var optionHTML = '<div class="osint-modal-option" data-input="' + input.value + '" data-type="' + input.type + '">';
            optionHTML += '<strong>Analizar ' + input.type + ':</strong> ' + input.value;
            optionHTML += '</div>';
            $options.append(optionHTML);
        });

        $modal.addClass('active');
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('.osint-modal-overlay').removeClass('active');
        selectedInput = null;
    }

    /**
     * Execute card with selected input
     */
    function executeWithSelectedInput() {
        if (!selectedInput) {
            alert('Por favor selecciona un input');
            return;
        }

        // Execute card action with selected input
        console.log('Executing with input:', selectedInput);

        closeModal();
    }

    // Expose functions globally
    window.osintDeckModal = {
        show: showModal,
        close: closeModal
    };

})(jQuery);
