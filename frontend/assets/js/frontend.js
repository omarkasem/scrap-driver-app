jQuery(document).ready(function($) {
    // Copy to clipboard functionality
    $('.copy-btn').on('click', function() {
        const text = $(this).parent('.copyable').data('copy');
        
        // Create temporary textarea element
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed'; // Avoid scrolling to bottom
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            // Try modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    $(this).addClass('copied');
                    setTimeout(() => $(this).removeClass('copied'), 2000);
                });
            } else {
                // Fallback to execCommand
                document.execCommand('copy');
                $(this).addClass('copied');
                setTimeout(() => $(this).removeClass('copied'), 2000);
            }
        } catch (err) {
            console.error('Failed to copy text: ', err);
        } finally {
            // Clean up
            document.body.removeChild(textarea);
        }
    });

    // Status update form
    $('#sda-status-form').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: '/wp-json/scrap-driver/v1/collections/<?php echo $collection_id; ?>/status',
            method: 'POST',
            data: {
                status: formData.get('status'),
                notes: formData.get('notes')
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            success: function(response) {
                alert('Status updated successfully!');
            },
            error: function(xhr) {
                alert('Error updating status. Please try again.');
            }
        });
    });

    // Photo upload form
    $('#sda-photo-form').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: '/wp-json/scrap-driver/v1/collections/<?php echo $collection_id; ?>/photos',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            success: function(response) {
                const img = $('<img>').attr('src', response.url);
                $('#sda-photo-gallery').append(img);
                $('#sda-photo-form')[0].reset();
            },
            error: function(xhr) {
                alert('Error uploading photo. Please try again.');
            }
        });
    });

    // Complete collection button
    $('#sda-complete-collection').on('click', function() {
        if (confirm('Are you sure you want to complete this collection?')) {
            $('#sda-status-form select[name="status"]').val('completed');
            $('#sda-status-form').submit();
        }
    });
});