/**
 * Bulk Page UI/UX
 *
 */
jQuery(function ($) {

        /**
         * Remove the quick setup dialog
         */
        function remove_dialog() {
            $('dialog#smush-quick-setup').remove();
        }

        //Show the Quick Setup Dialog
        if ($('#smush-quick-setup').size() > 0) {
            WDP.showOverlay("#smush-quick-setup", {
                title: ps_smush_msgs.quick_setup_title,
                class: 'no-close ps-smush-overlay'
            });
            remove_dialog();
        }
    }
);