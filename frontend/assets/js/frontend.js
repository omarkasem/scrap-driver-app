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


    if ($('#collections-table').length) {
        $('#collections-table').DataTable({
            order: [[3, 'asc']], // Sort by collection date by default
            pageLength: 25,
            language: {
                search: sdaDataTableTranslations.search,
                lengthMenu: sdaDataTableTranslations.lengthMenu,
                info: sdaDataTableTranslations.info,
                infoEmpty: sdaDataTableTranslations.infoEmpty,
                infoFiltered: sdaDataTableTranslations.infoFiltered,
                emptyTable: sdaDataTableTranslations.emptyTable,
                paginate: {
                    first: sdaDataTableTranslations.first,
                    last: sdaDataTableTranslations.last,
                    next: sdaDataTableTranslations.next,
                    previous: sdaDataTableTranslations.previous
                }
            }
        });
    }

    // Status form submission
    $('#sda-status-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        submitButton.prop('disabled', true);
        
        $.ajax({
            url: sdaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sda_update_collection_status',
                ...Object.fromEntries(new FormData(this))
            },
            success: function(response) {
                if (response.success) {
                    form.find('textarea[name="notes"]').val('');
                    $('.sda-notes-display').html(response.data.notes);
                    alert(response.data.message);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Photo upload
    $('#sda-photo-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        submitButton.prop('disabled', true);
        
        const formData = new FormData(this);
        formData.append('action', 'sda_upload_collection_photo');
        
        $.ajax({
            url: sdaAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const photoHtml = `
                        <div class="sda-photo-wrapper">
                            <img src="${response.data.image_url}" class="attachment-medium size-medium" alt="">
                            <button class="sda-remove-photo" data-id="${response.data.attachment_id}">×</button>
                        </div>
                    `;
                    $('#sda-photo-gallery').append(photoHtml);
                    form.find('input[type="file"]').val('');
                    alert(response.data.message);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Photo removal
    $(document).on('click', '.sda-remove-photo', function(e) {
        e.preventDefault();
        const button = $(this);
        const photoWrapper = button.closest('.sda-photo-wrapper');
        const collectionId = $('#sda-photo-form input[name="collection_id"]').val();
        const nonce = $('#sda-photo-form input[name="nonce"]').val();
        
        if (confirm('Are you sure you want to remove this photo?')) {
            $.ajax({
                url: sdaAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sda_remove_collection_photo',
                    collection_id: collectionId,
                    attachment_id: button.data('id'),
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        photoWrapper.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });
});