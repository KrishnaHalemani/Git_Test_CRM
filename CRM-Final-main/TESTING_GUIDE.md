# CRM Pro - Testing & Verification Guide

## Quick Start Testing (5 minutes)

### 1. **Login as Super Admin**
```
URL: http://localhost/CRM2/login.php
Username: admin
Password: admin
Expected: Redirects to dashboard_advanced.php or superadmin_dashboard.php
```

### 2. **Navigate to Super Admin Dashboard**
```
URL: http://localhost/CRM2/superadmin_dashboard.php
Expected: 
  - Success/Error messages visible at top
  - Summary cards showing totals
  - Sidebar with navigation
  - Dark theme sidebar
```

---

## Feature Testing

### ✅ Admin Management Section

#### Test Search Functionality:
1. Go to "Admins" section
2. Type in search box: "test" or part of admin name
3. **Expected:** Table filters in real-time
4. Click "All Branches" dropdown - filters by branch
5. Click "All Status" dropdown - filters by status

#### Test Add Admin:
1. Click "Add Admin" button
2. Fill form:
   - Username: `test_admin`
   - Full Name: `Test Admin User`
   - Email: `test@example.com`
   - Phone: `+1-555-1234`
   - Branch: `Main Branch`
   - Password: `test123`
3. Click "Create Admin"
4. **Expected:** Success message + new admin appears in table

#### Test Edit Admin:
1. Click edit (pencil) icon on any admin
2. Modal opens with fields pre-filled
3. Change Full Name to: `Updated Admin`
4. Leave password blank (keeps current)
5. Click "Update Admin"
6. **Expected:** Success message + table updates

#### Test Delete Admin:
1. Click delete (trash) icon on any admin
2. Confirm deletion
3. **Expected:** Success message + admin removed from table

---

### ✅ User Management Section

#### Test Search Functionality:
1. Go to "Users" section
2. Type in search box to filter
3. Use role filter (User/Admin)
4. Use status filter (Active/Inactive)
5. **Expected:** Real-time filtering

#### Test View User Profile:
1. Click blue "View" button on any user
2. Modal opens with user details:
   - Name, Email, Phone
   - Role, Status, Branch
   - Creation date
3. **Expected:** All fields display correctly

#### Test Activate/Deactivate:
1. Click green/red button on user row
2. Confirm action
3. **Expected:** Badge changes color + success message

#### Test Delete User:
1. Click delete (trash) icon
2. Confirm deletion
3. **Expected:** User removed + success message

---

### ✅ Lead Management

#### Test Table View:
1. Click "Leads" in sidebar (goes to leads_advanced.php)
2. Verify table displays with columns:
   - Lead name, email, phone, company
   - Service, status, priority, source
   - Estimated value, created date
3. **Expected:** All leads display correctly

#### Test View Toggle:
1. Click "Table" button (should be active)
2. Click "Kanban" button
3. **Expected:** Page switches to Kanban view

#### Test Kanban View:
1. Should see 6 columns: New, Contacted, Qualified, Hot, Converted, Lost
2. Each column shows count and lead cards
3. Each card shows: name, email, service, value
4. **Expected:** Leads organized by status

#### Test Drag & Drop:
1. In Kanban view, drag lead card to different column
2. **Expected:** Lead status updates + page refreshes

#### Test Lead Reassignment:
1. Click dropdown menu on any lead card (Kanban)
2. Click "Reassign"
3. Modal opens with admin dropdown
4. Select admin and click "Reassign Lead"
5. **Expected:** Success message + lead assigned

#### Test Add Lead:
1. Click "Add Lead" button
2. Fill form with:
   - Name: `Test Lead`
   - Email: `test@lead.com`
   - Phone: `+1-555-9999`
   - Company: `Test Company`
   - Service: `Web Development`
   - Status: `New`
   - Priority: `High`
3. Click "Add Lead"
4. **Expected:** Success message + lead appears in table/Kanban

---

### ✅ Settings Section

#### Test Company Settings:
1. Go to "Settings" section
2. Update:
   - Company Name: `My CRM Company`
   - Company Email: `contact@mycrm.com`
   - Company Phone: `+1-555-0000`
   - Theme Color: Select any color
3. Click "Save Settings"
4. **Expected:** Success message + refresh shows saved values

#### Test Logo Upload:
1. In Settings, click "Choose File" for logo
2. Select a PNG or JPG image
3. Click "Save Settings"
4. **Expected:** 
   - File uploads successfully
   - Preview displays uploaded image
   - File saved in uploads/ directory

---

### ✅ Permissions Section

#### Test View Permissions:
1. Go to "Permissions" section
2. **Expected:** See table with:
   - Feature names (left column)
   - 3 role columns (User, Admin, Super Admin)
   - ✓ or ✗ indicators
   - Descriptions

#### Verify Permissions Granted:
- **User Role:**
  - ✓ View own leads
  - ✓ Create leads
  - ✓ Edit leads
  - ✗ Manage users/admins

- **Admin Role:**
  - All User permissions +
  - ✓ View all leads
  - ✓ Reassign leads
  - ✓ Delete leads
  - ✓ Export leads
  - ✗ Manage system

- **Super Admin Role:**
  - All Admin permissions +
  - ✓ Manage admins
  - ✓ Manage users
  - ✓ Manage settings
  - ✓ View permissions

---

## Security Testing

### Test Role Enforcement:

#### 1. Test Dashboard Access:
```
As User: 
  - Try http://localhost/CRM2/dashboard_advanced.php
  - Expected: Redirects to login or shows error

As Admin/SuperAdmin:
  - Should display dashboard
```

#### 2. Test Export Access:
```
As User:
  - Try http://localhost/CRM2/export.php
  - Expected: Redirects to login

As Admin/SuperAdmin:
  - Should display export page
```

#### 3. Test Super Admin Dashboard:
```
As Admin:
  - Try http://localhost/CRM2/superadmin_dashboard.php
  - Expected: Redirects to login

As SuperAdmin:
  - Should display dashboard
```

### Test Data Validation:

#### 1. Create Admin with Missing Fields:
1. Click "Add Admin"
2. Leave Username empty
3. Try to submit
4. **Expected:** Form won't submit or shows validation error

#### 2. Invalid Email:
1. Click "Add Admin"
2. Enter invalid email: `notanemail`
3. Try to submit
4. **Expected:** Error or validation message

---

## Performance Testing

### Check Load Times:
1. Open Super Admin Dashboard
2. **Expected:** Loads in < 2 seconds
3. Check browser console for errors
4. **Expected:** No JavaScript errors

### Test with Large Data:
1. Create 50+ leads
2. Switch between Table/Kanban views
3. Use search/filters
4. **Expected:** Smooth performance, no lag

---

## Error Handling Tests

### Test Success Messages:
- Create admin → "Admin created successfully!"
- Update admin → "Admin updated successfully!"
- Delete admin → "Admin deleted successfully!"
- Delete user → "User deleted successfully!"
- Save settings → "Settings saved successfully!"

### Test Error Messages:
- Try to create admin without username → Show error
- Try to create admin with existing email → Show error
- Try to delete non-existent user → Show error

---

## Browser Compatibility

Test in:
- [x] Chrome/Chromium
- [x] Firefox
- [x] Safari (macOS)
- [x] Edge

**Expected:** All features work consistently

---

## Database Verification

### Check Migrated Columns:
```sql
-- Verify phone and branch columns exist
SHOW COLUMNS FROM users;
-- Expected: phone, branch columns present

-- Verify settings table
SELECT * FROM settings WHERE setting_key IN ('company_logo', 'theme_color');
-- Expected: 2 rows returned
```

### Check Sample Data:
```sql
-- Verify admin user exists
SELECT * FROM users WHERE role = 'admin';

-- Count all users
SELECT COUNT(*) FROM users;
```

---

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| 404 on superadmin_dashboard.php | File exists, check URL spelling |
| Redirect to login | User doesn't have required role |
| Settings not saving | Check uploads/ directory permissions |
| Logo not uploading | File size too large, wrong format, permissions issue |
| Kanban drag-drop not working | JavaScript enabled? Try refreshing |
| Search not filtering | Check browser console for JS errors |

---

## Final Verification Checklist

- [ ] All pages load without errors
- [ ] Role-based access control works
- [ ] Admin CRUD operations succeed
- [ ] User profile displays correctly
- [ ] Lead table/Kanban views work
- [ ] Lead reassignment works
- [ ] Settings save correctly
- [ ] Logo uploads successfully
- [ ] Permissions matrix displays
- [ ] Success messages appear
- [ ] No JavaScript errors in console
- [ ] Database tables updated correctly
- [ ] All links navigate correctly

---

## Support

If issues arise:
1. Check browser console (F12) for JS errors
2. Check PHP error logs in XAMPP
3. Verify database migration ran successfully
4. Ensure all new files are in place
5. Check file permissions on uploads/ directory

---

**Testing Complete! Your CRM is ready for use. 🎉**
