# CRUD Operations Quick Reference Guide

## Super Admin Dashboard - User Management

### How to Create a New Admin or User

1. Go to **Super Admin Dashboard**
2. Click **"Add Admin"** or **"Add User"** button
3. Fill in the form:
   - **Full Name**: Enter the person's full name
   - **Username**: Unique identifier for login
   - **Email**: Valid email address
   - **Branch**: Office location (e.g., "Head Office", "Mumbai Branch", "Delhi Branch")
   - **Password**: Secure password for the account
4. Click **"Create Admin"** or **"Create User"**
5. Success message appears at the top

---

### How to Edit an Admin or User

1. In the **Manage Admins** or **Manage Users** section, find the person
2. Click the **pencil icon (Edit)** button in the Actions column
3. The **Edit User** modal opens with their current information
4. Update any of the following:
   - **Full Name**
   - **Email**
   - **Branch**
   - **Status** (Active or Inactive)
   - **Password** *(optional - leave blank to keep current password)*
5. Click **"Update User"**
6. Success message confirms the changes

---

### How to Delete an Admin or User

1. In the **Manage Admins** or **Manage Users** section, find the person
2. Click the **trash icon (Delete)** button in the Actions column
3. A **confirmation dialog** appears asking "Are you sure you want to delete [Name]?"
4. Click **"Delete User"** to confirm, or **"Cancel"** to abort
5. User is permanently removed from the system
6. Success message confirms deletion

**⚠️ Important:** You cannot delete your own account for security reasons!

---

## Branch Management

### Setting Branches During User Creation

When creating a new admin or user:
- **Branch field** accepts any text input
- Common branch names:
  - "Head Office"
  - "Mumbai Branch"
  - "Delhi NCR"
  - "Bangalore Office"
  - Custom branch names as needed

### Viewing Branches

- Branch information displays in user/admin tables
- Branch field can be edited anytime by editing the user
- All created/modified users retain their branch assignment

---

## Walk-in Tracking

### Understanding Walk-in Counts

- **Walk-in Count Per Day** chart in Analytics Dashboard shows actual walk-in leads
- Tracks leads where:
  - **Source = "walk-in"** OR
  - **walk_in field = TRUE**
- Counts show real data from your CRM (not simulated)

### Role-Based Walk-in Visibility

| Role | Sees |
|------|------|
| **Superadmin** | All walk-in leads across organization |
| **Admin** | Walk-in leads from their team |
| **User** | Only their own walk-in leads |

### Creating Walk-in Leads

When adding leads to the CRM:
- Set **Source = "walk-in"** for walk-in customers
- Walk-in count automatically updates in analytics
- Dashboard reflects changes in real-time

---

## Sidebar Navigation (Leads Management)

### Current Navigation Items

✅ **Dashboard** - Main dashboard view
✅ **Leads** - Lead management (current page)
✅ **Analytics** - Analytics & reports (Admin/Superadmin only)
✅ **Reports** - Detailed reports (Admin/Superadmin only)
✅ **Export** - Export lead data

### Removed Items

❌ **Profile** - Removed for cleaner navigation

---

## Common Tasks

### Task: Create Branch Manager

1. Click **"Add Admin"**
2. Enter name, username, email
3. Enter branch: "Mumbai Branch"
4. Set password
5. Click Create
6. Branch manager account ready!

### Task: Update User's Branch

1. Find user in **Manage Users**
2. Click **Edit** (pencil icon)
3. Change **Branch** field to new location
4. Click **Update User**
5. Done!

### Task: Deactivate User Without Deleting

1. Find user in **Manage Users**
2. Click **Edit** (pencil icon)
3. Change **Status** to "Inactive"
4. Click **Update User**
5. User account disabled but data preserved

---

## Tips & Best Practices

1. **Branch Naming**: Use consistent naming across the organization
2. **Passwords**: Ensure strong, secure passwords when creating accounts
3. **Regular Updates**: Keep user information current (branch changes, status updates)
4. **Data Backup**: Backup database before bulk user operations
5. **Audit Trail**: Monitor who creates/edits/deletes users regularly

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Cannot delete own account | Try deleting another user first; self-delete prevented for security |
| Branch field empty | Default is "Head Office" if not specified |
| Walk-in chart shows zero | Check if leads are marked with source="walk-in" in database |
| Edit modal doesn't open | Ensure JavaScript is enabled in browser |
| Changes not saving | Verify all required fields are filled; check email uniqueness |

---

## Support

For issues or questions:
1. Check browser console for errors (F12)
2. Verify database connection in `db.php`
3. Ensure required fields are completed
4. Check user permissions and roles

