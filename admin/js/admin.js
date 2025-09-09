/**
 * WordPress CDN Integration Admin JavaScript - Professional Version
 * Following WordPress DevDocs standards and modern UX principles
 */
(function($) {
    'use strict';

    // Global state management
    const CDNState = {
        isUploading: false,
        currentBatch: 0,
        totalBatches: 0,
        uploadStartTime: null,
        uploadedFiles: 0,
        totalFiles: 0,
        failedFiles: 0,
        existingFiles: 0
    };

    // Document ready
    $(function() {
        // Initialize the main tabs
        initMainTabs();
        
        // Initialize analyzer tabs
        initAnalyzerTabs();
        
        // Initialize progress tracking
        initProgressTracking();
        
        
        // Test connection button
        $('#test-connection-button, #dashboard-test-connection').on('click', function() {
            testGitHubConnection($(this));
        });
        
        // Auto Connect with GitHub button
        $('#auto-connect-github').on('click', function() {
            startAutoGitHubConnection();
        });
        
        // Manual Setup Toggle
        $('.cdn-toggle-manual').on('click', function() {
            toggleManualSetup();
        });
        
        // Test GitHub Connection
        $('#test-github-connection').on('click', function() {
            testGitHubConnection($(this));
        });
        
        // Auto-fill fields when username changes
        $('#wp-cdn-github-username').on('blur', function() {
            autoFillRepositoryName();
        });
        
        // Disconnect GitHub button
        $('#disconnect-github').on('click', function() {
            disconnectGitHub();
        });
        
        // Purge CDN cache button
        $('#purge-cdn-button, #dashboard-purge-cdn, #quick-purge-cdn').on('click', function() {
            if ($(this).hasClass('disabled')) {
                return;
            }
            
            if (!confirm(wpCdnAdmin.i18n.confirmPurge)) {
                return;
            }
            
            purgeCdnCache($(this));
        });
        
        // Quick analysis button
        $('#quick-analyze-button').on('click', function() {
            analyzeUrls($(this), false);
        });
        
        // Deep analysis button
        $('#deep-analyze-button').on('click', function() {
            var startUrl = $('#start-url').val();
            var maxPages = $('#max-pages').val();
            
            if (!startUrl) {
                alert('Please enter a Start URL');
                return;
            }
            
            analyzeUrls($(this), true, startUrl, maxPages);
        });
        
        // Analyze pasted URLs button
        $('#analyze-pasted-urls-button').on('click', function() {
            var pastedUrls = $('#pasted-urls').val();
            
            if (!pastedUrls) {
                alert('Please paste URLs to analyze');
                return;
            }
            
            analyzePastedUrls($(this), pastedUrls);
        });
        
        // Select All button
        $(document).on('click', '#select-all-urls', function() {
            var $visibleCheckboxes = $('.url-item:visible input[type="checkbox"]');
            var isAllSelected = $visibleCheckboxes.filter(':checked').length === $visibleCheckboxes.length;
            
            $visibleCheckboxes.prop('checked', !isAllSelected);
            
            $(this).text(isAllSelected ? wpCdnAdmin.i18n.selectAll : wpCdnAdmin.i18n.deselectAll);
        });
        
        // Add Selected URLs button
        $(document).on('click', '#add-selected-urls', function() {
            addSelectedUrlsToCustomList();
        });
        
        // Upload to GitHub button
        $(document).on('click', '#upload-to-github-button', function() {
            uploadSelectedToGithub($(this));
        });
        
        // Log view functionality
        $('#view-log-button, #refresh-log-button').on('click', function() {
            viewLog($(this));
        });
        
        // Validate URLs button
        $('#validate-urls-button').on('click', function() {
            validateCustomUrls($(this));
        });
        
        // Filter functionality
        $('#url-filter, #url-type-filter').on('input change', function() {
            filterUrls();
        });
        
        // Tab linking functionality
        $('.cdn-tab-link').on('click', function() {
            var tabId = $(this).data('tab');
            showMainTab(tabId);
        });
        
        // Check for CDN enabled warning
        if ($('#wp-cdn-enabled').length) {
            var $enableCheckbox = $('#wp-cdn-enabled');
            var originalChecked = $enableCheckbox.prop('checked');
            
            $enableCheckbox.on('change', function() {
                if (!originalChecked && $(this).prop('checked')) {
                    var customUrlsContent = $('#wp-cdn-custom-urls').val();
                    if (!customUrlsContent || customUrlsContent.trim() === '') {
                        if (!confirm(wpCdnAdmin.i18n.enableWarning)) {
                            $(this).prop('checked', false);
                        }
                    }
                }
            });
        }
    });

    /**
     * Initialize progress tracking system.
     */
    function initProgressTracking() {
        // Create progress overlay if it doesn't exist
        if (!$('.loading-overlay').length) {
            $('body').append(`
                <div class="loading-overlay" style="display: none;">
                    <div class="loading-content">
                        <div class="loading-spinner"></div>
                        <h3>Processing Files</h3>
                        <p>Please wait while we process your files...</p>
                        <div class="progress-container">
                            <div class="progress-header">
                                <span class="progress-title">Upload Progress</span>
                                <span class="progress-percentage">0%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: 0%"></div>
                            </div>
                            <div class="progress-status">Initializing...</div>
                            <div class="progress-details">
                                <span class="files-processed">0 / 0 files</span>
                                <span class="time-remaining">Calculating...</span>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    /**
     * Show progress overlay with detailed tracking.
     */
    function showProgressOverlay(title = 'Processing Files', message = 'Please wait...') {
        $('.loading-overlay h3').text(title);
        $('.loading-overlay p').text(message);
        $('.loading-overlay').fadeIn(300);
    }

    /**
     * Hide progress overlay.
     */
    function hideProgressOverlay() {
        $('.loading-overlay').fadeOut(300);
    }

    /**
     * Update progress with detailed information.
     */
    function updateProgress(percentage, status, details = {}) {
        const $overlay = $('.loading-overlay');
        const $progressBar = $overlay.find('.progress-bar');
        const $percentage = $overlay.find('.progress-percentage');
        const $status = $overlay.find('.progress-status');
        const $filesProcessed = $overlay.find('.files-processed');
        const $timeRemaining = $overlay.find('.time-remaining');

        // Update progress bar
        $progressBar.css('width', percentage + '%');
        $percentage.text(Math.round(percentage) + '%');

        // Update status
        if (status) {
            $status.text(status);
        }

        // Update file count
        if (details.processed !== undefined && details.total !== undefined) {
            $filesProcessed.text(`${details.processed} / ${details.total} files`);
        }

        // Update time remaining
        if (details.timeRemaining) {
            $timeRemaining.text(details.timeRemaining);
        } else if (CDNState.uploadStartTime && details.processed > 0) {
            const elapsed = Date.now() - CDNState.uploadStartTime;
            const rate = details.processed / (elapsed / 1000);
            const remaining = details.total - details.processed;
            const eta = remaining / rate;
            $timeRemaining.text(formatTime(eta));
        }
    }

    /**
     * Format time in seconds to human readable format.
     */
    function formatTime(seconds) {
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        } else if (seconds < 3600) {
            return Math.round(seconds / 60) + 'm';
        } else {
            return Math.round(seconds / 3600) + 'h';
        }
    }

    /**
     * Initialize the main tabs system.
     */
    function initMainTabs() {
        $('.cdn-tab-button').on('click', function() {
            var tabId = $(this).data('tab');
            showMainTab(tabId);
        });
        
        // Check for hash in URL to show specific tab
        var hash = window.location.hash;
        if (hash) {
            var tabId = hash.substring(1);
            if (tabId.indexOf('-tab') === -1) {
                tabId = tabId + '-tab';
            }
            if ($('#' + tabId).length) {
                showMainTab(tabId.replace('-tab', ''));
            }
        }
    }
    
    /**
     * Show a specific main tab.
     * 
     * @param {string} tabId The tab ID to show.
     */
    function showMainTab(tabId) {
        // Update active tab
        $('.cdn-tab-button').removeClass('active');
        $('.cdn-tab-button[data-tab="' + tabId + '"]').addClass('active');
        
        // Show corresponding content
        $('.cdn-tab-pane').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');
        
        // Update URL hash
        window.location.hash = tabId;
    }
    
    /**
     * Initialize the analyzer tabs.
     */
    function initAnalyzerTabs() {
        $('.cdn-analyzer-tab').on('click', function() {
            var tabId = $(this).data('tab');
            
            // Update active tab
            $('.cdn-analyzer-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content
            $('.cdn-analyzer-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    }

    
    /**
     * Test GitHub connection with enhanced feedback.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function testGitHubConnection($button) {
        var $status = $button.siblings('.connection-status');
        if (!$status.length) {
            $status = $('<span class="connection-status"></span>');
            $button.after($status);
        }
        
        $button.prop('disabled', true);
        $button.html('<span class="loading-spinner"></span> ' + wpCdnAdmin.i18n.testingConnection);
        $status.removeClass('success error warning').text('');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_test_connection',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    
                    // Show repository information if available
                    if (response.data.repository) {
                        const repo = response.data.repository;
                        const repoInfo = `
                            <div class="repository-info" style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-radius: 4px; border-left: 4px solid #2271b1;">
                                <strong>Repository Details:</strong><br>
                                Name: ${repo.name}<br>
                                Full Name: ${repo.full_name}<br>
                                Default Branch: ${repo.default_branch}<br>
                                Private: ${repo.private ? 'Yes' : 'No'}
                            </div>
                        `;
                        $status.after(repoInfo);
                    }
                    
                    // Show success notification
                    showNotification(response.data.message, 'success');
                    
                    // Refresh the page if on dashboard to update workflow
                    if ($('.workflow-steps').length) {
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    $status.addClass('error').text(response.data.message);
                    
                    // Show detailed error information
                    let errorMessage = response.data.message;
                    if (response.data.code) {
                        errorMessage += ` (Error Code: ${response.data.code})`;
                    }
                    
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                $status.addClass('error').text('Failed to connect to server');
                showNotification('Failed to connect to server: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Test Connection');
            }
        });
    }
    
    /**
     * Purge CDN cache.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function purgeCdnCache($button) {
        var $status = $button.siblings('.purge-status');
        if (!$status.length) {
            $status = $('<span class="purge-status"></span>');
            $button.after($status);
        }
        
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.purgingCache);
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_purge_cache',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    alert(wpCdnAdmin.i18n.success + ': ' + response.data.message);
                } else {
                    $status.addClass('error').text(response.data.message);
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                $status.addClass('error').text('Failed to connect to server');
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Purge Cache');
            }
        });
    }
	/**
     * Analyze URLs from the site.
     * 
     * @param {jQuery} $button    The button that was clicked.
     * @param {bool}   deepAnalyze Whether to perform deep analysis.
     * @param {string} startUrl   Optional. Starting URL for deep analysis.
     * @param {number} maxPages   Optional. Maximum pages for deep analysis.
     */
    function analyzeUrls($button, deepAnalyze, startUrl, maxPages) {
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.analyzing);
        
        // Reset results
        $('.cdn-analyzer-results').hide();
        $('.cdn-analyzer-url-list').empty();
        $('#url-count').text('');
        $('.filter-status').text('');
        $('#url-filter').val('');
        $('#url-type-filter').val('all');
        
        var data = {
            action: 'cdn_analyze_urls',
            nonce: wpCdnAdmin.nonce,
            deep_analyze: deepAnalyze
        };
        
        if (deepAnalyze) {
            data.start_url = startUrl;
            data.max_pages = maxPages;
        }
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                } else {
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text(deepAnalyze ? 'Start Deep Analysis' : 'Analyze Homepage');
            }
        });
    }
    
    /**
     * Analyze pasted URLs.
     * 
     * @param {jQuery} $button    The button that was clicked.
     * @param {string} pastedUrls URLs pasted by the user.
     */
    function analyzePastedUrls($button, pastedUrls) {
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.analyzing);
        
        // Reset results
        $('.cdn-analyzer-results').hide();
        $('.cdn-analyzer-url-list').empty();
        $('#url-count').text('');
        $('.filter-status').text('');
        $('#url-filter').val('');
        $('#url-type-filter').val('all');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_direct_analyze_urls',
                nonce: wpCdnAdmin.nonce,
                pasted_urls: pastedUrls
            },
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                } else {
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                }
            },
            error: function() {
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Analyze Pasted URLs');
            }
        });
    }
    
    /**
     * Display analysis results.
     * 
     * @param {Object} data Response data from the server.
     */
	 function cleanUrlDisplay(url) {
    // Remove any remaining escaped characters
    return url.replace(/\\\//g, '/').replace(/\\/g, '/');
}
    function displayAnalysisResults(data) {
        if (!data.urls || !data.urls.length) {
            alert(wpCdnAdmin.i18n.noUrlsFound);
            return;
        }
        
        // Group URLs by type for better organization
        var urlsByType = categorizeUrls(data.urls);
        
        // Update count
        $('#url-count').text(data.count + ' URLs found');
        
        // Clear URL list
        var $urlList = $('.cdn-analyzer-url-list');
        $urlList.empty();
        
        // Create markup for each type
        Object.keys(urlsByType).forEach(function(type) {
            var urls = urlsByType[type];
            if (urls.length > 0) {
                var typeLabel = getTypeLabel(type);
                var typeIcon = getTypeIcon(type);
                
                var $typeHeader = $('<div class="url-type-header" data-type="' + type + '">' + 
                                  typeIcon + ' <strong>' + typeLabel + '</strong> <span class="count">(' + urls.length + ')</span>' +
                                  '<span class="toggle-indicator">▼</span>' +
                                  '</div>');
                
                var $typeItems = $('<div class="url-type-items" data-type="' + type + '"></div>');
                
                urls.forEach(function(url, index) {
                    var $item = $('<div class="url-item" data-type="' + type + '">' +
                                 '<label>' +
                                 '<input type="checkbox" class="url-checkbox" value="' + url + '"> ' +
                                 '<span class="url-path">' + url + '</span>' +
                                 '</label>' +
                                 '</div>');
                    
                    $typeItems.append($item);
                });
                
                $urlList.append($typeHeader);
                $urlList.append($typeItems);
                
                // Make type headers collapsible
                $typeHeader.on('click', function() {
                    var $items = $('.url-type-items[data-type="' + type + '"]');
                    $items.toggle();
                    var $indicator = $(this).find('.toggle-indicator');
                    $indicator.text($items.is(':visible') ? '▼' : '►');
                });
            }
        });
        
        // Initialize filter functionality
        filterUrls();
        
        // Show results
        $('.cdn-analyzer-results').show();
    }
    
    /**
     * Filter displayed URLs.
     */
    function filterUrls() {
        var searchText = $('#url-filter').val().toLowerCase();
        var selectedType = $('#url-type-filter').val();
        
        var visibleCount = 0;
        var totalCount = $('.url-item').length;
        
        // Show/hide URL items based on filters
        $('.url-item').each(function() {
            var $item = $(this);
            var urlText = $item.find('.url-path').text().toLowerCase();
            var urlType = $item.data('type');
            
            var matchesSearch = searchText === '' || urlText.indexOf(searchText) !== -1;
            var matchesType = selectedType === 'all' || (
                selectedType === 'images' && (urlType === 'png' || urlType === 'jpg' || urlType === 'jpeg' || urlType === 'gif' || urlType === 'svg' || urlType === 'webp' || urlType === 'ico') ||
                selectedType === 'fonts' && (urlType === 'woff' || urlType === 'woff2' || urlType === 'ttf' || urlType === 'eot') ||
                selectedType === urlType
            );
            
            if (matchesSearch && matchesType) {
                $item.show();
                visibleCount++;
            } else {
                $item.hide();
            }
        });
        
        // Update headers visibility based on their children
        $('.url-type-header').each(function() {
            var type = $(this).data('type');
            var $items = $('.url-item[data-type="' + type + '"]:visible');
            
            if ($items.length > 0) {
                $(this).show();
                $(this).find('.count').text('(' + $items.length + ')');
            } else {
                $(this).hide();
            }
        });
        
        // Update filter status
        $('.filter-status').text('Showing ' + visibleCount + ' of ' + totalCount + ' URLs');
    }
	/**
     * Add selected URLs to the custom URL list.
     */
    function addSelectedUrlsToCustomList() {
        var selectedUrls = [];
        $('.url-item input[type="checkbox"]:checked').each(function() {
            selectedUrls.push($(this).val());
        });
        
        if (selectedUrls.length === 0) {
            alert('Please select at least one URL.');
            return;
        }
        
        // Get current textarea content
        var customUrlsTextarea = $('#wp-cdn-custom-urls');
        var currentUrls = '';
        
        if (customUrlsTextarea.length) {
            // Direct update if we're on the same page
            currentUrls = customUrlsTextarea.val();
            
            // Add new URLs (ensure no duplicates)
            var existingUrls = currentUrls ? currentUrls.split("\n") : [];
            var newUrls = [];
            
            selectedUrls.forEach(function(url) {
                if (existingUrls.indexOf(url) === -1) {
                    newUrls.push(url);
                }
            });
            
            if (newUrls.length === 0) {
                alert('All selected URLs are already in the custom URL list.');
                return;
            }
            
            var updatedUrls = currentUrls ? currentUrls + "\n" + newUrls.join("\n") : newUrls.join("\n");
            
            // Update textarea
            customUrlsTextarea.val(updatedUrls);
            
            // Notify user
            alert('Added ' + newUrls.length + ' URLs to the custom URL list.');
        } else {
            // We're on a different tab/page, need to use AJAX
            $.ajax({
                url: wpCdnAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cdn_update_custom_urls',
                    nonce: wpCdnAdmin.nonce,
                    urls: JSON.stringify(selectedUrls)
                },
                success: function(response) {
                    if (response.success) {
                        alert('Added ' + selectedUrls.length + ' URLs to the custom URL list.');
                        
                        // Switch to custom URLs tab
                        showMainTab('custom');
                    } else {
                        alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                    }
                },
                error: function() {
                    alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
                }
            });
        }
    }
    
    /**
     * Upload selected URLs to GitHub with professional progress tracking.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function uploadSelectedToGithub($button) {
        var selectedUrls = [];
        $('.url-item input[type="checkbox"]:checked').each(function() {
            selectedUrls.push($(this).val());
        });
        
        if (selectedUrls.length === 0) {
            showNotification('Please select at least one URL to upload.', 'warning');
            return;
        }
        
        if (!confirm(wpCdnAdmin.i18n.confirmUpload)) {
            return;
        }
        
        // Initialize upload state
        CDNState.isUploading = true;
        CDNState.uploadStartTime = Date.now();
        CDNState.totalFiles = selectedUrls.length;
        CDNState.uploadedFiles = 0;
        CDNState.failedFiles = 0;
        CDNState.existingFiles = 0;
        
        // Show professional progress overlay
        showProgressOverlay('Uploading to GitHub', 'Preparing files for upload...');
        updateProgress(0, 'Initializing upload...', {
            processed: 0,
            total: selectedUrls.length
        });
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.html('<span class="loading-spinner"></span> ' + wpCdnAdmin.i18n.uploadingFiles);
        
        // Process uploads in batches for better performance
        const batchSize = 5;
        const batches = [];
        for (let i = 0; i < selectedUrls.length; i += batchSize) {
            batches.push(selectedUrls.slice(i, i + batchSize));
        }
        
        CDNState.totalBatches = batches.length;
        CDNState.currentBatch = 0;
        
        processUploadBatches(batches, 0, $button);
    }

    /**
     * Process upload batches with progress tracking.
     */
    function processUploadBatches(batches, batchIndex, $button) {
        if (batchIndex >= batches.length) {
            // All batches complete
            completeUpload($button);
            return;
        }
        
        const currentBatch = batches[batchIndex];
        CDNState.currentBatch = batchIndex;
        
        // Update progress
        const batchProgress = (batchIndex / batches.length) * 100;
        updateProgress(batchProgress, `Processing batch ${batchIndex + 1} of ${batches.length}...`, {
            processed: CDNState.uploadedFiles + CDNState.failedFiles + CDNState.existingFiles,
            total: CDNState.totalFiles
        });
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_upload_to_github',
                nonce: wpCdnAdmin.nonce,
                urls: JSON.stringify(currentBatch)
            },
            success: function(response) {
                if (response.success) {
                    const results = response.data.results;
                    
                    // Update counters
                    CDNState.uploadedFiles += results.success || 0;
                    CDNState.failedFiles += results.failed || 0;
                    CDNState.existingFiles += results.exists || 0;
                    
                    // Update progress
                    const totalProcessed = CDNState.uploadedFiles + CDNState.failedFiles + CDNState.existingFiles;
                    const progress = (totalProcessed / CDNState.totalFiles) * 100;
                    
                    updateProgress(progress, `Batch ${batchIndex + 1} complete. Processing next batch...`, {
                        processed: totalProcessed,
                        total: CDNState.totalFiles
                    });
                    
                    // Process next batch
                    setTimeout(() => {
                        processUploadBatches(batches, batchIndex + 1, $button);
                    }, 500);
                } else {
                    // Error in batch
                    CDNState.failedFiles += currentBatch.length;
                    showNotification('Upload failed: ' + response.data.message, 'error');
                    completeUpload($button);
                }
            },
            error: function() {
                // Network error
                CDNState.failedFiles += currentBatch.length;
                showNotification('Network error during upload. Please try again.', 'error');
                completeUpload($button);
            }
        });
    }

    /**
     * Complete upload process and show results.
     */
    function completeUpload($button) {
        CDNState.isUploading = false;
        hideProgressOverlay();
        
        // Update button state
        $button.prop('disabled', false);
        $button.text('Upload to GitHub');
        
        // Show comprehensive results
        showUploadResults();
        
        // Ask user if they want to add URLs to custom list
        if (CDNState.uploadedFiles > 0 && confirm(wpCdnAdmin.i18n.addToCustomUrls)) {
            addSuccessfulUrlsToCustomList();
        }
    }

    /**
     * Show comprehensive upload results.
     */
    function showUploadResults() {
        const $result = $('.cdn-upload-result');
        const totalProcessed = CDNState.uploadedFiles + CDNState.failedFiles + CDNState.existingFiles;
        
        let resultHtml = '<div class="upload-summary">';
        resultHtml += `<div class="stat success"><span class="count">${CDNState.uploadedFiles}</span><span class="label">Uploaded</span></div>`;
        resultHtml += `<div class="stat exists"><span class="count">${CDNState.existingFiles}</span><span class="label">Already Exists</span></div>`;
        resultHtml += `<div class="stat failed"><span class="count">${CDNState.failedFiles}</span><span class="label">Failed</span></div>`;
        resultHtml += '</div>';
        
        resultHtml += '<h3>' + wpCdnAdmin.i18n.uploadComplete + '</h3>';
        
        if (CDNState.failedFiles > 0) {
            resultHtml += '<div class="notice notice-warning"><p><strong>Warning:</strong> Some files failed to upload. Please check the details below.</p></div>';
        } else {
            resultHtml += '<div class="notice notice-success"><p><strong>Success:</strong> All files processed successfully!</p></div>';
        }
        
        resultHtml += `<p><strong>Summary:</strong> ${CDNState.uploadedFiles} uploaded, ${CDNState.existingFiles} already on GitHub, ${CDNState.failedFiles} failed, ${totalProcessed} total.</p>`;
        
        $result.html(resultHtml).show();
    }

    /**
     * Add successful URLs to custom list.
     */
    function addSuccessfulUrlsToCustomList() {
        // Get successful URLs from the current batch results
        const successfulUrls = [];
        $('.url-item input[type="checkbox"]:checked').each(function() {
            successfulUrls.push($(this).val());
        });
        
        if (successfulUrls.length === 0) return;
        
        const customUrlsTextarea = $('#wp-cdn-custom-urls');
        
        if (customUrlsTextarea.length) {
            // Direct update
            const currentUrls = customUrlsTextarea.val();
            const existingUrls = currentUrls ? currentUrls.split("\n") : [];
            const newUrls = [];
            
            successfulUrls.forEach(function(url) {
                if (existingUrls.indexOf(url) === -1) {
                    newUrls.push(url);
                }
            });
            
            if (newUrls.length > 0) {
                const updatedUrls = currentUrls ? currentUrls + "\n" + newUrls.join("\n") : newUrls.join("\n");
                customUrlsTextarea.val(updatedUrls);
                showNotification(`${newUrls.length} URLs added to custom list.`, 'success');
            }
        } else {
            // AJAX update
            $.ajax({
                url: wpCdnAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'cdn_update_custom_urls',
                    nonce: wpCdnAdmin.nonce,
                    urls: JSON.stringify(successfulUrls)
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('URLs added to custom list.', 'success');
                    }
                }
            });
        }
    }

    /**
     * Show professional notification.
     */
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="notice notice-${type} is-dismissible cdn-notification" style="position: fixed; top: 32px; right: 20px; z-index: 10000; max-width: 400px;">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Start auto GitHub connection process.
     */
    function startAutoGitHubConnection() {
        const $button = $('#auto-connect-github');
        const $status = $('.auto-connect-status');
        
        $button.prop('disabled', true);
        $button.html('<span class="loading-spinner"></span> Connecting...');
        $status.removeClass('success error').text('').show();
        
        showProgressOverlay('Connecting to GitHub', 'Setting up automatic connection...');
        updateProgress(10, 'Initializing connection...');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_auto_connect_github',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Check if we need to redirect to GitHub for authorization
                    if (response.data.redirect_url) {
                        updateProgress(50, 'Opening GitHub authorization...');
                        $status.addClass('success').text(response.data.message);
                        
                        setTimeout(() => {
                            hideProgressOverlay();
                            // Open GitHub OAuth in popup window
                            openGitHubPopup(response.data.redirect_url);
                        }, 1000);
                    } else {
                        // Connection completed successfully
                        updateProgress(100, 'Connection successful!');
                        $status.addClass('success').text(response.data.message);
                        showNotification('GitHub connected successfully!', 'success');
                        
                        setTimeout(() => {
                            hideProgressOverlay();
                            location.reload();
                        }, 2000);
                    }
                } else {
                    hideProgressOverlay();
                    const errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                    $status.addClass('error').text(errorMessage);
                    showNotification('Connection failed: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                hideProgressOverlay();
                let errorMessage = 'Failed to connect to server';
                
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.status === 0) {
                    errorMessage = 'Network error - please check your internet connection';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied - please check your permissions';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error - please try again later';
                }
                
                $status.addClass('error').text(errorMessage);
                showNotification('Connection failed: ' + errorMessage, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.html('<span class="dashicons dashicons-admin-links"></span> Auto Connect with GitHub');
            }
        });
    }
    
    /**
     * Open GitHub OAuth in popup window
     * 
     * @param {string} url The GitHub OAuth URL
     */
    function openGitHubPopup(url) {
        // Calculate popup dimensions
        const width = 600;
        const height = 700;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;
        
        // Open popup window
        const popup = window.open(
            url,
            'github-oauth',
            `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
        );
        
        if (!popup) {
            // Popup blocked, fallback to redirect
            showNotification('Popup blocked. Redirecting to GitHub...', 'warning');
            setTimeout(() => {
                window.location.href = url;
            }, 2000);
            return;
        }
        
        // Monitor popup for completion
        const checkClosed = setInterval(() => {
            if (popup.closed) {
                clearInterval(checkClosed);
                // Check if connection was successful by reloading the page
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        }, 1000);
        
        // Focus on popup
        popup.focus();
        
        // Show instruction message
        showNotification('Please complete the GitHub authorization in the popup window.', 'info');
    }

    
    /**
     * Disconnect from GitHub.
     */
    function disconnectGitHub() {
        if (!confirm('Are you sure you want to disconnect from GitHub? This will remove all stored credentials.')) {
            return;
        }
        
        const $button = $('#disconnect-github');
        const $status = $('.disconnect-status');
        
        $button.prop('disabled', true);
        $button.html('<span class="loading-spinner"></span> Disconnecting...');
        $status.removeClass('success error').text('');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_disconnect_github',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.addClass('success').text(response.data.message);
                    showNotification('GitHub disconnected successfully!', 'success');
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    $status.addClass('error').text(response.data.message);
                    showNotification('Disconnect failed: ' + response.data.message, 'error');
                }
            },
            error: function() {
                $status.addClass('error').text('Failed to connect to server');
                showNotification('Failed to connect to server', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.text('Disconnect GitHub');
            }
        });
    }
	/**
     * View log file.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function viewLog($button) {
        var $content = $('#log-content');
        
        $button.prop('disabled', true);
        $content.html('Loading log data...');
        
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_view_log',
                nonce: wpCdnAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $content.html(response.data.content);
                } else {
                    $content.html('Error: ' + response.data.message);
                }
            },
            error: function() {
                $content.html('Failed to load log data. Server error.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Validate custom URLs.
     * 
     * @param {jQuery} $button The button that was clicked.
     */
    function validateCustomUrls($button) {
        var $status = $('.validate-status');
        var $progress = $('#validation-progress');
        var $results = $('#validation-results');
        
        $button.prop('disabled', true);
        $button.text(wpCdnAdmin.i18n.validatingUrls);
        $status.removeClass('success error').text('');
        $progress.show();
        $progress.find('.progress-bar').css('width', '0%');
        $progress.find('.progress-status').text('Preparing validation...');
        $results.empty().hide();
        
        // Start the validation process
        $.ajax({
            url: wpCdnAdmin.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cdn_validate_urls',
                nonce: wpCdnAdmin.nonce,
                batch_mode: false
            },
            success: function(response) {
                if (response.success) {
                    processUrlBatches(response.data.batches, response.data.total_urls);
                } else {
                    $status.addClass('error').text(response.data.message);
                    $progress.hide();
                    alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                    $button.prop('disabled', false);
                    $button.text('Validate & Auto-Upload');
                }
            },
            error: function() {
                $status.addClass('error').text('Failed to connect to server');
                $progress.hide();
                alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
                $button.prop('disabled', false);
                $button.text('Validate & Auto-Upload');
            }
        });
        
        /**
         * Process URL batches for validation.
         * 
         * @param {number} totalBatches Total number of batches.
         * @param {number} totalUrls    Total number of URLs.
         */
        function processUrlBatches(totalBatches, totalUrls) {
            var validationResults = {
                processed: 0,
                success: 0,
                failed: 0,
                exists: 0,
                details: []
            };
            
            // Get all custom URLs
            var customUrls = $('#wp-cdn-custom-urls').val().split('\n').filter(function(url) {
                return url.trim() !== '';
            });
            
            // Process in batches
            var batchSize = 5;
            var batches = [];
            
            for (var i = 0; i < customUrls.length; i += batchSize) {
                batches.push(customUrls.slice(i, i + batchSize));
            }
            
            processBatch(0, batches);
            
            function processBatch(batchIndex, batches) {
                if (batchIndex >= batches.length) {
                    // All batches complete
                    completeValidation(validationResults, totalUrls);
                    return;
                }
                
                // Update progress
                var progress = Math.round((batchIndex / batches.length) * 100);
                $('#validation-progress .progress-bar').css('width', progress + '%');
                $('#validation-progress .progress-status').text(
                    'Validating batch ' + (batchIndex + 1) + ' of ' + batches.length
                );
                
                $.ajax({
                    url: wpCdnAdmin.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cdn_validate_urls',
                        nonce: wpCdnAdmin.nonce,
                        batch_mode: true,
                        batch_urls: JSON.stringify(batches[batchIndex]),
                        batch_index: batchIndex,
                        total_batches: batches.length
                    },
                    success: function(response) {
                        if (response.success) {
                            var batchResults = response.data.batch_results;
                            
                            // Merge batch results
                            validationResults.processed += batchResults.processed;
                            validationResults.success += batchResults.success;
                            validationResults.failed += batchResults.failed;
                            validationResults.exists += batchResults.exists;
                            
                            if (batchResults.details && batchResults.details.length) {
                                validationResults.details = validationResults.details.concat(batchResults.details);
                            }
                            
                            // Process next batch
                            processBatch(batchIndex + 1, batches);
                        } else {
                            // Error in batch
                            $('#validation-progress .progress-status').text('Error: ' + response.data.message);
                            $('#validation-progress .progress-bar').css('width', '100%').addClass('error');
                            
                            alert(wpCdnAdmin.i18n.error + ': ' + response.data.message);
                            $('#validate-urls-button').prop('disabled', false).text('Validate & Auto-Upload');
                        }
                    },
                    error: function() {
                        $('#validation-progress .progress-status').text('Error: Failed to connect to server');
                        $('#validation-progress .progress-bar').css('width', '100%').addClass('error');
                        
                        alert(wpCdnAdmin.i18n.error + ': ' + 'Failed to connect to server');
                        $('#validate-urls-button').prop('disabled', false).text('Validate & Auto-Upload');
                    }
                });
            }
        }
        
        /**
         * Complete validation process.
         * 
         * @param {Object} results   Validation results.
         * @param {number} totalUrls Total number of URLs.
         */
        function completeValidation(results, totalUrls) {
            var $button = $('#validate-urls-button');
            var $status = $('.validate-status');
            var $progress = $('#validation-progress');
            var $results = $('#validation-results');
            
            // Update progress to 100%
            $progress.find('.progress-bar').css('width', '100%').addClass('success');
            $progress.find('.progress-status').text('Validation completed');
            
            // Create result message
            var resultMessage = 'Processed ' + results.processed + ' URLs: ' + 
                                results.success + ' uploaded, ' + 
                                results.exists + ' already on GitHub, ' + 
                                results.failed + ' failed.';
            
            $status.addClass(results.failed > 0 ? 'warning' : 'success').text(resultMessage);
            
            // Show detailed results
            var resultHtml = '<h3>Validation Results</h3>';
            resultHtml += '<p><strong>' + resultMessage + '</strong></p>';
            
            if (results.details && results.details.length) {
                resultHtml += '<div class="validation-details">';
                resultHtml += '<table class="widefat">';
                resultHtml += '<thead><tr><th>URL</th><th>Status</th><th>Message</th></tr></thead>';
                resultHtml += '<tbody>';
                
                results.details.forEach(function(detail) {
                    var statusClass = '';
                    switch(detail.status) {
                        case 'uploaded': statusClass = 'status-success'; break;
                        case 'failed': statusClass = 'status-error'; break;
                        case 'exists': statusClass = 'status-exists'; break;
                    }
                    
                    resultHtml += '<tr>';
                    resultHtml += '<td>' + detail.url + '</td>';
                    resultHtml += '<td class="' + statusClass + '">' + detail.status + '</td>';
                    resultHtml += '<td>' + detail.message + '</td>';
                    resultHtml += '</tr>';
                });
                
                resultHtml += '</tbody></table></div>';
            }
            
            $results.html(resultHtml).show();
            
            // Re-enable button
            $button.prop('disabled', false).text('Validate & Auto-Upload');
        }
    }
    
    /**
     * Categorize URLs by file type.
     * 
     * @param {Array} urls Array of URLs.
     * @return {Object} URLs categorized by file type.
     */
    function categorizeUrls(urls) {
        var result = {
            'js': [],
            'css': [],
            'png': [],
            'jpg': [],
            'jpeg': [],
            'gif': [],
            'svg': [],
            'webp': [],
            'ico': [],
            'woff': [],
            'woff2': [],
            'ttf': [],
            'eot': [],
            'other': []
        };
        
        urls.forEach(function(url) {
            var extension = url.split('.').pop().split('?')[0].toLowerCase();
            
            if (result[extension] !== undefined) {
                result[extension].push(url);
            } else {
                result.other.push(url);
            }
        });
        
        return result;
    }
    
    /**
     * Get user-friendly type label.
     * 
     * @param {string} type File type.
     * @return {string} User-friendly label.
     */
    function getTypeLabel(type) {
        switch (type) {
            case 'js': return 'JavaScript Files';
            case 'css': return 'CSS Files';
            case 'png': return 'PNG Images';
            case 'jpg': return 'JPG Images';
            case 'jpeg': return 'JPEG Images';
            case 'gif': return 'GIF Images';
            case 'svg': return 'SVG Images';
            case 'webp': return 'WebP Images';
            case 'ico': return 'Icon Files';
            case 'woff': return 'WOFF Fonts';
            case 'woff2': return 'WOFF2 Fonts';
            case 'ttf': return 'TTF Fonts';
            case 'eot': return 'EOT Fonts';
            case 'other': return 'Other Files';
            default: return type.toUpperCase() + ' Files';
        }
    }
    
    /**
     * Get icon for file type.
     * 
     * @param {string} type File type.
     * @return {string} HTML icon.
     */
    function getTypeIcon(type) {
        var icon = '';
        switch (type) {
            case 'js': 
                icon = '<span class="dashicons dashicons-media-code" style="color: #f0db4f;"></span>';
                break;
            case 'css': 
                icon = '<span class="dashicons dashicons-editor-code" style="color: #264de4;"></span>';
                break;
            case 'png': 
            case 'jpg': 
            case 'jpeg': 
            case 'gif': 
            case 'svg':
            case 'webp':
            case 'ico':
                icon = '<span class="dashicons dashicons-format-image" style="color: #ff9800;"></span>';
                break;
            case 'woff':
            case 'woff2':
            case 'ttf':
            case 'eot':
                icon = '<span class="dashicons dashicons-editor-textcolor" style="color: #607d8b;"></span>';
                break;
            case 'other': 
                icon = '<span class="dashicons dashicons-media-default" style="color: #9e9e9e;"></span>';
                break;
            default:
                icon = '<span class="dashicons dashicons-media-default" style="color: #9e9e9e;"></span>';
        }
        return icon;
    }

     /**
      * Show progress overlay with message.
      */
     function showProgressOverlay(title, message) {
         const overlay = `
             <div id="cdn-progress-overlay" class="cdn-progress-overlay">
                 <div class="cdn-progress-content">
                     <div class="cdn-progress-header">
                         <h3>${title}</h3>
                         <p class="cdn-progress-message">${message}</p>
                     </div>
                     <div class="cdn-progress-bar">
                         <div class="cdn-progress-fill" style="width: 0%"></div>
                     </div>
                     <div class="cdn-progress-text">0%</div>
                 </div>
             </div>
         `;
         
         $('body').append(overlay);
     }
     
     /**
      * Update progress bar.
      */
     function updateProgress(percentage, message) {
         const $overlay = $('#cdn-progress-overlay');
         if ($overlay.length === 0) return;
         
         const $fill = $overlay.find('.cdn-progress-fill');
         const $text = $overlay.find('.cdn-progress-text');
         const $message = $overlay.find('.cdn-progress-message');
         
         $fill.css('width', percentage + '%');
         $text.text(percentage + '%');
         
         if (message) {
             $message.text(message);
         }
     }
     
     /**
      * Hide progress overlay.
      */
     function hideProgressOverlay() {
         $('#cdn-progress-overlay').fadeOut(300, function() {
             $(this).remove();
         });
     }
     
     /**
      * Show notification message.
      */
     function showNotification(message, type) {
         const notification = `
             <div class="cdn-notification cdn-notification-${type}">
                 <div class="cdn-notification-content">
                     <span class="cdn-notification-icon"></span>
                     <span class="cdn-notification-message">${message}</span>
                     <button class="cdn-notification-close">&times;</button>
                 </div>
             </div>
         `;
         
         const $notification = $(notification);
         $('body').append($notification);
         
         // Auto hide after 5 seconds
         setTimeout(() => {
             $notification.fadeOut(300, function() {
                 $(this).remove();
             });
         }, 5000);
         
         // Manual close
         $notification.find('.cdn-notification-close').on('click', function() {
             $notification.fadeOut(300, function() {
                 $(this).remove();
             });
         });
     }
     
     /**
      * Auto-fill repository name based on username
      */
     function autoFillRepositoryName() {
         const username = $('#wp-cdn-github-username').val().trim();
         const repository = $('#wp-cdn-github-repository').val().trim();
         
         // Only auto-fill if repository is empty
         if (username && !repository) {
             const siteName = $('input[name="blogname"]').val() || 'wordpress-cdn';
             const suggestedRepo = siteName.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');
             $('#wp-cdn-github-repository').val(suggestedRepo);
             
             // Add visual feedback
             $('#wp-cdn-github-repository').addClass('auto-filled');
             setTimeout(() => {
                 $('#wp-cdn-github-repository').removeClass('auto-filled');
             }, 2000);
         }
     }
     
     /**
      * Enhanced manual setup toggle with animation
      */
     function toggleManualSetup() {
         const $form = $('.cdn-manual-form');
         const $toggle = $('.cdn-toggle-manual .dashicons');
         
         if ($form.is(':visible')) {
             $form.slideUp(300);
             $toggle.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
         } else {
             $form.slideDown(300);
             $toggle.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
         }
     }

})(jQuery);