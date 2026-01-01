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

})(jQuery);
