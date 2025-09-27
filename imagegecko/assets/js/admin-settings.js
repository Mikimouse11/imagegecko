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

    function GenerationRunner(options) {
        options = options || {};

        this.ajaxUrl = options.ajaxUrl || '';
        this.nonce = options.nonce || '';
        this.hasApiKey = !!options.hasApiKey;
        this.i18n = options.i18n || {};

        // Debug logging for initialization
        console.log('ImageGecko: Initializing GenerationRunner with options:', {
            ajaxUrl: this.ajaxUrl,
            hasNonce: !!this.nonce,
            hasApiKey: this.hasApiKey
        });

        this.$button = $('#imagegecko-go-button');
        if (!this.$button.length) {
            console.warn('ImageGecko: GO button not found');
            return;
        }

        this.$progress = $('#imagegecko-progress');
        this.$summary = this.$progress.find('.imagegecko-progress__summary');
        this.$list = this.$progress.find('.imagegecko-progress__list');

        this.queue = [];
        this.items = {};
        this.total = 0;
        this.completed = 0;
        this.failed = 0;
        this.skipped = 0;

        this.bindEvents();
    }

    GenerationRunner.prototype.bindEvents = function () {
        var self = this;

        this.$button.on('click', function (event) {
            event.preventDefault();

            if (!self.hasApiKey) {
                window.alert(self.i18n.stepLocked || 'API key missing.');
                return;
            }

            if ($(this).prop('disabled')) {
                return;
            }

            self.start();
        });
    };

    GenerationRunner.prototype.setButtonState = function (state) {
        if ('busy' === state) {
            this.$button.prop('disabled', true).text(this.i18n.going || 'Working…');
        } else {
            this.$button.prop('disabled', false).text(this.i18n.go || 'GO');
        }

        this.$button.attr('data-state', state);
    };

    GenerationRunner.prototype.resetProgress = function () {
        this.queue = [];
        this.items = {};
        this.total = 0;
        this.completed = 0;
        this.failed = 0;
        this.skipped = 0;

        this.$summary.text('');
        this.$list.empty();
        this.$progress.show();
    };

    GenerationRunner.prototype.start = function () {
        var self = this;
        
        console.log('ImageGecko: Starting generation workflow');

        this.setButtonState('busy');
        this.resetProgress();

        $.post(this.ajaxUrl, {
            action: 'imagegecko_start_generation',
            nonce: this.nonce
        }).done(function (response) {
            console.log('ImageGecko: Start generation response:', response);
            
            if (!response || !response.success || !response.data || !Array.isArray(response.data.products)) {
                console.error('ImageGecko: Invalid response structure:', response);
                self.handleStartError(response);
                return;
            }

            self.queue = response.data.products.slice();
            self.total = self.queue.length;

            if (!self.total) {
                var message = response.data.message || self.i18n.startError;
                console.log('ImageGecko: No products found, message:', message);
                self.showMessage(message);
                self.setButtonState('idle');
                return;
            }

            console.log('ImageGecko: Starting generation for', self.total, 'products');
            self.renderInitialList();
            self.updateSummary();
            self.processNext();
        }).fail(function (xhr, status, error) {
            console.error('ImageGecko: Start generation AJAX failed:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });
            self.handleStartError();
        });
    };

    GenerationRunner.prototype.handleStartError = function (response) {
        var message = this.i18n.startError || 'Unable to start.';
        
        // Try to extract more specific error message from response
        if (response && response.data && response.data.message) {
            message = response.data.message;
        } else if (response && !response.success && response.data && typeof response.data === 'string') {
            message = response.data;
        }
        
        console.error('ImageGecko: Generation start error:', message);
        this.showMessage(message);
        this.setButtonState('idle');
    };

    GenerationRunner.prototype.showMessage = function (message) {
        this.$progress.show();
        this.$summary.text(message || '');
        this.$list.empty();
    };

    GenerationRunner.prototype.renderInitialList = function () {
        var self = this;

        this.$list.empty();
        this.items = {};

        this.queue.slice().forEach(function (product) {
            var $item = $('<li>', {
                class: 'imagegecko-progress__item',
                'data-product-id': product.id
            });

            $('<span>', {
                class: 'imagegecko-progress__label',
                text: product.label
            }).appendTo($item);

            $('<span>', {
                class: 'imagegecko-progress__status',
                text: self.i18n.queued || 'Queued'
            }).appendTo($item);

            $('<span>', {
                class: 'imagegecko-progress__message'
            }).appendTo($item);

            self.$list.append($item);
            self.items[product.id] = $item;
        });
    };

    GenerationRunner.prototype.processNext = function () {
        var self = this;

        if (!this.queue.length) {
            this.finish();
            return;
        }

        var product = this.queue.shift();
        var productId = product.id;

        this.updateItemStatus(productId, this.i18n.processing || 'Processing…', 'is-processing');

        $.post(this.ajaxUrl, {
            action: 'imagegecko_process_product',
            nonce: this.nonce,
            product_id: productId
        }).done(function (response) {
            console.log('ImageGecko: Process product response for', productId, ':', response);
            
            if (!response || !response.success || !response.data) {
                console.error('ImageGecko: Invalid process response for product', productId, ':', response);
                self.handleProcessFailure(productId);
                return;
            }

            self.handleProcessResult(productId, response.data);
        }).fail(function (xhr, status, error) {
            console.error('ImageGecko: Process product AJAX failed for', productId, ':', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });
            self.handleProcessFailure(productId);
        });
    };

    GenerationRunner.prototype.handleProcessFailure = function (productId) {
        this.handleProcessResult(productId, {
            success: false,
            status: 'failed',
            message: this.i18n.startError || 'Request failed.'
        });
    };

    GenerationRunner.prototype.handleProcessResult = function (productId, data) {
        data = data || {};

        var status = data.status || (data.success ? 'completed' : 'failed');
        var message = data.message || '';
        var statusText = '';
        var cssClass = '';

        switch (status) {
            case 'completed':
                statusText = this.i18n.completed || 'Completed';
                cssClass = 'is-success';
                this.completed++;
                break;
            case 'skipped':
                statusText = this.i18n.skipped || 'Skipped';
                cssClass = 'is-skipped';
                this.skipped++;
                break;
            case 'blocked':
            case 'failed':
            default:
                statusText = this.i18n.failed || 'Failed';
                cssClass = 'is-error';
                this.failed++;
                break;
        }

        this.updateItemStatus(productId, statusText, cssClass, message);
        this.updateSummary();
        this.processNext();
    };

    GenerationRunner.prototype.updateItemStatus = function (productId, text, cssClass, message) {
        var $item = this.items[productId];
        if (!$item || !$item.length) {
            return;
        }

        var $status = $item.find('.imagegecko-progress__status');
        $status.text(text || '');

        $item.removeClass('is-success is-error is-processing is-skipped');
        if (cssClass) {
            $item.addClass(cssClass);
        }

        if (message) {
            $item.find('.imagegecko-progress__message').text(message);
        } else {
            $item.find('.imagegecko-progress__message').text('');
        }
    };

    GenerationRunner.prototype.updateSummary = function (finished) {
        if (!this.total) {
            this.$summary.text('');
            return;
        }

        if (finished) {
            var finishedTemplate = this.i18n.summaryFinished || 'All done! %d products enhanced.';
            this.$summary.text(finishedTemplate.replace('%d', this.completed));
            return;
        }

        var progressTemplate = this.i18n.summaryProgress || '%1$d of %2$d products completed';
        this.$summary.text(progressTemplate.replace('%1$d', this.completed).replace('%2$d', this.total));
    };

    GenerationRunner.prototype.finish = function () {
        this.setButtonState('idle');
        this.updateSummary(true);
    };

    $(function () {
        $('.imagegecko-autocomplete').each(function () {
            new Autocomplete($(this));
        });

        new GenerationRunner({
            ajaxUrl: imageGeckoAdmin.ajaxUrl,
            nonce: imageGeckoAdmin.nonce,
            hasApiKey: !!imageGeckoAdmin.hasApiKey,
            i18n: imageGeckoAdmin.i18n
        });
    });
})(jQuery);
