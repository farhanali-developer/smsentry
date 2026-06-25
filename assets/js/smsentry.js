/**
 * SMSentry — frontend JavaScript
 * Handles: OTP resend countdown (login page), phone setup AJAX (profile), admin test/validate.
 */
(function ($) {
	'use strict';

	/* ── Helpers ─────────────────────────────────────────── */

	function showResult($el, message, isError) {
		$el
			.removeClass('is-success is-error')
			.addClass(isError ? 'is-error' : 'is-success')
			.text(message)
			.show();
	}

	/**
	 * Combine the selected country's dial code with the national number
	 * into a single E.164 string, e.g. "+14155551234".
	 * Accepts either the number input itself or any element inside its
	 * .smsentry-phone-group wrapper.
	 */
	function getFullPhone($el) {
		var $group = $el.hasClass('smsentry-phone-group') ? $el : $el.closest('.smsentry-phone-group');
		var dialCode = $group.find('.smsentry-country-trigger').data('dial-code');
		var national = $group.find('.smsentry-phone-number').val().replace(/\D/g, '');

		if (!dialCode || !national) {
			return '';
		}

		return '+' + dialCode + national;
	}

	/* ── Custom country picker (trigger button + searchable dropdown) ───── */

	function closeAllCountryDropdowns(except) {
		$('.smsentry-country-dropdown').each(function () {
			if (this !== except) {
				$(this).attr('hidden', true);
				$(this).closest('.smsentry-country-picker').find('.smsentry-country-trigger').attr('aria-expanded', 'false');
			}
		});
	}

	function initCountryPickers() {
		$('.smsentry-country-picker').each(function () {
			var $picker   = $(this);
			var $trigger  = $picker.find('.smsentry-country-trigger');
			var $dropdown = $picker.find('.smsentry-country-dropdown');
			var $search   = $picker.find('.smsentry-country-search');
			var $list     = $picker.find('.smsentry-country-list');
			var $items    = $list.find('li');
			var $hidden   = $picker.find('.smsentry-country-value');

			function openDropdown() {
				closeAllCountryDropdowns($dropdown.get(0));
				$dropdown.attr('hidden', false);
				$trigger.attr('aria-expanded', 'true');
				$search.val('');
				$items.show();
				window.setTimeout(function () { $search.focus(); }, 0);
			}

			function closeDropdown() {
				$dropdown.attr('hidden', true);
				$trigger.attr('aria-expanded', 'false');
			}

			function selectItem($li) {
				var iso      = $li.data('iso');
				var dialCode = $li.data('dial-code');

				$trigger.find('.smsentry-country-trigger-flag').text($li.find('.smsentry-country-flag').text());
				$trigger.find('.smsentry-country-trigger-code').text('+' + dialCode);
				$trigger.attr('data-dial-code', dialCode).data('dial-code', dialCode);
				$hidden.val(iso);

				$items.removeClass('is-selected');
				$li.addClass('is-selected');

				closeDropdown();
				$trigger.focus();
			}

			$trigger.on('click', function (e) {
				e.stopPropagation();
				if ($dropdown.attr('hidden') === undefined) {
					closeDropdown();
				} else {
					openDropdown();
				}
			});

			$search.on('input', function () {
				var term       = $(this).val().toLowerCase();
				var anyVisible = false;

				$items.each(function () {
					var $li     = $(this);
					var matches = !term
						|| $li.data('search').indexOf(term) !== -1
						|| String($li.data('dial-code')).indexOf(term) !== -1
						|| String($li.data('iso')).toLowerCase().indexOf(term) !== -1;
					$li.toggle(matches);
					anyVisible = anyVisible || matches;
				});

				$picker.find('.smsentry-country-list-empty').attr('hidden', anyVisible);
			});

			$search.on('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					var $firstVisible = $items.filter(':visible').first();
					if ($firstVisible.length) {
						selectItem($firstVisible);
					}
				}
			});

			$items.on('click', function () {
				selectItem($(this));
			});

			$picker.on('keydown', function (e) {
				if (e.key === 'Escape') {
					closeDropdown();
					$trigger.focus();
				}
			});
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('.smsentry-country-picker').length) {
				closeAllCountryDropdowns();
			}
		});
	}

	/* ── Login page: resend countdown + resend button ────── */

	var countdownInterval;

	function startCountdown(seconds) {
		var $timer  = $('#smsentry-resend-timer');
		var $count  = $('#smsentry-countdown');
		var $btn    = $('#smsentry-resend-btn');

		$timer.show();
		$btn.hide();

		clearInterval(countdownInterval);

		countdownInterval = setInterval(function () {
			seconds -= 1;
			$count.text(seconds);

			if (seconds <= 0) {
				clearInterval(countdownInterval);
				$timer.hide();
				$btn.show();
			}
		}, 1000);
	}

	function initResendTimer() {
		var $timer = $('#smsentry-resend-timer');
		if (!$timer.length) return;

		var remaining = parseInt($timer.data('remaining'), 10);
		if (remaining > 0) {
			startCountdown(remaining);
		}
	}

	function initResendButton() {
		$(document).on('click', '#smsentry-resend-btn', function () {
			var $btn    = $(this);
			var ajaxUrl = $btn.data('ajax');
			var nonce   = $btn.data('nonce');
			var $result = $('#smsentry-resend-result');

			$btn.prop('disabled', true).text(
				typeof smsentryAdmin !== 'undefined'
					? smsentryAdmin.i18n.sending
					: 'Sending...'
			);

			$.post(ajaxUrl, {
				action : 'smsentry_resend_otp',
				nonce  : nonce
			})
			.done(function (response) {
				if (response.success) {
					showResult($result, response.data.message, false);
					startCountdown(response.data.remaining || 60);
				} else {
					showResult($result, response.data.message, true);
					$btn.prop('disabled', false).text('Resend code');
				}
			})
			.fail(function () {
				showResult($result, 'Request failed. Please try again.', true);
				$btn.prop('disabled', false).text('Resend code');
			});
		});
	}

	/* ── Login page: toggle between SMS code and backup code ───── */

	function initBackupCodeToggle() {
		var $toggle = $('#smsentry-use-backup-toggle');
		if (!$toggle.length) return;

		var $mode  = $('#smsentry_mode');
		var $input = $('#smsentry_otp');
		var $label = $('#smsentry_otp_label');

		$toggle.on('click', function () {
			var usingBackup = $mode.val() === 'backup';

			if (usingBackup) {
				$mode.val('otp');
				$label.text('Verification Code');
				$input.attr({ maxlength: 6, placeholder: '------', inputmode: 'numeric' });
				$toggle.text('Use a backup code instead');
			} else {
				$mode.val('backup');
				$label.text('Backup Code');
				$input.attr({ maxlength: 11, placeholder: 'XXXXX-XXXXX', inputmode: 'text' });
				$toggle.text('Use SMS code instead');
			}

			$input.val('').focus();
		});
	}

	/* ── Profile page: phone setup AJAX ─────────────────── */

	function initProfileSetup() {
		if (!$('#smsentry-profile-2fa').length) return;

		var cfg = typeof smsentryProfile !== 'undefined' ? smsentryProfile : {};

		// Send OTP to phone
		$(document).on('click', '#smsentry-send-otp', function () {
			var phone   = getFullPhone($('#smsentry-new-phone'));
			var $result = $('#smsentry-setup-result');
			var $btn    = $(this);

			if (!phone) {
				showResult($result, cfg.i18n.phoneRequired || 'Enter a phone number first.', true);
				return;
			}

			$btn.prop('disabled', true).text(cfg.i18n.sending || 'Sending...');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_send_phone_otp',
				nonce  : cfg.nonce,
				phone  : phone
			})
			.done(function (response) {
				if (response.success) {
					showResult($result, response.data.message, false);
					$('#smsentry-otp-row').slideDown(200);
					$('#smsentry-otp-input').focus();
					$btn.text(cfg.i18n.resendCode || 'Resend Code');
				} else {
					showResult($result, response.data.message, true);
					$btn.text(cfg.i18n.sendCode || 'Send Verification Code');
				}
				$btn.prop('disabled', false);
			})
			.fail(function () {
				showResult($result, 'Request failed.', true);
				$btn.prop('disabled', false).text(cfg.i18n.sendCode || 'Send Verification Code');
			});
		});

		// Verify OTP entered on profile page
		$(document).on('click', '#smsentry-verify-otp', function () {
			var phone   = getFullPhone($('#smsentry-new-phone'));
			var otp     = $('#smsentry-otp-input').val().trim();
			var $result = $('#smsentry-setup-result');
			var $btn    = $(this);

			$btn.prop('disabled', true).text(cfg.i18n.verifying || 'Verifying...');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_verify_phone_otp',
				nonce  : cfg.nonce,
				phone  : phone,
				otp    : otp
			})
			.done(function (response) {
				if (response.success) {
					showResult($result, response.data.message, false);
					setTimeout(function () { location.reload(); }, 1800);
				} else {
					showResult($result, response.data.message, true);
					$btn.prop('disabled', false).text(cfg.i18n.verifyCode || 'Verify Code');
				}
			})
			.fail(function () {
				showResult($result, 'Request failed.', true);
				$btn.prop('disabled', false).text(cfg.i18n.verifyCode || 'Verify Code');
			});
		});

		// Toggle 2FA on/off
		$(document).on('click', '#smsentry-toggle-2fa', function () {
			var $btn    = $(this);
			var enabled = $btn.data('enabled') === '1' ? '0' : '1';
			var $result = $('#smsentry-toggle-result');

			$.post(cfg.ajaxUrl, {
				action  : 'smsentry_toggle_2fa',
				nonce   : cfg.nonce,
				enabled : enabled
			})
			.done(function (response) {
				if (response.success) {
					showResult($result, response.data.message, false);
					setTimeout(function () { location.reload(); }, 1200);
				} else {
					showResult($result, response.data.message, true);
				}
			});
		});

		// Remove 2FA entirely
		$(document).on('click', '#smsentry-remove-2fa', function () {
			var cfg     = typeof smsentryProfile !== 'undefined' ? smsentryProfile : {};
			var confirm_msg = cfg.i18n && cfg.i18n.confirmRemove
				? cfg.i18n.confirmRemove
				: 'Remove 2FA from your account?';

			if (!window.confirm(confirm_msg)) return;

			var $result = $('#smsentry-toggle-result');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_remove_2fa',
				nonce  : cfg.nonce
			})
			.done(function (response) {
				if (response.success) {
					showResult($result, response.data.message, false);
					setTimeout(function () { location.reload(); }, 1200);
				} else {
					showResult($result, response.data.message, true);
				}
			});
		});

		// Generate / regenerate backup codes
		$(document).on('click', '#smsentry-generate-backup-codes', function () {
			var $btn        = $(this);
			var isRegen     = $btn.text().trim() === (cfg.i18n.regenerateCodes || 'Regenerate Backup Codes');
			var confirmMsg  = cfg.i18n.confirmRegen || 'This will invalidate your existing backup codes. Continue?';

			if (isRegen && !window.confirm(confirmMsg)) {
				return;
			}

			$btn.prop('disabled', true).text(cfg.i18n.generating || 'Generating...');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_generate_backup_codes',
				nonce  : cfg.nonce
			})
			.done(function (response) {
				if (response.success) {
					renderBackupCodes(response.data.codes, cfg);
					$btn.text(cfg.i18n.regenerateCodes || 'Regenerate Backup Codes');
				} else {
					showResult($('#smsentry-toggle-result'), response.data.message, true);
					$btn.text(cfg.i18n.generateCodes || 'Generate Backup Codes');
				}
				$btn.prop('disabled', false);
			})
			.fail(function () {
				showResult($('#smsentry-toggle-result'), 'Request failed.', true);
				$btn.prop('disabled', false).text(cfg.i18n.generateCodes || 'Generate Backup Codes');
			});
		});

		// Opt into email-based 2FA (only shown when no phone is verified yet)
		$(document).on('click', '#smsentry-enable-email-2fa', function () {
			var $btn    = $(this);
			var $result = $('#smsentry-email-2fa-result');

			$btn.prop('disabled', true).text(cfg.i18n.enablingEmail || 'Enabling...');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_enable_email_2fa',
				nonce  : cfg.nonce
			})
			.done(function (response) {
				if (response.success) {
					showResult($result, response.data.message, false);
					setTimeout(function () { location.reload(); }, 1200);
				} else {
					showResult($result, response.data.message, true);
					$btn.prop('disabled', false).text(cfg.i18n.useEmailInstead || 'Use Email Instead');
				}
			})
			.fail(function () {
				showResult($result, 'Request failed.', true);
				$btn.prop('disabled', false).text(cfg.i18n.useEmailInstead || 'Use Email Instead');
			});
		});
	}

	function renderBackupCodes(codes, cfg) {
		var $box = $('#smsentry-backup-codes-display');
		var textBlob = codes.join('\n');

		var $container = $('<div class="smsentry-backup-codes-box"></div>');
		$container.append($('<p class="smsentry-backup-codes-notice"></p>').text(cfg.i18n.saveCodesNotice || 'Save these codes somewhere safe — they will not be shown again.'));

		var $list = $('<ul class="smsentry-backup-codes-list"></ul>');
		codes.forEach(function (code) {
			$list.append($('<li></li>').append($('<code></code>').text(code)));
		});
		$container.append($list);

		var $actions = $('<div class="smsentry-backup-codes-actions"></div>');
		var $copyBtn = $('<button type="button" class="button"></button>').text(cfg.i18n.copyCodes || 'Copy Codes');
		var $downloadLink = $('<a class="button" download="smsentry-backup-codes.txt"></a>')
			.attr('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(textBlob))
			.text(cfg.i18n.downloadCodes || 'Download');

		$copyBtn.on('click', function () {
			navigator.clipboard.writeText(textBlob).then(function () {
				showResult($('#smsentry-toggle-result'), cfg.i18n.codesCopied || 'Codes copied to clipboard.', false);
			});
		});

		$actions.append($copyBtn).append($downloadLink);
		$container.append($actions);

		$box.empty().append($container).show();
	}

	/* ── Admin settings: validate + test SMS ────────────── */

	function initAdminSettings() {
		if (typeof smsentryAdmin === 'undefined') return;

		var cfg = smsentryAdmin;

		// Show/hide provider credential sections when dropdown changes
		$('#smsentry_provider').on('change', function () {
			var selected = $(this).val();
			$('.smsentry-provider-section').hide();
			$('.smsentry-provider-section[data-provider="' + selected + '"]').show();
		});

		// Validate credentials
		$('#smsentry-validate').on('click', function () {
			var $btn    = $(this);
			var $result = $('#smsentry-validate-result');

			$btn.prop('disabled', true).text(cfg.i18n.validating || 'Validating...');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_validate_credentials',
				nonce  : cfg.nonce
			})
			.done(function (response) {
				showResult($result, response.data.message, !response.success);
				$btn.prop('disabled', false).text(cfg.i18n.validate || 'Validate Credentials');
			})
			.fail(function () {
				showResult($result, 'Request failed.', true);
				$btn.prop('disabled', false).text(cfg.i18n.validate || 'Validate Credentials');
			});
		});

		// Send test SMS
		$('#smsentry-test-send').on('click', function () {
			var phone   = getFullPhone($('#smsentry-test-phone'));
			var $result = $('#smsentry-test-result');
			var $btn    = $(this);

			if (!phone) {
				showResult($result, 'Enter a phone number first.', true);
				return;
			}

			$btn.prop('disabled', true).text(cfg.i18n.testing || 'Sending...');

			$.post(cfg.ajaxUrl, {
				action : 'smsentry_test_sms',
				nonce  : cfg.nonce,
				phone  : phone
			})
			.done(function (response) {
				showResult($result, response.data.message, !response.success);
				$btn.prop('disabled', false).text(cfg.i18n.send || 'Send Test SMS');
			})
			.fail(function () {
				showResult($result, 'Request failed.', true);
				$btn.prop('disabled', false).text(cfg.i18n.send || 'Send Test SMS');
			});
		});
	}

	/* ── Bootstrap ───────────────────────────────────────── */

	$(function () {
		initCountryPickers();
		initResendTimer();
		initResendButton();
		initBackupCodeToggle();
		initProfileSetup();
		initAdminSettings();
	});

}(jQuery));
