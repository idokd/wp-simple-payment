<?php
define("RECAPTCHA_V3_SECRET_KEY", 'YOUR_SECRET_HERE');
 
if (isset($_POST['email']) && $_POST['email']) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_STRING);
} else {
    // set error message and redirect back to form...
    header('location: subscribe_newsletter_form.php');
    exit;
}
 
$token = $_POST['token'];
$action = $_POST['action'];
 
// call curl to POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,"https://www.google.com/recaptcha/api/siteverify");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('secret' => RECAPTCHA_V3_SECRET_KEY, 'response' => $token)));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$arrResponse = json_decode($response, true);
 
// verify the response
if($arrResponse["success"] == '1' && $arrResponse["action"] == $action && $arrResponse["score"] >= 0.5) {
    // valid submission
    // go ahead and do necessary stuff
} else {
    // spam submission
    // show error message
}
?>

<html>
  <head>
    <title>Subscribe to Newsletter</title>
    <script
      src="https://code.jquery.com/jquery-3.4.1.min.js"
      integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo="
      crossorigin="anonymous"></script>
 
    <script src="https://www.google.com/recaptcha/api.js?render=6LdLk7EUAAAAAEWHuB2tabMmlxQ2-RRTLPHEGe9Y"></script>
  </head>
  <body>
    <div>
      <b>Subscribe Newsletter</b>
    </div>
 
    <form id="newsletterForm" action="subscribe_newsletter_submit.php" method="post">
      <div>
          <div>
              <input type="email" id="email" name="email">
          </div>
          <div>
              <input type="submit" value="submit">
          </div>
      </div>
    </form>
 
    <script>
    $('#newsletterForm').submit(function(event) {
        event.preventDefault();
        var email = $('#email').val();
 
        grecaptcha.ready(function() {
            grecaptcha.execute('6LdLk7EUAAAAAEWHuB2tabMmlxQ2-RRTLPHEGe9Y', {action: 'purchase'}).then(function(token) {
                $('#newsletterForm').prepend('<input type="hidden" name="token" value="' + token + '">');
                $('#newsletterForm').unbind('submit').submit();
            });;
        });
  });
  </script>
  </body>
</html>