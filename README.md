# Personal Expense Tracker Management Application

A secure, modular, student-level web application developed for the **Secure Software Engineering Laboratory Examination**.

---

## 1. Overview & Technology Stack

The application allows registered users to securely manage personal financial records (income, expenses, custom categories, monthly summaries, and financial reports) with strict access control ensuring each user only accesses their own financial data.

- **Backend**: PHP 7.4+ / 8.x
- **Database**: MySQL 5.7+ / MariaDB 10.3+ with `utf8mb4` charset
- **Database Access**: PHP Data Objects (PDO) with prepared statements
- **Authentication**: Native PHP Sessions with hardened cookie flags
- **Frontend**: HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons 1.11

---

## 2. Project Directory Structure

```text
expense-tracker/
├── config/
│   └── database.php           # Isolated PDO database configuration & error handling
├── database/
│   └── database.sql           # Complete schema (tables, foreign keys, indexes)
├── includes/
│   ├── auth.php               # Session initialization, login verification, user helpers
│   ├── audit.php              # Security audit logging helper (logAudit)
│   ├── csrf.php               # CSRF token generation & validation (csrfField)
│   ├── validation.php         # Server-side validation helpers & XSS escaper (escapeOutput)
│   ├── header.php             # Responsive Bootstrap 5 header & authenticated navbar
│   └── footer.php             # Common footer, script inclusions & structural closing
├── assets/
│   └── css/
│       └── style.css          # Custom styling (gradient stat cards, auth cards, print media)
├── index.php                  # Application entry point (routing to dashboard or login)
├── register.php               # User registration with password policy & default categories
├── login.php                  # Authentication with generic errors & session regeneration
├── logout.php                 # Secure session destruction & cookie expiration
├── dashboard.php              # Real-time financial summary cards & recent transactions
├── add_transaction.php        # Transaction creation with category ownership validation
├── transactions.php           # Filterable & searchable transaction history
├── view_transaction.php       # Transaction inspection with IDOR ownership validation
├── edit_transaction.php       # Transaction modification with ownership & category checks
├── delete_transaction.php     # Transaction deletion with confirmation & ownership check
├── categories.php             # User category management table
├── add_category.php           # Category creation with uniqueness & length checks
├── edit_category.php          # Category renaming with ownership & uniqueness checks
├── delete_category.php        # Category deletion preventing orphan transaction references
├── monthly_summary.php        # Month-by-month financial summary & category breakdown
├── reports.php                # Custom date-range reports with print & secure CSV export
└── README.md                  # Project documentation & laboratory guide
```

Total: **26 source files**

---

## 3. Database Schema

The database schema is defined in `database/database.sql` and includes 4 tables:

1. **`users`**
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `name` (VARCHAR(100) NOT NULL)
   - `email` (VARCHAR(255) NOT NULL UNIQUE)
   - `password` (VARCHAR(255) NOT NULL)
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)

2. **`categories`**
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `user_id` (INT NOT NULL, FOREIGN KEY -> `users(id)` ON DELETE CASCADE)
   - `name` (VARCHAR(100) NOT NULL)
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
   - `UNIQUE KEY unique_category_per_user (user_id, name)`

3. **`transactions`**
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `user_id` (INT NOT NULL, FOREIGN KEY -> `users(id)` ON DELETE CASCADE)
   - `category_id` (INT NOT NULL, FOREIGN KEY -> `categories(id)` ON DELETE RESTRICT)
   - `type` (ENUM('income', 'expense') NOT NULL)
   - `amount` (DECIMAL(12, 2) NOT NULL)
   - `description` (VARCHAR(500) DEFAULT '')
   - `transaction_date` (DATE NOT NULL)
   - `created_at`, `updated_at` (TIMESTAMPS)
   - Indexes on `(user_id, transaction_date)` and `(user_id, type)`

4. **`audit_logs`**
   - `id` (INT AUTO_INCREMENT PRIMARY KEY)
   - `user_id` (INT NULL, FOREIGN KEY -> `users(id)` ON DELETE SET NULL)
   - `action` (VARCHAR(100) NOT NULL)
   - `details` (TEXT)
   - `ip_address` (VARCHAR(45))
   - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
   - Indexes on `user_id`, `action`, and `created_at`

---

## 4. Secure Software Engineering Implementations

### A. Authentication & Password Security
- Passwords are encrypted using PHP's native `password_hash($password, PASSWORD_DEFAULT)` (BCrypt / Argon2).
- Verification uses constant-time `password_verify($password, $hash)`.
- Password policy enforced server-side: Minimum 8 characters, maximum 128 characters, at least 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.
- Duplicate email prevention checks exist server-side before insertion.
- Plaintext passwords are never logged, displayed, or stored.

### B. Session Security & Session Hijacking/Fixation Defenses
- Cookie parameters are hardened via `session_set_cookie_params()`:
  - `httponly => true`: Prevents client-side scripts from reading the session cookie via `document.cookie` (mitigating XSS session theft).
  - `samesite => Strict`: Prevents the cookie from being sent in cross-site requests (protecting against CSRF).
  - `secure => true`: Enforced whenever HTTPS is active.
- `session_regenerate_id(true)` is executed immediately upon successful login to defeat Session Fixation attacks.
- `logout.php` unsets `$_SESSION`, destroys the session record with `session_destroy()`, and expires the cookie.

### C. IDOR (Insecure Direct Object References) & Authorization Model
- **Strict Server-Side Ownership Principle**:
  ```text
  Client Request (URL/POST) ──> Extract $_SESSION['user_id']
                                          │
                                          ▼
                            WHERE id = ? AND user_id = ?
  ```
- No user identity is trusted from `GET`, `POST`, or cookies.
- When viewing, updating, or deleting a transaction or category, queries bind both the entity ID and the session `user_id`:
  ```sql
  SELECT * FROM transactions WHERE id = ? AND user_id = ?
  UPDATE transactions SET ... WHERE id = ? AND user_id = ?
  DELETE FROM transactions WHERE id = ? AND user_id = ?
  ```
- **Category Ownership Validation**: When saving or editing a transaction, the selected `category_id` is validated server-side to confirm it belongs to the authenticated user before executing the insert/update.
- Attempted unauthorized accesses to other users' entities trigger `UNAUTHORIZED_ACCESS` entries in the `audit_logs` table and display generic error messages without leaking data existence.

### D. SQL Injection Protection
- 100% of queries use PDO prepared statements with parameter binding (`execute([...])`).
- `PDO::ATTR_EMULATE_PREPARES => false` ensures queries are executed with native database prepared statements.
- Dynamic filters (search, type, category, date range) in `transactions.php` build query parameters securely via positional placeholders (`?`).

### E. Cross-Site Scripting (XSS) Prevention
- All user-controlled output rendered in HTML templates is escaped using `escapeOutput()`:
  ```php
  function escapeOutput(?string $input): string {
      return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
  }
  ```
- Applied to user names, category titles, transaction descriptions, filter parameters, and error/flash messages.

### F. Cross-Site Request Forgery (CSRF) Protection
- Per-session cryptographically secure 32-byte tokens generated via `random_bytes(32)`.
- Embedded into all state-changing forms using `csrfField()`:
  ```php
  <input type="hidden" name="csrf_token" value="...">
  ```
- Validated on POST using timing-safe `hash_equals($_SESSION['csrf_token'], $token)`.
- Submissions with invalid or missing CSRF tokens are rejected immediately.

### G. Comprehensive Audit Logging
- Security-relevant events recorded in `audit_logs`:
  - `REGISTER`: New account created
  - `LOGIN_SUCCESS`: Valid credentials supplied
  - `LOGIN_FAILED`: Invalid login attempted (logs attempted email, never password)
  - `LOGOUT`: Session terminated
  - `TRANSACTION_CREATE`: Income or expense recorded
  - `TRANSACTION_UPDATE`: Transaction details modified
  - `TRANSACTION_DELETE`: Transaction removed
  - `CATEGORY_CREATE`, `CATEGORY_UPDATE`, `CATEGORY_DELETE`: Category changes
  - `UNAUTHORIZED_ACCESS`: Attempted access to an entity owned by another user
  - `REPORT_EXPORT`: Financial report exported to CSV
- Includes timestamp, user ID (when known), action code, contextual summary, and client IP address.

### H. Information Leakage Prevention & Error Handling
- Technical database errors (e.g., `PDOException`) are caught and written to PHP's private `error_log()`.
- Users receive generic, safe feedback (e.g., "Invalid email or password", "Transaction not found or access denied").
- Database queries, connection credentials, and stack traces are never exposed in user responses.

---

## 5. Installation & Setup Guide

### Step 1: Install XAMPP or WAMP
Ensure Apache and MySQL / MariaDB services are installed and active.

### Step 2: Database Initialization
Import `database/database.sql` using MySQL CLI or phpMyAdmin:
```bash
mysql -u root -p < database/database.sql
```
*(If no password is set, omit `-p` or press Enter when prompted.)*

### Step 3: Application Configuration
Open `config/database.php` and verify credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'expense_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 4: Web Deployment
Copy the `expense-tracker` directory to the web server root:
- For XAMPP on Windows: `C:\xampp\htdocs\expense-tracker\`
- For Apache on Linux: `/var/www/html/expense-tracker/`

### Step 5: Run Application
Open your browser and navigate to:
```text
http://localhost/expense-tracker/
```

---

## 6. Demonstration / Examination Test Procedures

| # | Test Scenario | Expected Result | Verified In Code |
|---|---------------|-----------------|------------------|
| 1 | **Registration Policy** | Reject passwords < 8 chars or missing complexity | `includes/validation.php` (`validatePassword`) |
| 2 | **Default Categories** | 9 standard categories auto-provisioned upon registration | `register.php` (lines 53-57) |
| 3 | **Generic Login Error** | "Invalid email or password" whether email exists or not | `login.php` (line 44) |
| 4 | **Session Fixation** | Session ID regenerated upon successful authentication | `login.php` (`session_regenerate_id(true)`) |
| 5 | **CSRF Defense** | State-changing POST rejected if `csrf_token` altered | `includes/csrf.php` & all POST forms |
| 6 | **IDOR on Transaction** | User A cannot view/edit/delete `/edit_transaction.php?id=UserB_ID` | `edit_transaction.php` (`WHERE id = ? AND user_id = ?`) |
| 7 | **Category IDOR** | User A cannot assign User B's `category_id` in transaction | `add_transaction.php` (lines 44-49) |
| 8 | **Delete Category Restrict**| Cannot delete category with existing transactions | `delete_category.php` (lines 49-51) |
| 9 | **SQL Injection** | SQL payloads (`' OR 1=1 --`) safely treated as literal strings | PDO prepared statements across all files |
| 10 | **XSS Protection** | HTML tags in descriptions/categories encoded as HTML entities | `escapeOutput()` across all views |
| 11 | **Monthly Summary** | Calculations restricted strictly to authenticated session user | `monthly_summary.php` (lines 19-35) |
| 12 | **CSV Formula Injection** | CSV export prepends tab character to cells starting with `=`, `+`, `-`, `@` | `reports.php` (lines 66-70) |
| 13 | **Audit Trail** | All logins, failures, modifications, and unauthorized attempts logged | `audit_logs` table |
