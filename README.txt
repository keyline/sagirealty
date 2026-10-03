# SAGI Realty Landing Page

Professional responsive landing page based on the supplied SAGI Realty New Town, Kolkata promotional creatives.

## Files

- `index.html` — complete landing page
- `css/style.css` — responsive design and animations
- `js/script.js` — mobile menu, scroll reveal, gallery modal and form validation/AJAX
- `submit.php` — PHP server-side validation + email submission
- `thank-you.html` — success page
- `assets/` — supplied promotional creatives

## Setup

1. Upload the complete folder, including `vendor/`, to a PHP-enabled hosting account.
2. Keep `smtp-config.php` private and do not commit it to version control.
3. Confirm the sender address is verified in Brevo.
4. Open the website through its HTTP/HTTPS URL. A static Live Server cannot execute `submit.php`.

## Email delivery

The enquiry form uses PHPMailer with authenticated Brevo SMTP, STARTTLS and port 587.
If dependencies are not uploaded, run `composer install --no-dev` on the server.

## Important

The project numbers/pricing in the page are taken from the supplied promotional artwork. Verify the final project specifications, price, approvals and availability with the authorized sales representative before publishing.

## Suggested production URL

Use a dedicated landing-page URL such as:
`https://yourdomain.com/new-town-pre-launch/`

## Admin lead dashboard

Every valid form submission is stored before the email notification is sent. Open
`/admin.php` on the same website to search leads, update status, or export CSV.

To configure admin access:

1. Copy `admin-config.example.php` to `storage/admin-config.php`.
2. Generate a secure password hash:
   `php -r "echo password_hash('Choose-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"`
3. Paste the hash into `storage/admin-config.php` and set the desired username.

The private config and lead database are excluded from Git. PHP must be able to
write to the project directory so it can create `storage/` on the first submission.
