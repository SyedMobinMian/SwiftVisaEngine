# eTA Application Processing System

**Created by: Syed Mobin Mian**

---

## 1. Project Overview

This is a comprehensive web application designed to manage and process Electronic Travel Authorization (eTA) applications for multiple countries (e.g., Canada, UK, Vietnam). It features a user-friendly multi-step form, secure payment processing through Merchant Warrior, an administrative dashboard for monitoring, and automated generation of PDF documents and email notifications.

The system is built to be robust, scalable, and easy to maintain, with a clear separation between frontend and backend logic.

## 2. Key Features

-   **Multi-Country Support:** Easily configurable to handle application forms for different countries.
-   **Multi-Step Application Form:** A guided, step-by-step process for users to enter their information (Contact, Personal, Passport, Residential, Background).
-   **Solo & Group Applications:** Supports both individual and group/family applications, allowing one person to fill out forms for multiple travellers.
-   **Dynamic UI:** Features conditional fields that appear based on user input and chained dropdowns (Country → State → City) for a smooth user experience.
-   **Robust Validation:** Implements both client-side (JavaScript) and server-side (PHP) validation to ensure data integrity and provide immediate feedback.
-   **Secure Payment Gateway:** Integrated with **Merchant Warrior** using their secure payframe for token-based card processing, ensuring no sensitive card data touches the server.
-   **Automated Document Generation:** Creates PDF receipts and application summaries upon successful payment.
-   **Email Notifications:** Sends automated emails for application submission and payment confirmation using PHPMailer via SMTP.
-   **Admin Dashboard:** A powerful backend interface for administrators to:
    -   View key performance indicators (KPIs) and statistics.
    -   Monitor application statuses (Paid, Pending, etc.).
    -   Track revenue and user activity with interactive charts.
    -   View recently generated payment documents.
-   **Secure & Modern Backend:** Built with PHP, using PDO for safe database interactions and implementing CSRF protection on all forms.
-   **Environment-based Configuration:** Uses a `.env` file for easy and secure management of credentials and settings.

## 3. Technology Stack

| Category      | Technology                                                              |
| :------------ | :---------------------------------------------------------------------- |
| **Backend**   | PHP 8+, MySQL / MariaDB                                                 |
| **Frontend**  | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5                             |
| **PHP Libs**  | `phpmailer/phpmailer` (via Composer)                                    |
| **JS Libs**   | jQuery, Select2, intl-tel-input, Chart.js                               |
| **Database**  | PDO for database abstraction                                            |
| **Payments**  | Merchant Warrior (Payframe Integration)                                 |

## 4. Project Structure

The project follows a modular structure to keep the codebase organized and maintainable.

```
Morgill_eTA-2/
├── admin/              # Admin panel files (dashboard, login, etc.)
├── assets/             # Frontend assets (CSS, JS, images)
├── core/               # Core application files (bootstrap, config, db, mailer)
│   └── schemas/        # SQL schema files for database setup
├── includes/           # Reusable PHP view components (e.g., navbar)
├── modules/            # Main business logic of the application
│   ├── ajax/           # Server-side scripts for AJAX requests
│   ├── forms/          # Logic for form display, validation, and emails
│   └── payments/       # Payment gateway integration and document generation
├── uploads/            # Storage for generated PDF documents
├── vendor/             # Composer dependencies
├── .env                # Environment configuration (DB, SMTP, API keys) - IMPORTANT
├── form.php            # Main entry point for the user-facing application form
├── composer.json       # PHP dependencies definition
└── README.md           # This file
```

## 5. Setup and Installation

Follow these steps to get the project running on your local machine.

### Prerequisites

-   A local server environment like **XAMPP**, WAMP, or MAMP.
-   **PHP 8.0** or higher.
-   **MySQL** or **MariaDB**.
-   **Composer** for managing PHP dependencies.

### Step-by-Step Guide

1.  **Clone the Repository:**
    ```bash
    git clone <repository-url> Morgill_eTA-2
    cd Morgill_eTA-2
    ```

2.  **Database Setup:**
    -   Create a new database in your MySQL server (e.g., `dcform_db`).
    -   Import the database schema from `core/schemas/admin_schema.sql`.
    -   *Note: The main application schema (e.g., `applications`, `travellers`) appears to be created on-the-fly by the application code.*

3.  **Configuration:**
    -   Create a new file named `.env` in the root directory.
    -   Copy the contents of `.env.example` (if it exists) or use the template below and update it with your specific configuration.

    **`.env` Template:**
    ```ini
    ; --- Database Configuration ---
    DB_HOST="localhost"
    DB_NAME="xxxx_db"
    DB_USER="root"
    DB_PASS=""

    ; --- Application URL ---
    APP_URL="http://localhost/Morgill_eTA-2"

    ; --- SMTP Mailer Configuration ---
    SMTP_HOST="smtp.example.com"
    SMTP_PORT=587
    SMTP_SECURE="tls"
    SMTP_USERNAME="your-smtp-username"
    SMTP_PASSWORD="your-smtp-password"
    FROM_EMAIL="no-reply@example.com"
    FROM_NAME="eTA Application Team"
    ADMIN_EMAIL="admin@example.com"
    EMAIL_BCC_ADMIN=true

    ; --- Merchant Warrior Payment Gateway ---
    MW_CLIENT_ID="your-merchant-uuid"
    MW_CLIENT_SECRET="your-api-key"
    MW_MMID="your-merchant-id"
    MW_API_BASE="https://base.merchantwarrior.com"
    MW_PAYFRAME_BASE="https://payframe.merchantwarrior.com"
    MW_PAYFRAME_JS="https://payframe.merchantwarrior.com/js/payframe.js"

    ; --- Application Fees ---
    ETA_FEE=700 ; Fee in minor units (e.g., cents, so 700 = $7.00)

    ; --- Admin User Seed (for first-time setup) ---
    ADMIN_SEED_USERNAME="masteradmin"
    ADMIN_SEED_PASSWORD="a_very_strong_password"
    ```

4.  **Install Dependencies:**
    -   Run Composer in the project root to install the required PHP libraries (like PHPMailer).
    ```bash
    composer install
    ```

5.  **Run the Application:**
    -   Navigate to `http://localhost/Morgill_eTA-2/form.php` in your browser to see the application form.
    -   Access the admin panel at `http://localhost/Morgill_eTA-2/admin/`. The first time you access it, the seed admin user from your `.env` file will be created.

## 6. Key Workflows

### Application Form Submission

1.  The user fills out the multi-step form (`form.php`).
2.  Each step is saved via an AJAX call to a corresponding script in `modules/ajax/`.
3.  Client-side validation (`assets/js/validator1.js`) and server-side validation (`modules/forms/validate.php`) ensure data quality.
4.  After all travellers' details are entered, the user is taken to a confirmation screen.

### Payment Process

1.  On the payment page, the user fills in their billing details.
2.  Clicking "Proceed to Secure Payment" initiates a payment session with the backend (`modules/payments/payment.php`).
3.  The backend returns credentials to load the Merchant Warrior payframe.
4.  The user enters card details directly into the secure iframe.
5.  Upon submission, the iframe returns a `cardID` (token) to the frontend.
6.  This token is sent to `modules/payments/payment_verify.php`, which processes the payment via a server-to-server API call to Merchant Warrior.
7.  The user is redirected to the `thank-you.php` page, while background tasks (PDF generation, email sending) are completed. This prevents the user from waiting.
