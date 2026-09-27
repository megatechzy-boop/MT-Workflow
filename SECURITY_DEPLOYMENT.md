# Security and cPanel deployment checklist

1. Import the final database schema into the production database.
2. Set production database credentials in config.php.
3. Use a strong admin password and remove test users.
4. Set PHP production mode: display_errors=0, log_errors=1.
5. Enable HTTPS and secure session cookies.
6. Keep PHP execution disabled inside uploads.
7. Verify upload size limits and file type validation.
8. Confirm Admin, Employee, and Client access with separate test accounts.
9. Export and restore a database backup before launch.
10. Test payroll, invoices, expenses, cash flow, and restricted views.
