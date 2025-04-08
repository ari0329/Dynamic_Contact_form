jQuery(document).ready(function($) {
    // Verify script is loaded
    console.log('DCFM Form Handler Loaded');

    // Check if dcfmAjax is available
    if (typeof dcfmAjax === 'undefined') {
        console.error('dcfmAjax is not defined. Check wp_localize_script in PHP.');
        return;
    } else {
        console.log('AJAX Config:', dcfmAjax);
    }

    // Handle form submission
    $('.dcfm-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var formId = form.find('input[name="form_id"]').val();
        var messageDiv = $('#form-message-' + formId);
        var userAnswer = form.find('#captcha_input').val().trim();
        var correctAnswer = form.find('#captcha_answer-' + formId).val();

        // Debugging output
        console.log('Form Submission Attempted');
        console.log('Form ID:', formId);
        console.log('User Captcha:', userAnswer);
        console.log('Correct Captcha:', correctAnswer);
        console.log('Form Data:', form.serialize());

        // Clear previous messages
        messageDiv.removeClass('success error').hide();

        // Basic client-side validation
        if (!userAnswer) {
            messageDiv.addClass('error').html('Please enter the captcha.').show();
            return;
        }

        // AJAX submission
        $.ajax({
            url: dcfmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'submit_contact_form',
                nonce: dcfmAjax.nonce,
                form_id: formId,
                captcha_input: userAnswer,
                captcha_answer: correctAnswer,
                ...form.serializeArray().reduce((obj, item) => {
                    obj[item.name] = item.value;
                    return obj;
                }, {})
            },
            beforeSend: function() {
                console.log('Sending AJAX request');
            },
            success: function(response) {
                console.log('AJAX Success Response:', response);
                if (response.success) {
                    messageDiv.removeClass('error').addClass('success')
                        .html(response.data)
                        .show();
                    form[0].reset();
                } else {
                    messageDiv.removeClass('success').addClass('error')
                        .html(response.data || 'An unknown error occurred.')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                messageDiv.removeClass('success').addClass('error')
                    .html('An error occurred: ' + error)
                    .show();
            }
        });
    });

    // Handle captcha reset
    $('.reset-captcha').on('click', function(e) {
        e.preventDefault();
        var form = $(this).closest('.dcfm-form');
        var formId = form.find('input[name="form_id"]').val();
        var captchaDisplay = $('#captcha-display-' + formId);
        var captchaAnswer = $('#captcha_answer-' + formId);
        
        // Generate new captcha
        var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var newCaptcha = '';
        for (var i = 0; i < 4; i++) {
            newCaptcha += characters.charAt(Math.floor(Math.random() * characters.length));
        }
        
        captchaDisplay.text(newCaptcha);
        captchaAnswer.val(newCaptcha);
    });
});