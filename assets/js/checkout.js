document.addEventListener('DOMContentLoaded', function () {
  var card = document.querySelector('.unico-checkout-card');
  if (!card) {
    return;
  }
  var qtyInput = document.querySelector('.unico-qty-input');
  var qtyDisplay = card.querySelector('.unico-checkout-qty');
  var qty = 1;
  function parseQty() {
    var value = 1;
    if (qtyInput) {
      var parsed = parseInt(qtyInput.value, 10);
      if (parsed && parsed > 0) {
        value = parsed;
      }
    } else {
      var attr = card.getAttribute('data-voucher-qty');
      var parsedAttr = parseInt(attr, 10);
      if (parsedAttr && parsedAttr > 0) {
        value = parsedAttr;
      } else if (qtyDisplay) {
        var parsedText = parseInt(qtyDisplay.textContent.replace(/[^0-9]/g, ''), 10);
        if (parsedText && parsedText > 0) {
          value = parsedText;
        }
      }
    }
    return value;
  }
  function syncQty() {
    qty = parseQty();
    if (qtyInput) {
      qtyInput.value = qty;
    }
    if (qtyDisplay) {
      qtyDisplay.textContent = 'x' + qty;
    }
  }
  syncQty();
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
    if (errorEl) {
      errorEl.textContent = message || '';
    }
  }
  function getLabel(btn) {
    var span = btn.querySelector('span');
    if (span && span.textContent) {
      return span.textContent.trim();
    }
    return '';
  }
  function validateMethod(btn) {
    var limitAttr = btn.getAttribute('data-limit');
    var limit = parseInt(limitAttr, 10);
    if (limit && qty && qty > limit) {
      var label = getLabel(btn);
      setError('You can purchase up to ' + limit + ' units with ' + label + '. Current quantity is ' + qty + '.');
      return false;
    }
    return true;
  }
  function validateActiveMethod() {
    var active = document.querySelector('.unico-method-button.is-active');
    if (!active) {
      setError('');
      return;
    }
    if (!validateMethod(active)) {
      return;
    }
    setError('');
  }
  if (qtyInput) {
    qtyInput.addEventListener('change', function () {
      syncQty();
      validateActiveMethod();
    });
    qtyInput.addEventListener('input', function () {
      syncQty();
    });
  }
  var qtyButtons = document.querySelectorAll('.unico-qty-btn');
  qtyButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!qtyInput) {
        return;
      }
      var dir = btn.getAttribute('data-direction');
      var current = parseQty();
      if (dir === 'minus') {
        current = current - 1;
      } else if (dir === 'plus') {
        current = current + 1;
      }
      if (current < 1) {
        current = 1;
      }
      qtyInput.value = current;
      syncQty();
      validateActiveMethod();
    });
  });
  var uploadInput = document.querySelector('.unico-upload-input');
  var uploadPlaceholder = document.querySelector('.unico-upload-placeholder');
  var uploadError = null;
  if (uploadInput) {
    uploadError = document.createElement('div');
    uploadError.className = 'unico-upload-error';
    uploadInput.parentNode.appendChild(uploadError);
    uploadInput.addEventListener('change', function () {
      if (!uploadInput.files || !uploadInput.files.length) {
        if (uploadPlaceholder) {
          uploadPlaceholder.textContent = 'Click to upload receipt';
        }
        if (uploadError) {
          uploadError.textContent = '';
        }
        return;
      }
      var file = uploadInput.files[0];
      var name = file.name || '';
      var lower = name.toLowerCase();
      var parts = lower.split('.');
      var ext = parts.length > 1 ? parts.pop() : '';
      var allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      if (!ext || allowed.indexOf(ext) === -1) {
        if (uploadError) {
          uploadError.textContent = 'Please upload a valid image file (JPG, PNG, GIF, WEBP).';
        }
        uploadInput.value = '';
        if (uploadPlaceholder) {
          uploadPlaceholder.textContent = 'Click to upload receipt';
        }
        return;
      }
      if (uploadError) {
        uploadError.textContent = '';
      }
      if (uploadPlaceholder) {
        uploadPlaceholder.textContent = name;
      }
    });
  }
  methodButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      syncQty();
      if (!validateMethod(btn)) {
        return;
      }
      setError('');
      methodButtons.forEach(function (b) {
        b.classList.remove('is-active');
      });
      btn.classList.add('is-active');
      if (modeInput) {
        modeInput.value = btn.getAttribute('data-method');
      }

      // Show/hide bank transfer details based on payment method
      var bankDetails = document.getElementById('bank-transfer-details');
      if (bankDetails) {
        var selectedMethod = btn.getAttribute('data-method');
        if (selectedMethod === 'bank_transfer') {
          bankDetails.style.display = 'block';
        } else {
          bankDetails.style.display = 'none';
        }
      }
    });
  });
});

/**
 * Copy to clipboard functionality
 */
function copyToClipboard(elementId, button) {
  var element = document.getElementById(elementId);
  if (!element) {
    return;
  }

  var text = element.textContent || element.innerText;

  // Create temporary textarea
  var textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();

  try {
    // Copy to clipboard
    var successful = document.execCommand('copy');
    if (successful) {
      // Change button state
      var originalText = button.innerHTML;
      button.classList.add('copied');
      button.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!';

      // Reset after 2 seconds
      setTimeout(function() {
        button.classList.remove('copied');
        button.innerHTML = originalText;
      }, 2000);
    }
  } catch (err) {
    console.error('Failed to copy:', err);
  }

  // Clean up
  document.body.removeChild(textarea);
}
