(function ($) {
    'use strict';

    if (!window.imageGeckoAdmin) {
        return;
    }

    function Autocomplete($container) {
        this.$container = $container;
        this.lookup = $container.data('lookup');
        this.removeLabel = $container.data('remove-label') || imageGeckoAdmin.i18n.remove;
        this.placeholder = $container.data('placeholder') || '';
        this.$input = $container.find('.imagegecko-autocomplete__input');
        this.$selections = $container.find('.imagegecko-autocomplete__selections');
        this.$empty = $container.find('.imagegecko-autocomplete__empty');
        this.$summary = $container.find('[data-summary-target="categories"]');
        this.inputName = this.$selections.data('name');

        this.init();
    }

    Autocomplete.prototype.init = function () {
        var self = this;

        if (this.placeholder) {
            this.$input.attr('placeholder', this.placeholder);
        }

        this.$container.on('click', '.imagegecko-pill__remove', function (event) {
            event.preventDefault();
            $(this).closest('.imagegecko-pill').remove();
            self.updateState();
        });

        this.$input.autocomplete({
            minLength: 2,
            delay: 200,
            autoFocus: false,
            source: function (request, response) {
                if (!request.term || request.term.length < 2) {
                    response([]);
                    return;
                }

                self.fetch(request.term)
                    .done(response)
                    .fail(function () {
                        response([]);
                    });
            },
            focus: function (event) {
                event.preventDefault();
            },
            select: function (event, ui) {
                event.preventDefault();
                self.addSelection(ui.item);
                self.$input.val('');
            }
        });

        var widget = this.$input.autocomplete('instance');
        if (widget && widget.options && widget.options.messages) {
            widget.options.messages.noResults = imageGeckoAdmin.i18n.noResults;
        }

        this.updateState();
    };

    Autocomplete.prototype.fetch = function (term) {
        return $.getJSON(imageGeckoAdmin.ajaxUrl, {
            action: 'imagegecko_search_' + this.lookup,
            nonce: imageGeckoAdmin.nonce,
            q: term
        }).then(function (result) {
            if (result && result.success && Array.isArray(result.data)) {
                return result.data;
            }

            return [];
        });
    };

    Autocomplete.prototype.addSelection = function (item) {
        if (!item) {
            return;
        }

        var id = typeof item.id !== 'undefined' ? item.id : item.value;
        if (id === undefined || id === null || id === '') {
            return;
        }

        if (this.$selections.find('input[value="' + id + '"]').length) {
            this.announce(imageGeckoAdmin.i18n.duplicate);
            return;
        }

        var label = item.label || item.text || item.value;
        var $pill = $('<li>', {
            class: 'imagegecko-pill',
            'data-id': id
        });

        if (this.lookup === 'categories' && typeof item.count !== 'undefined') {
            $pill.attr('data-count', item.count);
        }

        $('<span>', {
            class: 'imagegecko-pill__label',
            text: label
        }).appendTo($pill);

        $('<button>', {
            type: 'button',
            class: 'button-link-delete imagegecko-pill__remove',
            'aria-label': this.removeLabel
        }).html('<span aria-hidden="true">&times;</span>').appendTo($pill);

        $('<input>', {
            type: 'hidden',
            name: this.inputName,
            value: id
        }).appendTo($pill);

        this.$selections.append($pill);
        this.updateState();
    };

    Autocomplete.prototype.updateState = function () {
        if (this.$selections.children().length) {
            this.$empty.hide();
        } else {
            this.$empty.show();
        }

        if (this.lookup === 'categories') {
            this.updateSummary();
        }
    };

    Autocomplete.prototype.updateSummary = function () {
        if (!this.$summary.length) {
            return;
        }

        var total = 0;
        this.$selections.children().each(function () {
            var count = parseInt($(this).attr('data-count'), 10);
            if (!Number.isNaN(count)) {
                total += count;
            }
        });

        if (total > 0) {
            var template = total === 1 ? imageGeckoAdmin.i18n.categorySummarySingular : imageGeckoAdmin.i18n.categorySummaryPlural;
            var text = template.replace('%1$d', total).replace('%d', total);
            this.$summary.text(text).show();
        } else {
            this.$summary.text('').hide();
        }
    };

    Autocomplete.prototype.announce = function (message) {
        if (!message) {
            return;
        }

        if (window.wp && wp.a11y && typeof wp.a11y.speak === 'function') {
            wp.a11y.speak(message);
        }
    };

    $(function () {
        $('.imagegecko-autocomplete').each(function () {
            new Autocomplete($(this));
        });
    });
})(jQuery);
