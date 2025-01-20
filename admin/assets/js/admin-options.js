(function($) {
    'use strict';

    let autocomplete;

    function initAutocomplete() {
        const addressInput = document.getElementById('sd_address_autocomplete');
        if (addressInput) {
            autocomplete = new google.maps.places.Autocomplete(addressInput, {
                types: ['address']
            });
        }
    }

    $(document).ready(function() {
        // Handle API key field changes
        $(sdAdminOptions.apiKeyField).on('change', function() {
            const apiKey = $(this).val();
            if (apiKey) {
                $(sdAdminOptions.addressWrapper).show();
            } else {
                $(sdAdminOptions.addressWrapper).hide();
            }
        });

        // Initialize autocomplete if API key exists
        if (typeof google !== 'undefined' && google.maps) {
            initAutocomplete();
        }
    });

})(jQuery); 