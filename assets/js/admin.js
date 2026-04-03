/**
 * Admin JavaScript for OSINT Deck
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Card editor functionality
        initCardEditor();

        // Color picker preview
        initColorPicker();

        initMetricsToolNameSuggest();
    });

    /**
     * Initialize card editor
     */
    function initCardEditor() {
        // Add card button
        $('.osint-add-card-btn').on('click', function(e) {
            e.preventDefault();
            addCardRow();
        });

        // Remove card button
        $(document).on('click', '.osint-card-remove', function(e) {
            e.preventDefault();
            $(this).closest('.osint-card-item').remove();
        });

        // Make cards sortable
        if ($.fn.sortable) {
            $('.osint-cards-editor').sortable({
                handle: 'h4',
                placeholder: 'osint-card-placeholder',
                update: function() {
                    updateCardOrder();
                }
            });
        }
    }

    /**
     * Add a new card row
     */
    function addCardRow() {
        var template = `
            <div class="osint-card-item">
                <span class="osint-card-remove dashicons dashicons-no-alt"></span>
                <h4>Nueva Card</h4>
                <table class="form-table">
                    <tr>
                        <th><label>Título</label></th>
                        <td><input type="text" name="card_title[]" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>Descripción</label></th>
                        <td><textarea name="card_desc[]" rows="2" class="large-text"></textarea></td>
                    </tr>
                    <tr>
                        <th><label>URL</label></th>
                        <td><input type="url" name="card_url[]" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label>Tipos de Input</label></th>
                        <td>
                            <select name="card_types[]" multiple size="5">
                                <option value="domain">Domain</option>
                                <option value="ip">IP</option>
                                <option value="url">URL</option>
                                <option value="email">Email</option>
                                <option value="hash">Hash</option>
                                <option value="none">None (Dashboard)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Modo</label></th>
                        <td>
                            <select name="card_mode[]">
                                <option value="manual">Manual</option>
                                <option value="url">URL</option>
                                <option value="api">API</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Pattern (para modo URL)</label></th>
                        <td><input type="text" name="card_pattern[]" class="regular-text" placeholder="{input}"></td>
                    </tr>
                </table>
            </div>
        `;
        
        $('.osint-cards-editor').append(template);
    }

    /**
     * Update card order after sorting
     */
    function updateCardOrder() {
        $('.osint-card-item').each(function(index) {
            $(this).find('input[name="card_order[]"]').val(index);
        });
    }

    /**
     * Initialize color picker preview
     */
    function initColorPicker() {
        $('input[type="color"]').on('change', function() {
            var color = $(this).val();
            $(this).next('.osint-category-color-preview').css('background-color', color);
        });
    }

    /**
     * Autocompletado de nombres en Métricas y reportes (coincidencias parciales vía admin-ajax).
     */
    function initMetricsToolNameSuggest() {
        var $input = $('#m-s');
        var $list = $('#osint-metrics-m-s-listbox');
        if (!$input.length || !$list.length || !$input.closest('.osint-deck-metrics-filters').length) {
            return;
        }

        var cfg = window.osintDeckAdmin;
        if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
            return;
        }

        var debounceMs = 220;
        var minChars = 2;
        var timer = null;
        var activeIndex = -1;

        function setOpen(open) {
            $input.attr('aria-expanded', open ? 'true' : 'false');
            if (open) {
                $list.prop('hidden', false).attr('aria-hidden', 'false');
            } else {
                $list.prop('hidden', true).attr('aria-hidden', 'true').empty();
                activeIndex = -1;
            }
        }

        function closeList() {
            setOpen(false);
        }

        function renderSuggestions(items) {
            $list.empty();
            activeIndex = -1;
            if (!items || !items.length) {
                closeList();
                return;
            }
            items.forEach(function (name, i) {
                var id = 'osint-metrics-suggest-' + i;
                var $li = $('<li role="presentation" />');
                var $btn = $('<button type="button" role="option" class="osint-metrics-suggest__option" />')
                    .attr('id', id)
                    .text(name);
                $li.append($btn);
                $list.append($li);
            });
            setOpen(true);
        }

        function fetchSuggestions(q) {
            $.post(cfg.ajaxUrl, {
                action: 'osint_deck_metrics_tool_suggest',
                nonce: cfg.nonce,
                q: q
            }).done(function (res) {
                if (!res || !res.success || !res.data) {
                    closeList();
                    return;
                }
                var items = res.data.suggestions || [];
                renderSuggestions(items);
            }).fail(function () {
                closeList();
            });
        }

        $input.on('input', function () {
            clearTimeout(timer);
            var q = $.trim($input.val());
            if (q.length < minChars) {
                closeList();
                return;
            }
            timer = setTimeout(function () {
                fetchSuggestions(q);
            }, debounceMs);
        });

        $list.on('mousedown', '.osint-metrics-suggest__option', function (e) {
            e.preventDefault();
        });

        $list.on('click', '.osint-metrics-suggest__option', function () {
            var name = $(this).text();
            $input.val(name);
            closeList();
            $input.trigger('focus');
        });

        $input.on('keydown', function (e) {
            if ($list.prop('hidden')) {
                if (e.key === 'Escape') {
                    closeList();
                }
                return;
            }
            var $opts = $list.find('.osint-metrics-suggest__option');
            var n = $opts.length;
            if (e.key === 'Escape') {
                e.preventDefault();
                closeList();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = activeIndex < n - 1 ? activeIndex + 1 : 0;
                $opts.removeAttr('aria-selected').eq(activeIndex).attr('aria-selected', 'true');
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = activeIndex > 0 ? activeIndex - 1 : n - 1;
                $opts.removeAttr('aria-selected').eq(activeIndex).attr('aria-selected', 'true');
                return;
            }
            if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                $opts.eq(activeIndex).trigger('click');
            }
        });

        $(document).on('click.osintMetricsSuggest', function (e) {
            if (!$(e.target).closest('.osint-metrics-name-field').length) {
                closeList();
            }
        });
    }

})(jQuery);
