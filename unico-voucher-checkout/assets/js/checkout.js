(function ($) {
	function setErrors(errors) {
		var $box = $('#unico-vc-errors');
		if (!$box.length) return;
		if (!errors || !errors.length) {
			$box.removeClass('is-visible').empty();
			return;
		}
		var list = '<ul>';
		for (var i = 0; i < errors.length; i++) {
			list += '<li>' + $('<div/>').text(errors[i]).html() + '</li>';
		}
		list += '</ul>';
		$box.html(list).addClass('is-visible');
	}

	function setOtpErrors(errors) {
		var $box = $('#unico-vc-otp-errors');
		if (!$box.length) return;
		if (!errors || !errors.length) {
			$box.removeClass('is-visible').empty();
			return;
		}
		var list = '<ul>';
		for (var i = 0; i < errors.length; i++) {
			list += '<li>' + $('<div/>').text(errors[i]).html() + '</li>';
		}
		list += '</ul>';
		$box.html(list).addClass('is-visible');
	}

	function getQty() {
		var qty = parseInt($('#unico-vc-qty').val(), 10);
		if (!Number.isFinite(qty) || qty < 1) qty = 1;
		return qty;
	}

	function syncQtyBadge() {
		$('#unico-vc-qty-badge').text('X' + getQty());
	}

	function formatTotal(amount) {
		var symbol = (window.UnicoVC && UnicoVC.currencySymbol) ? UnicoVC.currencySymbol : '';
		return symbol + amount.toFixed(2);
	}

	function syncTotal() {
		var $wrap = $('#unico-vc-checkout');
		var price = parseFloat($wrap.data('product-price'));
		if (!Number.isFinite(price)) return;
		var total = price * getQty();
		$('#unico-vc-total').text(formatTotal(total));
	}

	function bindUpload() {
		var $input = $('#unico-vc-receipt');
		if (!$input.length) return;
		$('#unico-vc-upload-btn').on('click', function () {
			$input.trigger('click');
		});
		$input.on('change', function () {
			var name = 'No file selected';
			if (this.files && this.files.length) name = this.files[0].name;
			$('#unico-vc-upload-meta').text(name);
		});
	}

	function bindQty() {
		$(document).on('click', '.unico-vc-qty-btn', function () {
			var $input = $('#unico-vc-qty');
			var current = parseInt($input.val(), 10);
			if (!Number.isFinite(current) || current < 1) current = 1;
			var action = $(this).data('action');
			if (action === 'increase') current += 1;
			if (action === 'decrease') current = Math.max(1, current - 1);
			$input.val(current).trigger('change');
		});
		$(document).on('input change', '#unico-vc-qty', function () {
			syncQtyBadge();
			syncTotal();
		});
	}

	function createOrder(otpKey) {
		var $btn = $('#unico-vc-submit');
		if ($btn.prop('disabled')) return;

		setErrors([]);

		var $wrap = $('#unico-vc-checkout');
		var productId = parseInt($wrap.data('product-id'), 10);
		var qty = getQty();
		var buyerName = $('#unico-vc-buyer-name').val() || '';
		var buyerEmail = $('#unico-vc-buyer-email').val() || '';
		var txnId = $('#unico-vc-txn').val() || '';
		var confirm = $('#unico-vc-confirm').is(':checked') ? 1 : 0;
		var bankKey = $wrap.data('bank-key') || '';

		var fileInput = document.getElementById('unico-vc-receipt');
		var file = fileInput && fileInput.files && fileInput.files.length ? fileInput.files[0] : null;

		var fd = new FormData();
		fd.append('action', 'unico_vc_create_order');
		fd.append('nonce', UnicoVC.nonce);
		fd.append('product_id', String(productId));
		fd.append('qty', String(qty));
		fd.append('buyer_name', buyerName);
		fd.append('buyer_email', buyerEmail);
		fd.append('txn_id', txnId);
		fd.append('confirm', String(confirm));
		fd.append('bank_key', String(bankKey));
		fd.append('otp_key', String(otpKey || ''));
		if (file) fd.append('receipt', file);

		$btn.prop('disabled', true);

		$.ajax({
			url: UnicoVC.ajaxUrl,
			method: 'POST',
			data: fd,
			processData: false,
			contentType: false,
		})
			.done(function (res) {
				if (res && res.success && res.data && res.data.redirect) {
					window.location.href = res.data.redirect;
					return;
				}
				var msg = (res && res.data && res.data.message) || 'Order failed.';
				var errors = (res && res.data && res.data.errors) || [msg];
				setErrors(errors);
			})
			.fail(function (xhr) {
				var errors = ['Order failed. Please try again.'];
				try {
					var json = xhr.responseJSON;
					if (json && json.data && json.data.errors) errors = json.data.errors;
					else if (json && json.data && json.data.message) errors = [json.data.message];
				} catch (e) {}
				setErrors(errors);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	}

	function openOtpModal(email) {
		$('#unico-vc-otp-email').text(email || '');
		$('#unico-vc-otp-code').val('');
		setOtpErrors([]);
		$('#unico-vc-otp-modal').addClass('is-active').attr('aria-hidden', 'false');
		setTimeout(function () {
			$('#unico-vc-otp-code').trigger('focus');
		}, 100);
	}

	function closeOtpModal() {
		$('#unico-vc-otp-modal').removeClass('is-active').attr('aria-hidden', 'true');
	}

	function requestOtp(otpState) {
		var buyerName = $('#unico-vc-buyer-name').val() || '';
		var buyerEmail = $('#unico-vc-buyer-email').val() || '';

		setOtpErrors([]);
		setErrors([]);

		var payload = {
			action: 'unico_vc_request_otp',
			nonce: UnicoVC.otpNonce,
			buyer_name: buyerName,
			buyer_email: buyerEmail,
			otp_key: otpState.key || ''
		};

		return $.post(UnicoVC.ajaxUrl, payload)
			.then(function (res) {
				if (res && res.success && res.data && res.data.otp_key) {
					otpState.key = res.data.otp_key;
					openOtpModal(buyerEmail);
					return true;
				}
				var msg = (res && res.data && res.data.message) || 'Unable to send verification code.';
				setErrors([msg]);
				return false;
			})
			.fail(function (xhr) {
				var errors = ['Unable to send verification code.'];
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errors = [xhr.responseJSON.data.message];
				}
				setErrors(errors);
				return false;
			});
	}

	function verifyOtp(otpState) {
		var buyerEmail = $('#unico-vc-buyer-email').val() || '';
		var otpCode = $('#unico-vc-otp-code').val() || '';
		setOtpErrors([]);

		return $.post(UnicoVC.ajaxUrl, {
			action: 'unico_vc_verify_otp',
			nonce: UnicoVC.otpNonce,
			buyer_email: buyerEmail,
			otp_key: otpState.key || '',
			otp_code: otpCode
		})
			.then(function (res) {
				if (res && res.success) {
					otpState.verified = true;
					closeOtpModal();
					createOrder(otpState.key);
					return true;
				}
				var msg = (res && res.data && res.data.message) || 'Verification failed.';
				setOtpErrors([msg]);
				return false;
			})
			.fail(function (xhr) {
				var errors = ['Verification failed.'];
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errors = [xhr.responseJSON.data.message];
				}
				setOtpErrors(errors);
				return false;
			});
	}

	function submitWithOtp(otpState) {
		if (otpState.verified) {
			createOrder(otpState.key);
			return;
		}
		requestOtp(otpState);
	}

	$(function () {
		if (!$('#unico-vc-checkout').length) return;
		var otpState = { verified: false, key: null };
		bindQty();
		bindUpload();
		syncQtyBadge();
		syncTotal();
		$('#unico-vc-submit').on('click', function () {
			submitWithOtp(otpState);
		});
		$('#unico-vc-otp-verify').on('click', function () {
			verifyOtp(otpState);
		});
		$('#unico-vc-otp-resend').on('click', function () {
			requestOtp(otpState);
		});
		$('#unico-vc-otp-close').on('click', function () {
			closeOtpModal();
		});
		$('#unico-vc-otp-modal').on('click', function (event) {
			if (event.target === this) {
				closeOtpModal();
			}
		});
	});
})(jQuery);
