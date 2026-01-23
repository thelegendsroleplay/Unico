/**
 * Unico Custom Checkout JavaScript
 * Works with custom shop system (no WooCommerce)
 */

document.addEventListener('DOMContentLoaded', function () {
  console.log('Unico Custom Checkout: Initialized');

  /* ------------------------------
     Receipt Upload Handler
  -------------------------------*/
  var uploadInput = document.querySelector('.unico-upload-input');
  var uploadPlaceholder = document.querySelector('.unico-upload-placeholder');
  var uploadError = null;
  var receiptUploaded = false;

  if (uploadInput) {
    uploadError = document.createElement('div');
    uploadError.className = 'unico-upload-error';
    uploadInput.parentNode.appendChild(uploadError);

    uploadInput.addEventListener('change', async function () {
      receiptUploaded = false;

      if (!uploadInput.files || !uploadInput.files.length) {
        uploadError.textContent = '';
        return;
      }

      var file = uploadInput.files[0];
      var ext = file.name.toLowerCase().split('.').pop();
      var allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

      if (allowed.indexOf(ext) === -1) {
        uploadError.textContent = 'Please upload a valid image file.';
        uploadInput.value = '';
        return;
      }

      uploadError.textContent = 'Uploading receipt...';

      var fd = new FormData();
      fd.append('action', 'unico_upload_receipt');
      fd.append('voucher_payment_receipt', file);

      if (typeof unicoCheckout !== 'undefined' && unicoCheckout.nonce) {
        fd.append('nonce', unicoCheckout.nonce);
      }

      try {
        var ajaxUrl = (typeof unicoCheckout !== 'undefined' && unicoCheckout.ajax_url)
          ? unicoCheckout.ajax_url
          : '/wp-admin/admin-ajax.php';

        console.log('Unico Checkout: Uploading receipt to', ajaxUrl);

        const res = await fetch(ajaxUrl, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });
        const data = await res.json();

        console.log('Unico Checkout: Upload response:', data);

        if (!data.success) {
          console.error('Unico Checkout: Upload failed:', data.data);
          uploadError.textContent = data.data || 'Upload failed.';
          uploadInput.value = '';
          return;
        }

        receiptUploaded = true;
        uploadError.textContent = '✓ Receipt uploaded successfully';
        uploadError.style.color = '#4caf50';
        if (uploadPlaceholder) uploadPlaceholder.textContent = file.name;
        console.log('Unico Checkout: Receipt uploaded successfully');

      } catch (e) {
        console.error('Unico Checkout: Upload exception:', e);
        uploadError.textContent = 'Upload error. Please try again.';
        uploadInput.value = '';
      }
    });
  }

  /* ------------------------------
     Payment Method Selection
  -------------------------------*/
  var methodButtons = document.querySelectorAll('.unico-method-button');
  var modeInput = document.querySelector('input[name="voucher_payment_mode"]');
  var selectedMethod = null;

  methodButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      methodButtons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      selectedMethod = btn.getAttribute('data-method');

      if (modeInput) modeInput.value = selectedMethod;

      // Show/hide bank transfer details
      var bankDetails = document.getElementById('bank-transfer-details');
      if (bankDetails) {
        bankDetails.style.display = (selectedMethod === 'bank_transfer') ? 'block' : 'none';
      }
    });
  });

  /* ------------------------------
     Confirm Order Button
  -------------------------------*/
  var confirmBtn = document.getElementById('unico-confirm-order-btn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', async function (e) {
      e.preventDefault();

      console.log('Unico Checkout: Confirm order clicked');

      // Validate
      if (!selectedMethod) {
        alert('Please select a payment method');
        return;
      }

      if (selectedMethod === 'bank_transfer' && !receiptUploaded) {
        alert('Please upload your payment receipt before placing the order');
        return;
      }

      var transactionId = document.querySelector('input[name="voucher_payment_reference"]');
      if (selectedMethod === 'bank_transfer' && (!transactionId || !transactionId.value.trim())) {
        alert('Please enter the transaction ID');
        return;
      }

      var termsCheckbox = document.querySelector('input[name="voucher_terms_confirmed"]');
      if (!termsCheckbox || !termsCheckbox.checked) {
        alert('Please confirm the terms and conditions');
        return;
      }

      // Disable button
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Processing...';

      // Prepare form data
      var formData = new FormData();
      formData.append('action', 'unico_place_order');
      formData.append('nonce', unicoCheckout.nonce);
      formData.append('buyer_name', document.querySelector('input[name="voucher_buyer_full_name"]').value);
      formData.append('buyer_email', document.querySelector('input[name="voucher_buyer_email"]').value);
      formData.append('payment_mode', selectedMethod);

      if (transactionId) {
        formData.append('payment_reference', transactionId.value);
      }

      var bankSelect = document.querySelector('select[name="selected_bank_id"]');
      if (bankSelect) {
        formData.append('bank_id', bankSelect.value);
      }

      formData.append('terms_confirmed', termsCheckbox.checked ? '1' : '0');

      try {
        const res = await fetch(unicoCheckout.ajax_url, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });

        const data = await res.json();

        console.log('Unico Checkout: Order response:', data);

        if (!data.success) {
          alert('Error: ' + (data.data || 'Failed to place order'));
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'CONFIRM ORDER';
          return;
        }

        // Success! Redirect to confirmation page
        console.log('Unico Checkout: Order placed successfully');
        window.location.href = data.data.redirect;

      } catch (e) {
        console.error('Unico Checkout: Order exception:', e);
        alert('An error occurred. Please try again.');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'CONFIRM ORDER';
      }
    });
  }
});

/* ------------------------------
   Copy to clipboard
-------------------------------*/
function copyToClipboard(elementId, button) {
  var element = document.getElementById(elementId);
  if (!element) return;

  var textarea = document.createElement('textarea');
  textarea.value = element.textContent || element.innerText;
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();

  try {
    document.execCommand('copy');
    if (button) {
      var originalText = button.textContent;
      button.textContent = 'Copied!';
      setTimeout(function() {
        button.textContent = originalText;
      }, 2000);
    }
  } catch (e) {
    console.error('Copy failed:', e);
  }

  document.body.removeChild(textarea);
}
