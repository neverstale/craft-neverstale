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
        const $buttons = $('a[data-flag-id]');

        $buttons.on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const flagId = $button.data('flag-id');
            const customId = $button.data('custom-id');

            // Validate required data
            if (!flagId || !customId) {
                return;
            }

            // Create slideout using native Craft pattern
            const slideout = new Craft.CpScreenSlideout('neverstale/flag/ignore-slideout', {
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

                // Immediately update the flag display with expiry date if available
                updateFlagDisplay(flagId, customId, data ? data.expiryDate : null);

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
        const options = {
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
        const $slideout = $('.slideout:visible, .hud:visible').last();
        let $radioButtons, $customSection;

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
            const selectedValue = $(this).val();

            if (selectedValue === 'custom') {
                showCustomDateSection($slideout, $customSection);
            } else {
                hideCustomDateSection($slideout, $customSection);
            }
        });

        // Set initial state
        const $checkedOption = $slideout.length > 0
          ? $slideout.find('input[type="radio"]:checked')
          : $('input[type="radio"]:checked');

        if ($checkedOption.length > 0 && $checkedOption.val() === 'custom') {
            showCustomDateSection($slideout, $customSection);
        }

        // Focus the date field when custom is selected
        if ($customSection.length > 0) {
            const $dateInput = $customSection.find('input[type="text"]');
            if ($dateInput.length > 0) {
                setTimeout(function() {
                    $dateInput.focus();
                }, 100);
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
            const $dateInput = $customSection.find('input[type="text"]');
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
    function updateFlagDisplay(flagId, customId, expiryDate) {
        // Find all flag items with this flag ID
        const $flagItems = $('.neverstale-flag-item').filter(function() {
            const $ignoreBtn = $(this).find('a[data-flag-id="' + flagId + '"]');
            return $ignoreBtn.length > 0;
        });

        $flagItems.each(function() {
            const $flagItem = $(this);
            const $ignoreBtn = $flagItem.find('a[data-flag-id="' + flagId + '"]');

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
            const $label = $flagItem.find('strong').first();
            if ($label.length > 0) {
                const currentText = $label.text();
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
                let ignoreMessage = '✓ Ignored just now';

                // Use expiry date if provided
                if (expiryDate) {
                    const formattedExpiry = formatDate(expiryDate);
                    if (formattedExpiry) {
                        ignoreMessage += ' • Until ' + formattedExpiry;
                    }
                }

                const $ignoredNotice = $(
                  '<div class="neverstale-flag-ignored-notice" style="margin-top: 12px; padding: 8px 12px; background: #f3f4f6; border-radius: 4px;">' +
                  '<span style="font-size: 12px; color: #6b7280;">' +
                  ignoreMessage +
                  '</span>' +
                  '</div>'
                );
                $flagItem.append($ignoredNotice);
            }
        });

        // Update status banner after flag operations
        updateStatusBanner();

        // Update flag count in section header
        updateFlagSectionHeader();
    }

    /**
     * Update status banner after flag operations
     */
    function updateStatusBanner() {
        const $statusCard = $('.neverstale-status-card');
        if ($statusCard.hasClass('neverstale-status-flagged')) {
            // Keep status as "Flagged" since we no longer use "Stale"
            $statusCard.find('.neverstale-status-date').text('Content needs re-analysis');
        }
    }

    /**
     * Update flag section header count
     */
    function updateFlagSectionHeader() {
        const $flagSection = $('#neverstale-flags');
        if ($flagSection.length > 0) {
            // Count active vs ignored flags
            const $allFlags = $flagSection.find('.neverstale-flag-item');
            const $ignoredFlags = $flagSection.find('.neverstale-flag-item.neverstale-flag-ignored');
            const totalFlags = $allFlags.length;
            const ignoredCount = $ignoredFlags.length;
            const activeCount = totalFlags - ignoredCount;

            // Update the count display
            const $countSpan = $flagSection.find('.info');
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
     * Update status display to show pending analysis
     */
    function updateStatusToPending() {
        const $statusCard = $('.neverstale-status-card');

        // Remove all status classes
        $statusCard.removeClass('neverstale-status-synced neverstale-status-flagged');
        $statusCard.addClass('neverstale-status-pending');

        // Update the status icon
        const $statusIcon = $statusCard.find('.neverstale-status-icon .status');
        $statusIcon.removeClass('green orange red gray').addClass('blue');

        // Update the title and description
        $statusCard.find('.neverstale-status-title').text('Processing');
        $statusCard.find('.neverstale-status-date').text('Waiting for Neverstale analysis');

        // Hide the action buttons since it's now processing
        $statusCard.find('.neverstale-status-actions').hide();
    }

    /**
     * Initialize re-analyze button handlers
     */
    function initReanalyzeButtons() {
        $(document).on('click', 'button[data-action="neverstale/reanalyze"]', function(e) {
            e.preventDefault();

            const $button = $(this);
            const entryId = $button.data('entry-id');

            if (!entryId) {
                Craft.cp.displayError('Entry ID is missing');
                return;
            }

            // Show loading state
            $button.addClass('loading');
            $button.find('.spinner').removeClass('hidden');
            $button.prop('disabled', true);

            // Make AJAX request to re-analyze
            Craft.postActionRequest('neverstale/content/reanalyze', {
                entryId: entryId
            }, function(response) {
                if (response.success) {
                    Craft.cp.displayNotice(response.message || 'Content submitted for re-analysis');

                    // Update the status card immediately to show pending state
                    updateStatusToPending();

                    // Remove loading state from button since operation is complete
                    $button.removeClass('loading');
                    $button.find('.spinner').addClass('hidden');
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