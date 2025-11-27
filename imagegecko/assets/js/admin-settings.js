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
        this.batchSize = 10; // Process 10 products simultaneously
        this.activeBatches = 0;

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
        this.activeBatches = 0;

        this.$summary.text('');
        this.$list.empty();
        this.$progress.show();
    };

    GenerationRunner.prototype.start = function () {
        var self = this;
        
        console.log('ImageGecko: Starting generation workflow');

        this.setButtonState('busy');
        this.resetProgress();

        // First, save the configuration
        this.saveConfiguration().then(function() {
            console.log('ImageGecko: Configuration saved, starting generation');
            
            // Now start the generation process
            return $.post(self.ajaxUrl, {
                action: 'imagegecko_start_generation',
                nonce: self.nonce
            });
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
        }).catch(function(error) {
            console.error('ImageGecko: Configuration save or generation start failed:', error);
            self.handleStartError();
        });
    };

    GenerationRunner.prototype.saveConfiguration = function () {
        var self = this;
        var $form = $('#imagegecko-config-form');
        
        if (!$form.length) {
            console.warn('ImageGecko: Configuration form not found, skipping save');
            return $.Deferred().resolve().promise();
        }

        console.log('ImageGecko: Saving configuration');
        
        // Serialize form data
        var formData = $form.serializeArray();
        var config = {};
        
        // Convert form data to nested object structure
        formData.forEach(function(field) {
            // Handle array fields like selected_categories[] and selected_products[]
            var name = field.name;
            var value = field.value;
            
            // Extract the actual field name from WordPress option format
            // e.g., "imagegecko_settings[selected_categories][]" -> "selected_categories"
            var matches = name.match(/imagegecko_settings\[([^\]]+)\](\[\])?/);
            if (matches) {
                var fieldName = matches[1];
                var isArray = !!matches[2];
                
                if (isArray) {
                    if (!config[fieldName]) {
                        config[fieldName] = [];
                    }
                    config[fieldName].push(value);
                } else {
                    config[fieldName] = value;
                }
            }
        });

        console.log('ImageGecko: Sending configuration:', config);

        return $.post(this.ajaxUrl, {
            action: 'imagegecko_save_config',
            nonce: this.nonce,
            config: config
        }).fail(function(xhr, status, error) {
            console.error('ImageGecko: Configuration save failed:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            throw new Error('Configuration save failed');
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

        // Check if we're done
        if (!this.queue.length && this.activeBatches === 0) {
            this.finish();
            return;
        }

        // Don't start new batches if we have none left to process
        if (!this.queue.length) {
            return;
        }

        // Create a batch of products to process
        var batch = [];
        var batchSize = Math.min(this.batchSize, this.queue.length);
        
        for (var i = 0; i < batchSize; i++) {
            if (this.queue.length > 0) {
                batch.push(this.queue.shift());
            }
        }

        if (batch.length === 0) {
            return;
        }

        this.activeBatches++;

        // Mark all products in batch as processing
        batch.forEach(function(product) {
            self.updateItemStatus(product.id, self.i18n.processing || 'Processing…', 'is-processing');
        });

        console.log('ImageGecko: Processing batch of', batch.length, 'products:', batch.map(function(p) { return p.id; }));

        // Process each product in the batch simultaneously
        var completedInBatch = 0;
        
        batch.forEach(function(product) {
            var productId = product.id;
            
            $.post(self.ajaxUrl, {
                action: 'imagegecko_process_product',
                nonce: self.nonce,
                product_id: productId
            }).done(function (response) {
                console.log('ImageGecko: Process product response for', productId, ':', response);
                
                if (!response || !response.success || !response.data) {
                    console.error('ImageGecko: Invalid process response for product', productId, ':', response);
                    self.handleProcessFailure(productId);
                } else {
                    self.handleProcessResult(productId, response.data);
                }
                
                completedInBatch++;
                if (completedInBatch === batch.length) {
                    self.activeBatches--;
                    // Process next batch only after current batch is fully complete
                    self.processNext();
                }
            }).fail(function (xhr, status, error) {
                console.error('ImageGecko: Process product AJAX failed for', productId, ':', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                self.handleProcessFailure(productId);
                
                completedInBatch++;
                if (completedInBatch === batch.length) {
                    self.activeBatches--;
                    // Process next batch only after current batch is fully complete
                    self.processNext();
                }
            });
        });
        
        // Note: Next batch will only start when all products in current batch complete
        // (triggered by the completedInBatch check above)
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

        this.updateItemStatus(productId, statusText, cssClass, message, data.images);
        this.updateSummary();
    };

    GenerationRunner.prototype.updateItemStatus = function (productId, text, cssClass, message, images) {
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
        
        // Add images if available and status is completed
        if (images && cssClass === 'is-success') {
            this.displayImages($item, images);
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
        var progressText = progressTemplate.replace('%1$d', this.completed + this.failed + this.skipped).replace('%2$d', this.total);
        
        // Add batch processing info if we have active batches
        if (this.activeBatches > 0) {
            var batchInfo = this.i18n.batchProcessing || ' (Processing %d batches simultaneously)';
            progressText += batchInfo.replace('%d', this.activeBatches);
        }
        
        this.$summary.text(progressText);
    };

    GenerationRunner.prototype.finish = function () {
        this.setButtonState('idle');
        this.updateSummary(true);
    };
    
    GenerationRunner.prototype.displayImages = function ($item, images) {
        var self = this;
        
        // Check if images container already exists
        var $imagesContainer = $item.find('.imagegecko-progress__images');
        if ($imagesContainer.length) {
            return; // Already displayed
        }
        
        $imagesContainer = $('<div>', {
            class: 'imagegecko-progress__images'
        });
        
        // Add source image if available
        if (images.source && images.source.url) {
            var $sourceContainer = $('<div>', {
                class: 'imagegecko-progress__image-container'
            });
            
            $('<h4>', {
                class: 'imagegecko-progress__image-title',
                text: self.i18n.sourceImage || 'Source Image'
            }).appendTo($sourceContainer);
            
            var $sourceImg = $('<img>', {
                src: images.source.url,
                class: 'imagegecko-progress__image',
                alt: 'Source image',
                title: self.i18n.clickToViewFull || 'Click to view full size'
            });
            
            $sourceImg.on('click', function() {
                self.openImageModal(images.source.full_url, images.source.title || 'Source Image');
            });
            
            $sourceImg.appendTo($sourceContainer);
            
            $imagesContainer.append($sourceContainer);
        }
        
        // Add generated image if available
        if (images.generated && images.generated.url) {
            var $generatedContainer = $('<div>', {
                class: 'imagegecko-progress__image-container'
            });
            
            var $titleContainer = $('<div>', {
                class: 'imagegecko-progress__image-header'
            });
            
            $('<h4>', {
                class: 'imagegecko-progress__image-title',
                text: self.i18n.generatedImage || 'Generated Image'
            }).appendTo($titleContainer);
            
            var $deleteBtn = $('<button>', {
                type: 'button',
                class: 'button button-small imagegecko-delete-btn',
                text: self.i18n.delete || 'Delete',
                'data-attachment-id': images.generated.attachment_id
            });
            
            $titleContainer.append($deleteBtn);
            $generatedContainer.append($titleContainer);
            
            var $generatedImg = $('<img>', {
                src: images.generated.url,
                class: 'imagegecko-progress__image',
                alt: 'Generated image',
                title: self.i18n.clickToViewFull || 'Click to view full size'
            });
            
            $generatedImg.on('click', function() {
                self.openImageModal(images.generated.full_url, images.generated.title || 'Generated Image', images.generated.attachment_id);
            });
            
            $generatedImg.appendTo($generatedContainer);
            
            $imagesContainer.append($generatedContainer);
            
            // Bind delete functionality
            $deleteBtn.on('click', function(e) {
                e.preventDefault();
                self.deleteGeneratedImage($(this), images.generated.attachment_id);
            });
        }
        
        $item.append($imagesContainer);
    };
    
    GenerationRunner.prototype.deleteGeneratedImage = function ($button, attachmentId) {
        var self = this;
        
        if (!confirm(self.i18n.deleteConfirm || 'Are you sure you want to delete this generated image? This action cannot be undone.')) {
            return;
        }
        
        $button.prop('disabled', true).text(self.i18n.deleting || 'Deleting...');
        
        $.post(this.ajaxUrl, {
            action: 'imagegecko_delete_generated_image',
            nonce: this.nonce,
            attachment_id: attachmentId
        }).done(function (response) {
            if (response && response.success) {
                // Remove the generated image container
                $button.closest('.imagegecko-progress__image-container').fadeOut(300, function() {
                    $(this).remove();
                });
                
                // Show feedback if featured image was restored
                if (response.data && response.data.featured_restored) {
                    // You could add a temporary success message here if desired
                    console.log('ImageGecko: Featured image restored to original');
                }
            } else {
                var message = response && response.data && response.data.message 
                    ? response.data.message 
                    : (self.i18n.deleteError || 'Failed to delete image.');
                alert(message);
                $button.prop('disabled', false).text(self.i18n.delete || 'Delete');
            }
        }).fail(function (xhr, status, error) {
            console.error('ImageGecko: Delete image AJAX failed:', {
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            alert(self.i18n.deleteError || 'Failed to delete image. Please try again.');
            $button.prop('disabled', false).text(self.i18n.delete || 'Delete');
        });
    };
    
    GenerationRunner.prototype.openImageModal = function(imageUrl, title, attachmentId) {
        var self = this;
        
        // Remove existing modal if any
        $('.imagegecko-image-modal').remove();
        
        // Create modal structure
        var $modal = $('<div>', {
            class: 'imagegecko-image-modal'
        });
        
        var $content = $('<div>', {
            class: 'imagegecko-modal-content'
        });
        
        // Modal header
        var $header = $('<div>', {
            class: 'imagegecko-modal-header'
        });
        
        $('<h3>', {
            class: 'imagegecko-modal-title',
            text: title
        }).appendTo($header);
        
        var $closeBtn = $('<button>', {
            class: 'imagegecko-modal-close',
            type: 'button',
            'aria-label': self.i18n.close || 'Close',
            html: '&times;'
        });
        
        $header.append($closeBtn);
        $content.append($header);
        
        // Modal image
        var $img = $('<img>', {
            src: imageUrl,
            class: 'imagegecko-modal-image',
            alt: title
        });
        
        $content.append($img);
        
        // Modal actions (only for generated images)
        if (attachmentId) {
            var $actions = $('<div>', {
                class: 'imagegecko-modal-actions'
            });
            
            var $deleteBtn = $('<button>', {
                type: 'button',
                class: 'button imagegecko-delete-btn',
                text: self.i18n.delete || 'Delete',
                'data-attachment-id': attachmentId
            });
            
            $actions.append($deleteBtn);
            $content.append($actions);
            
            // Bind delete functionality
            $deleteBtn.on('click', function(e) {
                e.preventDefault();
                var $modalButton = $(this);
                
                if (!confirm(self.i18n.deleteConfirm || 'Are you sure you want to delete this generated image? This action cannot be undone.')) {
                    return;
                }
                
                $modalButton.prop('disabled', true).text(self.i18n.deleting || 'Deleting...');
                
                $.post(self.ajaxUrl, {
                    action: 'imagegecko_delete_generated_image',
                    nonce: self.nonce,
                    attachment_id: attachmentId
                }).done(function (response) {
                    if (response && response.success) {
                        // Close modal first
                        $modal.removeClass('is-open');
                        setTimeout(function() {
                            $modal.remove();
                        }, 300);
                        
                        // Remove the generated image container from the progress list
                        $('.imagegecko-progress__image-container').each(function() {
                            var $container = $(this);
                            if ($container.find('img[src="' + imageUrl + '"]').length) {
                                $container.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                        });
                        
                        // Show feedback if featured image was restored
                        if (response.data && response.data.featured_restored) {
                            console.log('ImageGecko: Featured image restored to original');
                        }
                    } else {
                        var message = response && response.data && response.data.message 
                            ? response.data.message 
                            : (self.i18n.deleteError || 'Failed to delete image.');
                        alert(message);
                        $modalButton.prop('disabled', false).text(self.i18n.delete || 'Delete');
                    }
                }).fail(function (xhr, status, error) {
                    console.error('ImageGecko: Delete image AJAX failed:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    alert(self.i18n.deleteError || 'Failed to delete image. Please try again.');
                    $modalButton.prop('disabled', false).text(self.i18n.delete || 'Delete');
                });
            });
        }
        
        $modal.append($content);
        $('body').append($modal);
        
        // Close modal functionality
        var closeModal = function() {
            $modal.removeClass('is-open');
            setTimeout(function() {
                $modal.remove();
            }, 300);
        };
        
        $closeBtn.on('click', closeModal);
        
        // Close on backdrop click
        $modal.on('click', function(e) {
            if (e.target === $modal[0]) {
                closeModal();
            }
        });
        
        // Close on escape key
        $(document).on('keyup.imagegecko-modal', function(e) {
            if (e.keyCode === 27) { // Escape key
                closeModal();
                $(document).off('keyup.imagegecko-modal');
            }
        });
        
        // Show modal
        setTimeout(function() {
            $modal.addClass('is-open');
        }, 10);
    };

    $(function () {
        // Initialize all autocomplete fields
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
