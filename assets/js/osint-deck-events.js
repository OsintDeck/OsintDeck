/* =========================================================
 * EVENT LISTENERS FOR CARD BUTTONS
 * ========================================================= */
console.log('[OSINT Events] Script loaded');

jQuery(document).ready(function ($) {
    console.log('[OSINT Events] jQuery ready, buttons found:', $('.osint-act-go').length);

    // Event delegation for dynamically generated buttons

    // 1. Analyze button click
    $(document).on('click', '.osint-act-go', function (e) {
        e.preventDefault();
        const card = $(this).closest('.osint-card');
        const toolUrl = card.data('url') || card.find('[data-url]').data('url');

        if (toolUrl) {
            window.open(toolUrl, '_blank');
        } else {
            console.error('No URL found for tool');
        }
    });

    // 2. Share button clicks
    $(document).on('click', '.osint-share-item', function (e) {
        e.preventDefault();
        const action = $(this).data('action');
        const card = $(this).closest('.osint-card');
        const toolName = card.find('.osint-ttl').text();
        const toolUrl = card.data('url') || window.location.href;

        switch (action) {
            case 'copy':
                navigator.clipboard.writeText(toolUrl).then(() => {
                    alert('URL copiada al portapapeles');
                });
                break;
            case 'linkedin':
                window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(toolUrl), '_blank');
                break;
            case 'whatsapp':
                window.open('https://wa.me/?text=' + encodeURIComponent(toolName + ': ' + toolUrl), '_blank');
                break;
            case 'twitter':
                window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(toolName) + '&url=' + encodeURIComponent(toolUrl), '_blank');
                break;
        }
    });

    // 3. Report button click
    $(document).on('click', '.osint-report', function (e) {
        e.preventDefault();
        const card = $(this).closest('.osint-card');
        const toolName = card.find('.osint-ttl').text();

        if (confirm('¿Deseas reportar la herramienta "' + toolName + '"?')) {
            // TODO: Implement report functionality
            alert('Funcionalidad de reporte en desarrollo');
        }
    });

    // 4. Share menu toggle
    $(document).on('click', '.osint-act-share', function (e) {
        e.stopPropagation();
        const menu = $(this).siblings('.osint-share-menu');
        $('.osint-share-menu').not(menu).removeClass('show');
        menu.toggleClass('show');
    });

    // Close share menu when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.osint-share-wrapper').length) {
            $('.osint-share-menu').removeClass('show');
        }
    });
});
