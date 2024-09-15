jQuery(document).ready(function ($) {
    function sendExtraFactoryCode() {
        const $emailCheckMessage = $('#email-check-message');
        const username = $("#user_login").val().trim();

        // Show extra factor section if username is provided
        if (username) {
            // Append message if it doesn't exist
            if ($emailCheckMessage.length === 0) {
                $("#user_login").after(`<p id="email-check-message" style="color: green; margin-bottom: 5px;">${extraFactor.message}</p>`);
            }

            // Send AJAX request
            $.ajax({
                url: extraFactor.ajax_url,
                type: 'POST',
                data: {
                    action: 'send_extra_factor_code',
                    username: username,
                    nonce: extraFactor.nonce,
                },
                success: function (response) {
                    console.log(response);
                },
                error: function (textStatus, errorThrown) {
                    console.error('Error sending extra factor code:', textStatus, errorThrown);
                }
            });
        }
    }

    $('#user_login').on('blur', sendExtraFactoryCode);
    $('#extra_factor').on('focus', sendExtraFactoryCode);
    
});