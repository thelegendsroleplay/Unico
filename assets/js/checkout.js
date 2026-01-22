document.addEventListener('DOMContentLoaded', function () {
  var card = document.querySelector('.unico-checkout-card');
  if (!card) {
    return;
  }

  /* ------------------------------
     Quantity handling (UNCHANGED)
  -------------------------------*/
  var qtyInput = document.querySelector('.unico-qty-input');
  var qtyDisplay = card.querySelector('.unico-checkout-qty');
  var qty = 1;

  function parseQty() {
    var value = 1;
    if (qtyInput) {
      var parsed = parseInt(qtyInput.value, 10);
      if (parsed && parsed > 0) value = parsed;
    } else {
      var attr = card.getAttribute('data-voucher-qty');
      var parsedAttr = parseInt(attr, 10);
      if (parsedAttr && parsedAttr > 0) {
        value = parsedAttr;
      } else if (qtyDisplay) {
        var parsedText = parseInt(qtyDisplay.textContent.replace(/[^0-9]/g, ''), 10);
        if (parsedText && parsedText > 0) value = parsedText;
      }
    }
    return value;
  }

  function syncQty() {
    qty = parseQty();
    if (qtyInput) qtyInput.value = qty;
    if (qtyDisplay) qtyDisplay.textContent = 'x' + qty;
  }

  syncQty();

  /* ------------------------------
     Payment method logic
  -------------------------------*/
  var methodButtons = document.querySelectorAll('.unico-method-button');
  var modeInput = document.querySelector('input[name="voucher_payment_mode"]');
  var methodsRow = document.querySelector('.unico-checkout-methods');
  var errorEl = null;

  if (methodsRow) {
    errorEl = document.createElement('div');
    errorEl.className = 'unico-method-error';
    methodsRow.appendChild(errorEl);
  }

  function setError(message) {
    if (errorEl) errorEl.textContent = message || '';
  }

  function getLabel(btn) {
    var span = btn.querySelector('span');
    return span && span.textContent ? span.textContent.trim() : '';
  }

  function validateMethod(btn) {
    var limit = parseInt(btn.getAttribute('data-limit'), 10);
    if (limit && qty > limit) {
      setError(
        'You can purchase up to ' + limit + ' units with ' + getLabel(btn) +
        '. Current quantity is ' + qty + '.'
      );
      return false;
    }
    return true;
  }

  if (qtyInput) {
    qtyInput.addEventListener('change', function () {
      syncQty();
    });
    qtyInput.addEventListener('input', syncQty);
  }

  document.querySelectorAll('.unico-qty-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!qtyInput) return;
      var current = parseQty();
      current += btn.getAttribute('data-direction') === 'minus' ? -1 : 1;
      if (current < 1) current = 1;
      qtyInput.value = current;
      syncQty();
    });
  });

  /* ------------------------------
     Receipt upload (NEW FIX)
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

      try {
        const res = await fetch(wc_checkout_params.ajax_url, {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        if (!data.success) {
          uploadError.textContent = data.data || 'Upload failed.';
          uploadInput.value = '';
          return;
        }

        receiptUploaded = true;
        uploadError.textContent = 'Receipt uploaded successfully ✔';
        if (uploadPlaceholder) uploadPlaceholder.textContent = file.name;

      } catch (e) {
        uploadError.textContent = 'Upload error. Please try again.';
        uploadInput.value = '';
      }
    });
  }

  /* ------------------------------
     Method selection
  -------------------------------*/
  methodButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      syncQty();
      if (!validateMethod(btn)) return;

      setError('');
      methodButtons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      if (modeInput) modeInput.value = btn.getAttribute('data-method');

      var bankDetails = document.getElementById('bank-transfer-details');
      if (bankDetails) {
        bankDetails.style.display =
          btn.getAttribute('data-method') === 'bank_transfer'
            ? 'block'
            : 'none';
      }
    });
  });

  /* ------------------------------
     BLOCK CHECKOUT if missing receipt
  -------------------------------*/
  jQuery(document.body).on('checkout_place_order', function () {
    var active = document.querySelector('.unico-method-button.is-active');
    if (active && active.getAttribute('data-method') === 'bank_transfer') {
      if (!receiptUploaded) {
        alert('Please upload your bank transfer receipt before placing the order.');
        return false;
      }
    }
    return true;
  });
});

/* ------------------------------
   Copy to clipboard (UNCHANGED)
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
  } catch (e) {}

  document.body.removeChild(textarea);
}
