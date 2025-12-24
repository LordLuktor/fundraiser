# Changelog

## [1.0.1] - 2024-12-24

### Changed
- Integrated Stripe settings as tab in main plugin settings page
- Removed separate Stripe Settings submenu for cleaner UX
- Improved navigation and settings organization

### Technical
- Updated `admin/views/settings.php` with Payouts & Stripe tab
- Modified `includes/Core.php` to remove separate submenu
- Added Git Updater headers for automatic updates

---

## [1.0.0] - 2024-12-24

### Added - Initial Release

#### WC Vendors Integration
- Complete integration with WC Vendors Free (no premium plugin costs)
- Automatic fundraiser-to-vendor conversion
- 7% platform commission enforcement
- 93% of sales go directly to fundraisers

#### Stripe Connect OAuth2
- One-click Stripe account connection for platform
- Secure OAuth2 flow with CSRF protection
- Individual Stripe accounts for each fundraiser
- Instant automated payouts via Stripe Transfer API

#### Vendor Shop Pages
- Beautiful campaign listing pages at `/vendors/{vendor-slug}/`
- Individual campaign pages at `/vendors/{vendor-slug}/{campaign-slug}/`
- Responsive grid layouts with progress bars
- Featured images and campaign descriptions
- Real-time goal tracking

#### Payout System
- Multiple payout methods: Stripe, PayPal, Venmo, CashApp, Bank Transfer
- Encrypted account data storage
- Automated payout scheduling (instant, daily, weekly, monthly)
- Email notifications for payouts
- Complete transaction history

### New Files

**Core Integration:**
- `includes/VendorIntegration.php` - Maps fundraisers to WC Vendors
- `includes/VendorCampaignIntegration.php` - Campaign shop page URLs
- `includes/PayoutManager.php` - Multi-method payout logic
- `includes/StripeConnectPayout.php` - Stripe instant payouts
- `includes/StripeOAuthManager.php` - OAuth2 authentication
- `includes/WCVendorsPayoutBridge.php` - Bridges WC Vendors with Stripe
- `includes/WeeklyPayoutScheduler.php` - Scheduled payout cron

**Templates:**
- `templates/vendor-shop-campaigns.php` - Campaign listing page
- `templates/vendor-campaign-page.php` - Individual campaign page

**Admin:**
- `admin/StripeSettingsPage.php` - Stripe connection UI

**Database:**
- `wp_fundraiser_payout_accounts` - Vendor payout account data
- `wp_fundraiser_payouts` - Payout transaction history

### Requirements
- WordPress 6.4+
- PHP 8.1+
- WooCommerce (active)
- WC Vendors Free (active)

### Cost Savings
- Avoided WC Vendors Pro: $199/year saved
- Built with free plugins and custom integration
