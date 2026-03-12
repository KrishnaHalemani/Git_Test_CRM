# Integrating your website lead form with CRM Pro

This guide explains how to send leads from an external website form into your CRM instance.

Summary
- Add a secure API key to your CRM settings (key: `lead_api_key`) or set environment variable `CRM_LEAD_API_KEY`.
- POST lead data to `/api/receive_lead.php` as JSON or form-encoded data.
- Endpoint will return JSON { success: true, lead_id: 123 } on success.

Security
- The endpoint expects an API key in the `X-CRM-API-KEY` header or as `api_key` form field.
- Configure the API key via the `settings` table (key `lead_api_key`) using `setSetting()` or via Admin UI (Settings).
- Optionally set `lead_api_allowed_origins` (comma-separated origins) to restrict CORS.

Fields accepted (recommended)
- name (string)
- email (string)
- phone (string)
- company (string)
- service (string)
- message / notes (string)
- priority (low|medium|high) optional
- estimated_value (numeric) optional
- source optional (defaults to "website")

Response codes
- 201 Created -> success
- 400 Bad Request -> missing required fields
- 401 Unauthorized -> invalid API key
- 500 Server Error -> insertion failed

Example HTML form (direct POST - not recommended for exposing API key)

<form method="post" action="https://yourdomain/CRM2/api/receive_lead.php">
  <input type="hidden" name="api_key" value="YOUR_API_KEY_HERE">
  <label>Name: <input name="name"></label>
  <label>Email: <input name="email"></label>
  <label>Phone: <input name="phone"></label>
  <label>Company: <input name="company"></label>
  <label>Service: <input name="service"></label>
  <label>Message: <textarea name="message"></textarea></label>
  <button type="submit">Send</button>
</form>

Better: Post from your server (recommended)
- Keep the API key secret on your server.
- Example Node/PHP server script that receives form on your website and forwards to CRM with API key.

Client-side fetch (from browser) - use only if you can keep key private or use a short-lived token

```js
fetch('https://yourdomain/CRM2/api/receive_lead.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CRM-API-KEY': 'YOUR_API_KEY_HERE'
  },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com',
    phone: '+1234567890',
    company: 'ACME',
    service: 'Pricing',
    message: 'Please contact me',
    source: 'website-contact-form'
  })
})
.then(r => r.json())
.then(json => console.log(json))
.catch(e => console.error(e));
```

Server-side PHP forwarding example (recommended)

```php
// yoursite/form-handler.php
$form = $_POST; // from website form
$apiKey = 'YOUR_API_KEY';
$ch = curl_init('https://yourdomain/CRM2/api/receive_lead.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-CRM-API-KEY: $apiKey", 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($form));
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
// handle $response (json)
```

How to set the API key in CRM (quick)
- Use `setSetting('lead_api_key', 'your-secret-key')` from a small PHP script, or add via Settings page if available.

Example quick setup script (run once)

```php
<?php
include 'db.php';
setSetting('lead_api_key', 'put-a-long-random-string-here');
setSetting('lead_api_allowed_origins', 'https://yourwebsite.com');
echo "API key set\n";
```

Notes & best practices
- Keep the API key secret (do not embed in public client-side JS if possible).
- Use server-to-server forwarding when feasible.
- Consider adding CAPTCHA on your site and validating before forwarding.
- Monitor logs for abusive traffic.
- Optionally implement replay protection / HMAC signature for higher security.

If you want, I can:
- Add an Admin UI page to generate/manage API keys.
- Add HMAC-signed requests verification.
- Implement server-side forwarding example on your website (I can provide a sample script you can drop in).
