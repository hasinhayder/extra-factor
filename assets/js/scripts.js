jQuery(document).ready(function ($) {
    $('#extra_factor').on('focus', function () {
        const $emailCheckMessage = $('#email-check-message');
        const username = $("#user_login").val().trim();

        // Remove existing message
        $emailCheckMessage.remove();

        // Show extra factor section if username is provided
        if (username) {
            $('#extra_factor').show();

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
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error('Error sending extra factor code:', textStatus, errorThrown);
                }
            });
        }
    });
});