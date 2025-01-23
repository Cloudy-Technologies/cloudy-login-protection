jQuery(document).ready(function ($) {
  let warningShown = false;
  let activityTimeout;
  let warningTimeout;
  let lastActivityTime = new Date();

  function trackActivity() {
    lastActivityTime = new Date();

    clearTimeout(activityTimeout);
    clearTimeout(warningTimeout);

    warningShown = false;

    $('#session-warning-modal').hide();

    warningTimeout = setTimeout(showWarning, (sessionManager.timeout - sessionManager.warning_time) * 1000);

    activityTimeout = setTimeout(function () {
      window.location.href = window.location.href; // Reload to trigger server-side check
    }, sessionManager.timeout * 1000);

    $.post(sessionManager.ajaxurl, {
      action: 'update_user_activity',
      nonce: sessionManager.nonce
    });
  }

  function showWarning() {
    if (!warningShown) {
      warningShown = true;

      if (!$('#session-warning-modal').length) {
        $('body').append(`
                    <div id="session-warning-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                        background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; z-index: 9999; box-shadow: 0 0 10px rgba(0,0,0,0.5);">
                        <h3>Session Timeout Warning</h3>
                        <p>Your session will expire in less than 1 minute due to inactivity.</p>
                        <button id="extend-session" style="margin-right: 10px;">Extend Session</button>
                        <button id="logout-now">Logout Now</button>
                    </div>
                `);
      }

      $('#session-warning-modal').show();
    }
  }

  if (sessionManager.timeout > 0) {
    $(document).on('mousedown keydown scroll', function () {
      if (new Date() - lastActivityTime > 1000) { // 1 second minimum between updates
        trackActivity();
      }
    });

    $(document).on('click', '#extend-session', function () {
      $('#session-warning-modal').hide();
      trackActivity();
    });

    $(document).on('click', '#logout-now', function () {
      window.location.href = '/wp-login.php?action=logout';
    });

    trackActivity();
  }
});
