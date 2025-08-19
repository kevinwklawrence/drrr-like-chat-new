$(document).ready(function() {
            let usernameCheckTimeout;
            let emailCheckTimeout;
            
            // Real-time username validation
            $('#username').on('input', function() {
                const username = $(this).val();
                const field = $(this);
                
                clearTimeout(usernameCheckTimeout);
                
                if (username.length < 3) {
                    field.removeClass('is-valid is-invalid');
                    return;
                }
                
                if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Username can only contain letters, numbers, and underscores (3-20 characters)');
                    return;
                }
                
                // Check availability after delay
                usernameCheckTimeout = setTimeout(() => {
                    checkUsernameAvailability(username, field);
                }, 500);
            });
            
            // Real-time email validation
            $('#email').on('input', function() {
                const email = $(this).val();
                const field = $(this);
                
                clearTimeout(emailCheckTimeout);
                
                if (email.length === 0) {
                    field.removeClass('is-valid is-invalid');
                    return;
                }
                
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Please enter a valid email address');
                    return;
                }
                
                // Check availability after delay
                emailCheckTimeout = setTimeout(() => {
                    checkEmailAvailability(email, field);
                }, 500);
            });
            
            // Password strength checker
            $('#password').on('input', function() {
                const password = $(this).val();
                const field = $(this);
                const strengthBar = $('#passwordStrengthBar');
                
                if (password.length === 0) {
                    field.removeClass('is-valid is-invalid');
                    strengthBar.removeClass().addClass('password-strength-bar');
                    return;
                }
                
                if (password.length < 6) {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Password must be at least 6 characters');
                    strengthBar.removeClass().addClass('password-strength-bar');
                    return;
                }
                
                const strength = calculatePasswordStrength(password);
                strengthBar.removeClass().addClass('password-strength-bar strength-' + strength.level);
                
                if (strength.score >= 3) {
                    field.removeClass('is-invalid').addClass('is-valid');
                } else {
                    field.removeClass('is-valid').addClass('is-invalid');
                    field.siblings('.invalid-feedback').text('Password could be stronger');
                }
                
                // Check confirm password if it has value
                if ($('#confirm_password').val()) {
                    checkPasswordMatch();
                }
            });
            
            // Confirm password validation
            $('#confirm_password').on('input', checkPasswordMatch);
            
            // Form submission
            $('#registerForm').on('submit', function(e) {
                e.preventDefault();
                
                // Final validation
                if (!validateForm()) {
                    return;
                }
                
                const formData = {
                    username: $('#username').val().trim(),
                    email: $('#email').val().trim(),
                    password: $('#password').val(),
                    confirm_password: $('#confirm_password').val()
                };
                
                // Show loading state
                const submitBtn = $('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating Account...');
                
                $.ajax({
                    url: 'register.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert('Registration successful! Redirecting to lounge...');
                            window.location.href = 'lounge.php';
                        } else {
                            alert('Error: ' + response.message);
                            submitBtn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('Connection error: ' + error);
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
        
        function checkUsernameAvailability(username, field) {
            // In a real implementation, you'd make an AJAX call to check availability
            // For now, we'll just validate format
            field.removeClass('is-invalid').addClass('is-valid');
        }
        
        function checkEmailAvailability(email, field) {
            // In a real implementation, you'd make an AJAX call to check availability
            // For now, we'll just validate format
            field.removeClass('is-invalid').addClass('is-valid');
        }
        
        function calculatePasswordStrength(password) {
            let score = 0;
            let level = 'weak';
            
            // Length
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            
            // Character types
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            if (score <= 2) level = 'weak';
            else if (score <= 3) level = 'fair';
            else if (score <= 4) level = 'good';
            else level = 'strong';
            
            return { score, level };
        }
        
        function checkPasswordMatch() {
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();
            const field = $('#confirm_password');
            
            if (confirmPassword.length === 0) {
                field.removeClass('is-valid is-invalid');
                return;
            }
            
            if (password === confirmPassword) {
                field.removeClass('is-invalid').addClass('is-valid');
            } else {
                field.removeClass('is-valid').addClass('is-invalid');
                field.siblings('.invalid-feedback').text('Passwords do not match');
            }
        }
        
        function validateForm() {
            let isValid = true;
            
            // Check all required fields have valid class
            $('.form-control[required]').each(function() {
                if (!$(this).hasClass('is-valid') || $(this).val().trim() === '') {
                    isValid = false;
                    if (!$(this).hasClass('is-invalid')) {
                        $(this).addClass('is-invalid');
                    }
                }
            });
            
            return isValid;
        }