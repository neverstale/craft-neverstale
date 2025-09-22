/**
 * Neverstale JavaScript - Production Version
 *
 * Handles ignore flag slideout functionality using native Craft CMS patterns
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initIgnoreFlagSlideouts();
        initReanalyzeButtons();
    });

    /**
     * Initialize ignore flag slideout handlers
     */
    function initIgnoreFlagSlideouts() {
        var $buttons = $('a[data-flag-id]');

        $buttons.on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var flagId = $button.data('flag-id');
            var customId = $button.data('custom-id');

            // Validate required data
            if (!flagId || !customId) {
                return;
            }

            // Create slideout using native Craft pattern
            var slideout = new Craft.CpScreenSlideout('neverstale/flag/ignore-slideout', {
                params: {
                    flagId: flagId,
                    customId: customId
                }
            });

            // Initialize custom date picker when slideout loads
            slideout.on('load', function() {
                // Small delay to ensure DOM is fully rendered
                setTimeout(function() {
                    initCustomDatePicker();
                }, 100);
            });

            // Handle successful form submission using Craft's proper pattern
            slideout.on('submit', function({data}) {
                // Show success message from server response
                if (data && data.message) {
                    Craft.cp.displayNotice(data.message);
                }

                // Immediately update the flag display
                updateFlagDisplay(flagId, customId);

                // Also refresh the page after a delay to ensure data consistency
                // This serves as a backup and updates other elements like entry meta
                setTimeout(function() {
                    location.reload();
                }, 3000);
            });
        });
    }

    /**
     * Format a date object to a readable string
     */
    function formatDate(date) {
        if (!date) return '';

        // Convert string to Date object if needed
        if (typeof date === 'string') {
            date = new Date(date);
        }

        // Check if valid date
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }

        // Format as "Dec 25, 2024"
        var options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        return date.toLocaleDateString('en-US', options);
    }

    /**
     * Initialize custom date picker functionality within slideout
     */
    function initCustomDatePicker() {
        // Find slideout container
        var $slideout = $('.slideout:visible, .hud:visible').last();
        var $radioButtons, $customSection;

        if ($slideout.length > 0) {
            // Search within slideout context
            $radioButtons = $slideout.find('input[name="ignoreOption"]');
            if ($radioButtons.length === 0) {
                // Fallback to radio button type selector (handles Craft form namespacing)
                $radioButtons = $slideout.find('input[type="radio"]');
            }
            $customSection = $slideout.find('#custom-date-section');
        } else {
            // Fallback to global search
            $radioButtons = $('input[name="ignoreOption"]');
            if ($radioButtons.length === 0) {
                $radioButtons = $('input[type="radio"]');
            }
            $customSection = $('#custom-date-section');
        }

        // Exit if no radio buttons found
        if ($radioButtons.length === 0) {
            return;
        }

        // Handle custom date section visibility
        $radioButtons.on('change', function() {
            var selectedValue = $(this).val();

            if (selectedValue === 'custom') {
                showCustomDateSection($slideout, $customSection);
            } else {
                hideCustomDateSection($slideout, $customSection);
            }
        });

        // Set initial state
        var $checkedOption = $slideout.length > 0
          ? $slideout.find('input[type="radio"]:checked')
          : $('input[type="radio"]:checked');

        if ($checkedOption.length > 0 && $checkedOption.val() === 'custom') {
            showCustomDateSection($slideout, $customSection);
        }

        // Monitor date input changes and format display
        if ($customSection.length > 0) {
            var $dateInput = $customSection.find('input[type="text"], input[type="date"]');

            // Monitor for Craft's date picker updates
            $dateInput.on('change blur', function() {
                var dateValue = $(this).val();
                if (dateValue) {
                    // Format and update any visible date display
                    var formattedDate = formatDate(dateValue);

                    // Update the input's display value if needed
                    var $displayElement = $(this).siblings('.datewrapper').find('.text');
                    if ($displayElement.length > 0) {
                        $displayElement.val(formattedDate);
                    }

                    // Update any date preview text
                    var $datePreview = $customSection.find('.date-preview');
                    if ($datePreview.length > 0) {
                        $datePreview.text(formattedDate);
                    }
                }
            });

            // Also monitor for Craft's date picker widget events
            if (window.Craft && window.Craft.DateTimePicker) {
                setTimeout(function() {
                    var $dateWrapper = $customSection.find('.datewrapper');
                    if ($dateWrapper.length > 0) {
                        var datePickerData = $dateWrapper.data('datepicker');
                        if (datePickerData) {
                            // Override the date picker's format display
                            var originalUpdateValue = datePickerData.updateValue;
                            if (originalUpdateValue) {
                                datePickerData.updateValue = function() {
                                    originalUpdateValue.call(this);
                                    var $input = this.$date;
                                    if ($input && $input.val()) {
                                        var formattedDate = formatDate($input.val());
                                        // Update display without affecting the actual value
                                        var $display = this.$container.find('.text');
                                        if ($display.length > 0 && $display.val() !== formattedDate) {
                                            // Store original value
                                            var originalVal = $input.val();
                                            // Show formatted date
                                            $display.attr('data-original-date', originalVal);
                                            $display.val(formattedDate);
                                        }
                                    }
                                };
                            }
                        }
                    }
                }, 200);
            }
        }
    }

    /**
     * Show custom date section with fallback selectors
     * Note: With inline layout, this now just enables the date field
     */
    function showCustomDateSection($slideout, $customSection) {
        // The CSS handles showing/hiding via opacity and pointer-events
        // This function is kept for compatibility but the CSS does the work

        // Focus the date field when custom is selected
        if ($customSection.length > 0) {
            var $dateInput = $customSection.find('input[type="text"]');
            if ($dateInput.length > 0) {
                setTimeout(function() {
                    $dateInput.focus();
                }, 100);
            }
        }
    }

    /**
     * Hide custom date section with fallback selectors
     * Note: With inline layout, this now just disables the date field
     */
    function hideCustomDateSection($slideout, $customSection) {
        // The CSS handles showing/hiding via opacity and pointer-events
        // This function is kept for compatibility but the CSS does the work
    }

    /**
     * Update flag display immediately after successful operation
     */
    function updateFlagDisplay(flagId, customId) {
        // Find all flag items with this flag ID
        var $flagItems = $('.neverstale-flag-item').filter(function() {
            var $ignoreBtn = $(this).find('a[data-flag-id="' + flagId + '"]');
            return $ignoreBtn.length > 0;
        });

        $flagItems.each(function() {
            var $flagItem = $(this);
            var $ignoreBtn = $flagItem.find('a[data-flag-id="' + flagId + '"]');

            // Update flag appearance to show it's been ignored
            $flagItem.addClass('neverstale-flag-ignored');

            // Update inline styles to match ignored state
            $flagItem.css({
                'border-color': '#e5e7eb',
                'background': '#f9fafb'
            });

            // Update status indicator from red to gray
            $flagItem.find('.status').removeClass('red').addClass('gray');

            // Update flag label to show ignored state
            var $label = $flagItem.find('strong').first();
            if ($label.length > 0) {
                var currentText = $label.text();
                if (!currentText.includes('(Ignored)')) {
                    $label.text(currentText + ' (Ignored)');
                }
            }

            // Hide the ignore button
            if ($ignoreBtn.length > 0) {
                $ignoreBtn.hide();
            }

            // Add ignored notice if it doesn't exist
            if (!$flagItem.find('.neverstale-flag-ignored-notice').length) {
                var $ignoredNotice = $(
                  '<div class="neverstale-flag-ignored-notice" style="margin-top: 12px; padding: 8px 12px; background: #f3f4f6; border-radius: 4px;">' +
                  '<span style="font-size: 12px; color: #6b7280;">' +
                  '✓ Ignored just now' +
                  '</span>' +
                  '</div>'
                );
                $flagItem.append($ignoredNotice);
            }
        });

        // Update status banner to show "Stale" if it was showing "Flagged"
        updateStatusBanner();

        // Update flag count in section header
        updateFlagSectionHeader();
    }

    /**
     * Update status banner after flag operations
     */
    function updateStatusBanner() {
        var $statusCard = $('.neverstale-status-card');
        if ($statusCard.hasClass('neverstale-status-flagged')) {
            // Change from "Flagged" to "Stale"
            $statusCard.find('.neverstale-status-title').text('Stale');
            $statusCard.find('.neverstale-status-date').text('Content needs re-analysis');
        }
    }

    /**
     * Update flag section header count
     */
    function updateFlagSectionHeader() {
        var $flagSection = $('#neverstale-flags');
        if ($flagSection.length > 0) {
            // Count active vs ignored flags
            var $allFlags = $flagSection.find('.neverstale-flag-item');
            var $ignoredFlags = $flagSection.find('.neverstale-flag-item.neverstale-flag-ignored');
            var totalFlags = $allFlags.length;
            var ignoredCount = $ignoredFlags.length;
            var activeCount = totalFlags - ignoredCount;

            // Update the count display
            var $countSpan = $flagSection.find('.info');
            if ($countSpan.length > 0) {
                if (activeCount > 0 && ignoredCount > 0) {
                    $countSpan.text(totalFlags + ' (' + activeCount + ' active, ' + ignoredCount + ' ignored)');
                } else if (ignoredCount > 0) {
                    $countSpan.text(totalFlags + ' (all ignored)');
                } else {
                    $countSpan.text(totalFlags);
                }
            }
        }
    }

    /**
     * Initialize re-analyze button handlers
     */
    function initReanalyzeButtons() {
        $(document).on('click', 'button[data-action="neverstale/reanalyze"]', function(e) {
            e.preventDefault();

            var $button = $(this);
            var entryId = $button.data('entry-id');

            if (!entryId) {
                Craft.cp.displayError('Entry ID is missing');
                return;
            }

            // Show loading state
            $button.addClass('loading');
            $button.find('.spinner').removeClass('hidden');
            $button.prop('disabled', true);

            // Make AJAX request to re-analyze
            Craft.postActionRequest('neverstale/content/ingest', {
                entryId: entryId
            }, function(response) {
                if (response.success) {
                    Craft.cp.displayNotice(response.message || 'Content submitted for re-analysis');

                    // Reload page after short delay to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    Craft.cp.displayError(response.error || 'Failed to re-analyze content');

                    // Reset button state
                    $button.removeClass('loading');
                    $button.find('.spinner').addClass('hidden');
                    $button.prop('disabled', false);
                }
            }).fail(function() {
                Craft.cp.displayError('An error occurred while re-analyzing content');

                // Reset button state
                $button.removeClass('loading');
                $button.find('.spinner').addClass('hidden');
                $button.prop('disabled', false);
            });
        });
    }

})();
