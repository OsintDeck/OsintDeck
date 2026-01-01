/**
 * Search functionality for OSINT Deck
 */
(function ($) {
    'use strict';

    var searchTimeout;
    var currentInputs = [];

    $(document).ready(function () {
        initSearch();
    });

    /**
     * Initialize search functionality
     */
    function initSearch() {
        var $searchInput = $('.osint-deck-search-input');

        if (!$searchInput.length) {
            return;
        }

        // Real-time input detection
        $searchInput.on('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                performSearch();
            }, 500);
        });

        // Enter key
        $searchInput.on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                performSearch();
            }
        });
    }

    /**
     * Perform search with AJAX
     */
    function performSearch() {
        var query = $('.osint-deck-search-input').val();

        if (!query || query.length < 2) {
            $('.osint-deck-cards-grid').empty();
            $('.osint-deck-search-hints').html('');
            return;
        }

        // Show loading
        $('.osint-deck-cards-grid').html('<p>Buscando...</p>');

        $.ajax({
            url: osintDeck.ajaxUrl,
            type: 'POST',
            data: {
                action: 'osint_deck_search',
                nonce: osintDeck.nonce,
                query: query
            },
            success: function (response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    $('.osint-deck-cards-grid').html('<p>Error en la búsqueda</p>');
                }
            },
            error: function () {
                $('.osint-deck-cards-grid').html('<p>Error de conexión</p>');
            }
        });
    }

    /**
     * Display search results
     */
    function displayResults(data) {
        var $grid = $('.osint-deck-cards-grid');
        var $hints = $('.osint-deck-search-hints');

        $grid.empty();
        $hints.empty();

        // Show detected inputs
        if (data.inputs && data.inputs.length > 0) {
            var hintsHTML = '<strong>Inputs detectados:</strong> ';
            data.inputs.forEach(function (input) {
                hintsHTML += '<span class="osint-input-detected">' +
                    input.type + ': ' + input.value +
                    '</span>';
            });
            $hints.html(hintsHTML);
        }

        // Show results
        if (data.results && data.results.length > 0) {
            data.results.forEach(function (result) {
                result.cards.forEach(function (card) {
                    var cardHTML = buildCardHTML(card, result.tool);
                    $grid.append(cardHTML);
                });
            });
        } else {
            $grid.html('<p>No se encontraron herramientas compatibles</p>');
        }
    }

    /**
     * Build card HTML
     */
    function buildCardHTML(card, tool) {
        var html = '<div class="osint-card" data-card-id="' + card.id + '" data-tool-id="' + tool.id + '">';

        // Header
        html += '<div class="osint-card-header">';
        if (tool.favicon) {
            html += '<img src="' + tool.favicon + '" class="osint-card-icon" alt="' + tool.name + '">';
        }
        html += '<h3 class="osint-card-title">' + card.title + '</h3>';
        html += '</div>';

        // Description
        if (card.desc) {
            html += '<p class="osint-card-description">' + card.desc + '</p>';
        }

        // Tags
        if (card.tags && card.tags.length > 0) {
            html += '<div class="osint-card-tags">';
            card.tags.forEach(function (tag) {
                html += '<span class="osint-card-tag">' + tag + '</span>';
            });
            html += '</div>';
        }

        // Badges
        if (tool.badges) {
            html += '<div class="osint-card-badges">';
            if (tool.badges.popular) html += '<span class="osint-badge popular">Popular</span>';
            if (tool.badges.new) html += '<span class="osint-badge new">Nuevo</span>';
            if (tool.badges.verified) html += '<span class="osint-badge verified">Verificado</span>';
            if (tool.badges.recommended) html += '<span class="osint-badge recommended">Recomendado</span>';
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    // Card click handler
    $(document).on('click', '.osint-card', function () {
        var cardId = $(this).data('card-id');
        var toolId = $(this).data('tool-id');

        // Track click
        trackCardClick(toolId);

        // Execute card action (to be implemented)
        console.log('Card clicked:', cardId, toolId);
    });

    /**
     * Track card click
     */
    function trackCardClick(toolId) {
        $.ajax({
            url: osintDeck.ajaxUrl,
            type: 'POST',
            data: {
                action: 'osint_deck_track_click',
                nonce: osintDeck.nonce,
                tool_id: toolId
            }
        });
    }

})(jQuery);
