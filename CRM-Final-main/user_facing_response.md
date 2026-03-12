It sounds like `import_leads.php` might be encountering a PHP error before it can redirect you back to the dashboard. This would result in a blank page.

To help diagnose this, please follow these steps:

1.  **Enable Error Display (Temporarily):**
    *   I've updated `/Applications/XAMPP/xamppfiles/htdocs/CRM2/import_leads.php` to include:
        ```php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ```
        at the very top of the file. This should force any PHP errors to be displayed on the page.

2.  **Attempt the Import Again:**
    *   Go back to your super admin dashboard and try importing the franchise leads file again.
    *   If there's a PHP error, it should now be displayed directly on the `import_leads.php` page instead of a blank screen. **Please provide the exact error message you see.**

3.  **Check PHP Error Log:**
    *   Even if an error displays on the page, more details are often written to the PHP error log. The path to this log in XAMPP is typically:
        `/Applications/XAMPP/xamppfiles/logs/php_error.log`
    *   Please check this file for any recent errors, especially those occurring around the time of your import attempt, and share them.

4.  **Verify Required PHP Extensions:**
    *   The `SimpleXLSX` library (used for `.xlsx` files) requires the `simplexml` and `zip` PHP extensions to be enabled.
    *   Please check your `php.ini` file (often found at `/Applications/XAMPP/xamppfiles/etc/php.ini`) and ensure that the following lines are uncommented (meaning, remove the `;` at the beginning of the line if it's there):
        ```
        extension=zip
        extension=simplexml
        ```
    *   **After modifying `php.ini`, you *must* restart your Apache server in XAMPP** for the changes to take effect.

5.  **Check Import Error Log:**
    *   Remember to also check the `/Applications/XAMPP/xamppfiles/htdocs/CRM2/tools/import_errors.log` file. While this logs errors caught by the script, it might also contain initial traces even if a fatal error occurs later.

Please provide any error messages you find after performing these steps. Once we've debugged the issue, remember to remove the `error_reporting(E_ALL); ini_set('display_errors', 1);` lines from `import_leads.php`.