// Scope global vars
window.site = window.site || {};
//var google = google || {};

(function ($, site) {

    'use strict';

    // Scope global variables
    site = site || {};

    $(document).ready(function () {
        $('input[name="ikros_options[invoice_numbering_type]"]').change(function () {
            var numberingType = $(this).val();
            if (numberingType == 'ikros') {
                $('.invoice_number_format-wrapper').fadeOut(500);
                $('.next_invoice_number-wrapper').fadeOut(500);
                setTimeout(function () {
                    $('.ikros_invoice_numbering_list-wrapper').fadeIn(500);
                }, 500);


            } else if (numberingType == 'plugin') {
                $('.ikros_invoice_numbering_list-wrapper').fadeOut(500, function () {
                    $('.invoice_number_format-wrapper').fadeIn(500);
                    $('.next_invoice_number-wrapper').fadeIn(500);
                });


            }
        })
    });


})(jQuery, window.site);