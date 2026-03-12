# Analytics Dashboard - Quick Fix Guide

## Step 1: Diagnose the Issue ✓

Go to this URL in your browser:
```
http://localhost/CRM2/test_analytics.php
```

This will tell you exactly what's wrong.

---

## Step 2: Look for Green Checkmarks ✓

The test page will show:
- ✓ User logged in
- ✓ Database connection
- ✓ Leads count
- ✓ Walk-in tracking
- ✓ Sample lead data

---

## Step 3: If Something Shows RED ✗

**Red Error** = That's the problem

### Common Fixes:

#### If "User not logged in":
1. Go to http://localhost/CRM2/login.php
2. Login with:
   - Username: `superadmin`
   - Password: `password` (or your configured password)
3. Then try analytics dashboard again

#### If "Database connection ERROR":
1. Open `/Applications/XAMPP/xamppfiles/htdocs/CRM2/db.php`
2. Check the credentials (lines 3-7):
   - host: localhost
   - username: root
   - password: (should be empty for local XAMPP)
   - database: crm_pro

#### If "No leads found":
1. You need to create test leads first
2. Go to superadmin_dashboard.php
3. Click "Leads" > Create some sample leads
4. Then try analytics again

---

## Step 4: Access Analytics Dashboard

Once test_analytics.php shows all green checkmarks:

Go to:
```
http://localhost/CRM2/analytics_dashboard.php
```

---

## Troubleshooting Checklist

- [ ] Ran test_analytics.php
- [ ] All tests show green checkmarks
- [ ] Logged in as superadmin
- [ ] Database connection working
- [ ] Leads table has data
- [ ] Accessed analytics_dashboard.php
- [ ] See charts and metrics displaying

---

## If Charts Still Don't Show

**Check Browser Console:**
1. Press `F12` key
2. Go to "Console" tab
3. Look for red error messages
4. Send screenshot of errors

---

## URLs to Remember

| Page | URL |
|------|-----|
| Test Diagnostics | http://localhost/CRM2/test_analytics.php |
| Analytics Dashboard | http://localhost/CRM2/analytics_dashboard.php |
| Super Admin Dashboard | http://localhost/CRM2/superadmin_dashboard.php |
| Login | http://localhost/CRM2/login.php |

---

## Still Having Issues?

**Most Common Reason:** Page refreshed in browser before loading completely

**Solution:**
1. Go to analytics_dashboard.php
2. Wait 5-10 seconds for page to fully load
3. Charts should appear

---

**Summary:**
1. Open http://localhost/CRM2/test_analytics.php
2. Fix any RED errors shown
3. Then open http://localhost/CRM2/analytics_dashboard.php
4. Charts should now display ✓

