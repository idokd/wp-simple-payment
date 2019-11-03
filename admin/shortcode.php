<h2>Shortcode  Parameters</h2>

[simple_payment] shortcode support the following configuration:<br /> <br />

<table border="1">
  <tr>
      <th>Parameter</th>
      <th>Default</th>
      <th>Description</th>
  </tr>
  <tr>
      <td>id</td>
      <td>null</td>
      <td>Post or Page ID to fetch data from (title / amount)</td>
  </tr>
  <tr>
      <td>amount</td>
      <td>null</td>
      <td>Amount to fix for charge (if null tries to fetch from current post, or post id)</td>
  </tr>
  <tr>
      <td>product</td>
      <td>null</td>
      <td>Product or Service name that will appear on the charge, will also work with: title, concept</td>
  </tr>
  <tr>
      <td>currency</td>
      <td>null</td>
      <td>Currency ISO standard 3 letters</td>
  </tr>
  <tr>
      <td>type</td>
      <td>form</td>
      <td>Form/ Button/ Template- should shortcode render a button or a complete form</td>
  </tr>
  <tr>
      <td>target</td>
      <td>null</td>
      <td>Form target or button A HREF target attribute</td>
  </tr>
  <tr>
      <td>form</td>
      <td>legacy</td>
      <td>Some predefined forms inclueded in the plugin; Legacy, Bootstrap, Expermintal</td>
  </tr>
  <tr>
      <td>template</td>
      <td>null</td>
      <td>Template to use as form, relative to theme folder example: theme-payment.php</td>
  </tr>
  <tr>
      <td>amount_field</td>
      <td>amount</td>
      <td>Custom field to use for amount</td>
  </tr>
  <tr>
      <td>product_field</td>
      <td>null</td>
      <td>Custom field to use for product name, if null uses post title</td>
  </tr>
  <tr>
      <td>redirect_url</td>
      <td>null</td>
      <td>Redirect after successful transactions (depending on engine funcionality)</td>
  </tr>
  <tr>
      <td>engine</td>
      <td>PayPal</td>
      <td>Available Payment Engines</td>
  </tr>
  <tr>
      <td>method</td>
      <td>null</td>
      <td>Payment method, if supported by engine, some support PayPal, Credit, Debit etc.</td>
  </tr>
  <tr>
      <td>enable_query</td>
      <td>false</td>
      <td>Enable overwrite of query parameters for product title / amount</td>
  </tr>
</table>

<h2>Shortcode  Examples</h2>
