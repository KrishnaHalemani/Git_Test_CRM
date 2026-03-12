# Meta Ads Lead Integration Guide

Integrate your CRM with Meta (Facebook) Ads to automatically capture leads from Meta Lead Ads forms and retrieve all leads assigned to your user.

---

## Overview

Two-way integration:
1. **Inbound**: Meta Lead Ads → CRM (automatic webhook)
2. **Outbound**: Retrieve all leads for your user ID via API

---

## Prerequisites

- Meta Business Account
- Meta Lead Ads form set up
- CRM running and accessible via HTTPS (required by Meta)
- API key from CRM (generated earlier, stored in settings)

---

## Part 1: Set Up Meta Webhook (Receive Leads)

### Step 1: Generate Meta Verification Token

In your CRM, create a one-off script to store the Meta verification token:

**Create `/set_meta_credentials.php`:**

```php
<?php
require_once 'db.php';

// Generate a random verification token (keep it secret)
$verify_token = bin2hex(random_bytes(32)); // 64-char hex string

// Optional: Meta App Secret (for signature validation)
$webhook_secret = bin2hex(random_bytes(32));

// Store in CRM settings
setSetting('meta_verify_token', $verify_token);
setSetting('meta_webhook_secret', $webhook_secret);

echo "Meta Verification Token: " . $verify_token . "\n";
echo "Meta Webhook Secret: " . $webhook_secret . "\n";
echo "Save these securely and delete this script after setup.\n";
?>
```

Run it once:
```bash
php /path/to/set_meta_credentials.php
```

Copy the printed tokens and **delete the script**.

### Step 2: Configure Webhook in Meta Business Suite

1. Go to [Meta Business Suite](https://business.facebook.com)
2. Navigate to **Apps & Websites** → **Apps** → Select your app
3. Go to **Settings** → **Basic** (copy your App ID and App Secret)
4. Go to **Messenger** or **Lead Ads** → **Webhooks**
5. Set up webhook:
   - **Callback URL**: `https://your-crm-domain/CRM2/api/meta_webhook.php`
   - **Verify Token**: paste the token from step 1
   - **Subscribe to**: `leadgen` (for lead ads)

6. Click **Verify and Save**
   - Meta will send a GET request with challenge to your webhook
   - Your webhook will return the challenge to confirm it works

### Step 3: Test Webhook (Postman)

```
GET https://your-crm-domain/CRM2/api/meta_webhook.php?verify_token=YOUR_TOKEN_HERE&challenge=test123
```

Expected response: `test123` (the challenge echoed back)

---

## Part 2: Retrieve Leads for Your User

Use this endpoint to fetch all leads assigned to or created by your user ID.

### Endpoint

```
GET /api/get_user_leads.php?user_id=1&status=hot&limit=100&offset=0
```

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `user_id` | int | YES | - | CRM user ID to retrieve leads for |
| `status` | string | NO | - | Filter by status: `new`, `hot`, `warm`, `cold`, `converted` |
| `limit` | int | NO | 100 | Max records to return |
| `offset` | int | NO | 0 | Pagination offset |
| `format` | string | NO | json | Output format: `json` or `csv` |

### Headers

```
X-CRM-API-KEY: your_api_key
```

### Example Requests

#### 1. Retrieve all leads for user ID 1 (JSON)
```bash
curl -X GET "https://your-crm-domain/CRM2/api/get_user_leads.php?user_id=1" \
  -H "X-CRM-API-KEY: YOUR_API_KEY"
```

#### 2. Retrieve hot leads only
```bash
curl -X GET "https://your-crm-domain/CRM2/api/get_user_leads.php?user_id=1&status=hot" \
  -H "X-CRM-API-KEY: YOUR_API_KEY"
```

#### 3. Retrieve as CSV (download file)
```bash
curl -X GET "https://your-crm-domain/CRM2/api/get_user_leads.php?user_id=1&format=csv" \
  -H "X-CRM-API-KEY: YOUR_API_KEY" \
  -o leads.csv
```

#### 4. Pagination (get 50 leads, skip first 100)
```bash
curl -X GET "https://your-crm-domain/CRM2/api/get_user_leads.php?user_id=1&limit=50&offset=100" \
  -H "X-CRM-API-KEY: YOUR_API_KEY"
```

### Response Example (JSON)

```json
{
  "success": true,
  "user_id": 1,
  "count": 3,
  "limit": 100,
  "offset": 0,
  "leads": [
    {
      "id": 15,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "555-1234",
      "company": "ACME Corp",
      "source": "meta-ads",
      "status": "hot",
      "assigned_to": 1,
      "assigned_to_name": "Admin User",
      "created_by": 1,
      "created_by_name": "Admin User",
      "created_at": "2025-11-18 10:30:00"
    },
    {
      "id": 14,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "phone": "555-5678",
      "company": "Beta Inc",
      "source": "meta-ads",
      "status": "new",
      "assigned_to": 1,
      "assigned_to_name": "Admin User",
      "created_by": 1,
      "created_by_name": "Admin User",
      "created_at": "2025-11-17 15:45:00"
    }
  ]
}
```

### Response Example (CSV)

```csv
id,name,email,phone,company,source,status,assigned_to,assigned_to_name,created_by,created_by_name,created_at
15,John Doe,john@example.com,555-1234,ACME Corp,meta-ads,hot,1,Admin User,1,Admin User,2025-11-18 10:30:00
14,Jane Smith,jane@example.com,555-5678,Beta Inc,meta-ads,new,1,Admin User,1,Admin User,2025-11-17 15:45:00
```

---

## Part 3: Postman Testing Examples

### Setup Postman Environment Variables

In Postman, create an environment with:

```
crm_domain: https://your-crm-domain/CRM2
api_key: YOUR_API_KEY
user_id: 1
```

### Test 1: Get All Leads for User

**Method**: GET  
**URL**: `{{crm_domain}}/api/get_user_leads.php?user_id={{user_id}}`  
**Headers**:
```
X-CRM-API-KEY: {{api_key}}
```

**Pre-request Script** (optional, validates setup):
```javascript
if (!pm.environment.get('api_key')) {
    throw new Error('API key not set in environment');
}
console.log('Fetching leads for user: ' + pm.environment.get('user_id'));
```

**Tests** (optional, validate response):
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response contains leads array", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('leads');
    pm.expect(jsonData.leads).to.be.an('array');
});

pm.test("Each lead has required fields", function () {
    var jsonData = pm.response.json();
    jsonData.leads.forEach(function(lead) {
        pm.expect(lead).to.have.property('id');
        pm.expect(lead).to.have.property('name');
        pm.expect(lead).to.have.property('email');
    });
});
```

### Test 2: Filter by Status

**Method**: GET  
**URL**: `{{crm_domain}}/api/get_user_leads.php?user_id={{user_id}}&status=hot`  
**Headers**:
```
X-CRM-API-KEY: {{api_key}}
```

### Test 3: Download as CSV

**Method**: GET  
**URL**: `{{crm_domain}}/api/get_user_leads.php?user_id={{user_id}}&format=csv`  
**Headers**:
```
X-CRM-API-KEY: {{api_key}}
```

(Postman will offer to save the response as a file)

### Test 4: Test Pagination

**Method**: GET  
**URL**: `{{crm_domain}}/api/get_user_leads.php?user_id={{user_id}}&limit=10&offset=0`

Then change offset to 10, 20, etc. to navigate pages.

---

## Part 4: Complete Flow (Meta Ads → CRM → Retrieve)

### Scenario: Franchise Owner Gets Leads from Meta Campaign

1. **Customer fills Meta Lead Ads form** (on Instagram/Facebook)
   - Form asks for: Name, Email, Phone, Company

2. **Meta sends webhook to your CRM** (`/api/meta_webhook.php`)
   - Lead is automatically created with source: `meta-ads`
   - Status: `new` (ready for assignment)
   - Assigned to: unassigned (admin assigns later)

3. **Franchise Admin logs in to CRM**
   - Views new leads from Meta
   - Assigns lead to a user (e.g., "Sales Rep John")

4. **Franchise Manager wants all their leads**
   - Uses API to retrieve: `GET /api/get_user_leads.php?user_id=5`
   - Gets JSON or CSV of all leads assigned to them
   - Syncs to spreadsheet, CRM dashboard, or mobile app

---

## Advanced: Fetch Full Lead Details from Meta

By default, the webhook receives minimal data (leadgen_id only). To get full lead details (name, email, phone, etc.), you need a Meta access token:

### Step 1: Get Meta Access Token

In Meta Business Suite:
1. Go to **Apps & Websites** → **Integrations Manager**
2. Generate a long-lived access token with `leads:read` permission

### Step 2: Update Webhook to Fetch Full Details

Edit `/api/meta_webhook.php` and uncomment the `fetchMetaLeadDetails()` call to pull name, email, phone from Meta's Graph API.

### Example: Fetch lead details

```bash
curl -X GET "https://graph.instagram.com/v18.0/LEADGEN_ID?fields=id,created_time,field_data&access_token=YOUR_META_TOKEN"
```

---

## Security Best Practices

1. **Webhook Validation**: Always verify the X-Hub-Signature header (implemented in webhook).
2. **API Key**: Store in environment variable, never in code.
3. **HTTPS Only**: Meta webhooks require HTTPS (use SSL certificate).
4. **Rate Limiting**: Add rate limits to `/api/get_user_leads.php` in production.
5. **Audit Logs**: Log all API requests for compliance.
6. **Rotate Keys**: Regenerate verification tokens and API keys periodically.

---

## Troubleshooting

### Webhook not receiving leads

- Verify webhook URL is HTTPS and publicly accessible
- Check CRM logs: `error_log()` output in `/api/meta_webhook.php`
- Verify token in Meta Business Suite matches stored token
- Test with Postman GET request to confirm webhook responds with challenge

### API returns "Invalid API key"

- Confirm you're using the correct API key from CRM settings
- Header must be exactly: `X-CRM-API-KEY: YOUR_KEY`
- Check for extra spaces or line breaks in the key

### No leads returned

- Verify user_id exists in CRM users table
- Check that leads are actually assigned to or created by that user
- Try without status filter first
- Check lead dates with: `SELECT * FROM leads WHERE assigned_to = YOUR_USER_ID;`

### CSV download shows as JSON

- Ensure `&format=csv` is in the URL
- Check Accept-Encoding header is not interfering

---

## Testing Checklist

- [ ] Webhook URL is HTTPS and publicly accessible
- [ ] Meta verification token stored in CRM settings
- [ ] Webhook responds to Meta's challenge request
- [ ] Test lead created in Meta Lead Ads form
- [ ] Lead appears in CRM database
- [ ] API key is correct and stored in CRM
- [ ] GET /api/get_user_leads.php works with correct user_id
- [ ] CSV export downloads correctly
- [ ] Status filter works (e.g., &status=hot)
- [ ] Pagination works (limit, offset)

---

## Support

For issues, check:
1. CRM error logs in `php://stderr` or Apache error log
2. Meta webhook test in Business Suite → Webhooks → Recent Deliveries
3. Postman request/response details
4. Database: `SELECT * FROM leads WHERE source='meta-ads';`

