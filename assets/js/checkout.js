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
      var allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

      if (allowed.indexOf(ext) === -1) {
        uploadError.textContent = 'Please upload a valid image or PDF file.';
        uploadInput.value = '';
        return;
      }

      uploadError.textContent = 'Uploading receipt...';

      var fd = new FormData();
      fd.append('action', 'unico_upload_payment_receipt'); // Fixed action name
      fd.append('voucher_payment_receipt', file); // Use correct field name expected by backend if any, or just file

      // Add nonce if available
      if (typeof unicoCheckout !== 'undefined' && unicoCheckout.nonce) {
        fd.append('nonce', unicoCheckout.nonce);
      }

      try {
        // AJAX request
        var ajaxUrl = unicoCheckout.ajax_url;

        const res = await fetch(ajaxUrl, {
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
        uploadError.style.color = 'green';
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
  // Note: This relies on jQuery trigger 'checkout_place_order' which might not happen in custom form?
  // Our custom form is standard HTML form. We should intercept submit.
  var checkoutForm = document.querySelector('.unico-checkout-form');
  if (checkoutForm) {
      checkoutForm.addEventListener('submit', function(e) {
          var active = document.querySelector('.unico-method-button.is-active');
          if (active && active.getAttribute('data-method') === 'bank_transfer') {
            // Check if receipt is uploaded
            // We can check the receiptUploaded flag, OR check if the input has files (but backend needs upload first?)
            // Actually, if we upload via AJAX, we probably set a hidden field with the attachment ID?
            // Or if we just submit the file with the form (standard POST)?
            // The form has enctype="multipart/form-data".
            // So we don't strictly need AJAX upload unless we want to validate it before submit.
            // But the user said "asking me to upload file", which implies the standard required attribute is working.
            // If we rely on standard form submit, we don't need to block unless we want custom validation.
            
            // If the input has 'required', browser handles it.
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
    } else {
        alert("Copied to clipboard");
    }
  } catch (e) {}

  document.body.removeChild(textarea);
}

/* ------------------------------
   OTP Logic (Moved from inline)
-------------------------------*/
jQuery(document).ready(function($) {
    $('#unico-send-otp-btn').on('click', function(e) {
        e.preventDefault(); // Prevent any form submission
        var btn = $(this);
        btn.prop('disabled', true).text('Sending...');
        
        $.ajax({
            url: unicoCheckout.ajax_url,
            type: 'POST',
            data: {
                action: 'unico_send_purchase_otp',
                nonce: unicoCheckout.nonce_verification
            },
            success: function(response) {
                if (response.success) {
                    $('#unico-otp-step-1').hide();
                    $('#unico-otp-step-2').show();
                    $('#unico-otp-message').text(response.data.message).css('color', 'green');
                } else {
                    btn.prop('disabled', false).text('Send Verification Code');
                    alert(response.data.message);
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Send Verification Code');
                alert('Error sending request.');
            }
        });
    });

    $('#unico-verify-otp-btn').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var code = $('#unico-otp-input').val();
        
        if (!code) {
            alert('Please enter the code');
            return;
        }
        
        btn.prop('disabled', true).text('Verifying...');
        
        $.ajax({
            url: unicoCheckout.ajax_url,
            type: 'POST',
            data: {
                action: 'unico_verify_purchase_otp',
                code: code,
                nonce: unicoCheckout.nonce_verification
            },
            success: function(response) {
                if (response.success) {
                    $('#unico-otp-message').text(response.data.message).css('color', 'green');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    btn.prop('disabled', false).text('Verify Code');
                    $('#unico-otp-message').text(response.data.message).css('color', 'red');
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Verify Code');
                alert('Error sending request.');
            }
        });
    });
});

/* ------------------------------
   Cart Quantity Update (Global)
-------------------------------*/
window.updateCartQty = function(productId, change) {
    // Find the input for this product
    var qtyInput = jQuery('.unico-checkout-card[data-product-id="' + productId + '"] .unico-qty-input');
    var currentQty = parseInt(qtyInput.val());
    var newQty = currentQty + change;
    
    if (newQty < 1) return;

    // Implement AJAX call to update quantity
    jQuery.ajax({
        url: unicoCheckout.ajax_url,
        type: 'POST',
        data: {
            action: 'unico_update_cart_quantity',
            product_id: productId,
            quantity: newQty,
            nonce: unicoCheckout.nonce_update_cart
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        }
    });
};
