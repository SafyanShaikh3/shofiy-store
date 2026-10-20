/* Razorpay Checkout launcher for pay.php */
(function () {
  'use strict';
  var opts = window.RZP_OPTS;
  var btn = document.getElementById('payBtn');
  var err = document.getElementById('payError');
  var form = document.getElementById('verifyForm');
  if (!opts || !btn || !form) return;

  function showError(msg) { err.textContent = msg; err.hidden = false; }

  opts.handler = function (resp) {
    btn.disabled = true;
    btn.textContent = 'Confirming payment…';
    form.razorpay_payment_id.value = resp.razorpay_payment_id;
    form.razorpay_order_id.value = resp.razorpay_order_id;
    form.razorpay_signature.value = resp.razorpay_signature;
    form.submit();
  };
  opts.modal = {
    ondismiss: function () {
      btn.disabled = false;
      showError('Payment was not completed. You can try again — your items are still reserved.');
    }
  };

  function open() {
    if (typeof window.Razorpay !== 'function') { showError('Could not load the payment window. Check your connection and refresh the page.'); return; }
    err.hidden = true;
    var rzp = new window.Razorpay(opts);
    rzp.on('payment.failed', function (r) {
      showError((r && r.error && r.error.description) ? r.error.description + ' Please try again or use another method.' : 'Payment failed. Please try again.');
    });
    rzp.open();
  }

  btn.addEventListener('click', open);
  // Open automatically on arrival from checkout
  window.addEventListener('load', function () { setTimeout(open, 300); });
})();
