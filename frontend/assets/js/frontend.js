jQuery(document).ready(function($) {
    // Copy to clipboard functionality
    $('.copy-btn').on('click', function() {
        const text = $(this).parent('.copyable').data('copy');
        navigator.clipboard.writeText(text).then(() => {
            alert('Copied to clipboard!');
        });
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