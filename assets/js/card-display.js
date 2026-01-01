/**
 * Card display functionality
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initCardDisplay();
    });

    /**
     * Initialize card display
     */
    function initCardDisplay() {
        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img.lazy').forEach(function (img) {
                imageObserver.observe(img);
            });
        }

        // Sorting functionality
        $('.osint-deck-sort').on('change', function () {
            var sortBy = $(this).val();
            sortCards(sortBy);
        });
    }

    /**
     * Sort cards
     */
    function sortCards(sortBy) {
        var $grid = $('.osint-deck-cards-grid');
        var $cards = $grid.children('.osint-card');

        $cards.sort(function (a, b) {
            switch (sortBy) {
                case 'alphabetical':
                    var titleA = $(a).find('.osint-card-title').text();
                    var titleB = $(b).find('.osint-card-title').text();
                    return titleA.localeCompare(titleB);

                case 'popularity':
                    var popA = $(a).data('clicks') || 0;
                    var popB = $(b).data('clicks') || 0;
                    return popB - popA;

                case 'recent':
                    var dateA = $(a).data('date') || 0;
                    var dateB = $(b).data('date') || 0;
                    return dateB - dateA;

                default:
                    return 0;
            }
        });

        $grid.html($cards);
    }

})(jQuery);
