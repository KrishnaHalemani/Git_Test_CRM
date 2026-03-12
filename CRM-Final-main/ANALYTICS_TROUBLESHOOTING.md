# Analytics Dashboard - Troubleshooting Guide

## Issue: Analytics Dashboard Page Not Displaying

### Quick Diagnosis Steps

**Step 1: Test your login**
- Make sure you are logged in
- Try accessing: `http://localhost/CRM2/login.php`
- Use test credentials provided in database setup

**Step 2: Run the diagnostic page**
- Go to: `http://localhost/CRM2/test_analytics.php`
- This will show you:
  ✓ If you're logged in correctly
  ✓ If database connection is working
  ✓ If leads data exists
  ✓ If walk-in tracking is working
  ✓ Sample lead data

**Step 3: Check browser console**
- Press `F12` to open Developer Tools
- Go to "Console" tab
- Look for any red error messages
- Check "Network" tab for failed requests

**Step 4: Check PHP Errors**
- Open `http://localhost/CRM2/test_analytics.php`
- It will display any database errors

---

## Common Issues & Solutions

### Issue 1: White Blank Page
**Cause:** Database error or PHP error
**Solution:**
1. Run test_analytics.php
2. Check for database connection errors
3. Verify db.php file exists and is configured correctly

### Issue 2: "Undefined variable" errors
**Cause:** Database query failed
**Solution:**
1. Run test_analytics.php
2. Check if leads table exists: `SHOW TABLES;`
3. Verify getLeads() function in db.php

### Issue 3: Charts not displaying
**Cause:** No data or Chart.js not loading
**Solution:**
1. Check browser console for JavaScript errors
2. Verify Chart.js CDN is loading
3. Check if leads data exists in database

### Issue 4: Walk-in counts showing 0
**Cause:** No leads with source='walk-in'
**Solution:**
1. Create test leads with source='walk-in'
2. Or modify analytics_dashboard.php to show all leads as default

---

## File Fixes Applied

### analytics_dashboard.php (Updated)
✅ Walk-in query simplified to use `source='walk-in'` only
✅ Added error handling for failed queries
✅ Added fallback to 0 if query fails
✅ Support for both PDO and mysqli

---

## Steps to Get Analytics Dashboard Working

### Option A: Quick Test (Recommended First)

1. **Open test page:**
   ```
   http://localhost/CRM2/test_analytics.php
   ```

2. **Check the test results:**
   - Green checkmarks = working ✓
   - Red X = error ✗

3. **Fix any errors shown**

4. **Try analytics dashboard:**
   ```
   http://localhost/CRM2/analytics_dashboard.php
   ```

---

### Option B: Manual Testing

1. **Verify you're logged in:**
   - Go to `http://localhost/CRM2/superadmin_dashboard.php`
   - If redirected to login, log in first

2. **Click Analytics link from dashboard**
   - Or visit directly: `http://localhost/CRM2/analytics_dashboard.php`

3. **If page is blank:**
   - Run test_analytics.php to diagnose
   - Share the error messages

---

### Option C: Database Verification

**Check if database is set up:**
```sql
SHOW TABLES;
DESCRIBE leads;
SELECT COUNT(*) FROM leads;
SELECT DISTINCT source FROM leads;
```

**Check for walk-in leads:**
```sql
SELECT COUNT(*) as walk_ins FROM leads WHERE source = 'walk-in';
```

---

## Analytics Dashboard Features

Once working, you'll see:

✅ **Key Metrics:**
- Total Leads
- Conversions
- Conversion Rate
- Follow-ups (Last 15 Days)

✅ **Pie Charts:**
- Lead Status Distribution
- Lead Source Distribution
- Lead Category Distribution

✅ **Bar Charts:**
- Daily Leads Trend
- Daily Follow-ups
- Daily Conversions
- Walk-in Counts Per Day

✅ **Role-Based Views:**
- Superadmin: All data
- Admin: Team data
- User: Personal data only

---

## Files Involved

| File | Purpose |
|------|---------|
| analytics_dashboard.php | Main analytics page |
| test_analytics.php | Diagnostic tool |
| db.php | Database functions |
| superadmin_dashboard.php | Contains Analytics link |

---

## Browser Requirements

✅ Modern browser (Chrome, Firefox, Safari, Edge)
✅ JavaScript enabled
✅ Chart.js CDN accessible
✅ Bootstrap CSS accessible

---

## If Still Not Working

**Please provide:**
1. What do you see on the page? (Blank, error message, partial content)
2. Output from test_analytics.php
3. Browser console errors (F12 > Console tab)
4. Database status (Run SHOW TABLES; command)

---

## Quick Links

- **Main Dashboard:** http://localhost/CRM2/superadmin_dashboard.php
- **Analytics Dashboard:** http://localhost/CRM2/analytics_dashboard.php
- **Test Page:** http://localhost/CRM2/test_analytics.php
- **Login:** http://localhost/CRM2/login.php

---

## Next Steps

1. ✅ Run test_analytics.php
2. ✅ Review test results
3. ✅ Fix any issues shown
4. ✅ Access analytics_dashboard.php
5. ✅ Verify charts are displaying

