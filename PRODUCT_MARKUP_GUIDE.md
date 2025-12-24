# Product Markup & Cost Tracking System

## ✅ What's Been Implemented

Your Fundraiser Pro plugin now tracks product costs and automatically calculates markup profits on physical products! This works alongside the 5% platform fee to give you **two revenue streams**:

1. **Product Markup** - Profit on physical goods (your cost vs. sale price)
2. **Platform Fee** - 5% commission on all transactions

---

## How It Works

### Dual Revenue Model

```
Example: T-Shirt Sale

Customer pays: $25.00
   ↓
Your cost from supplier (Yoycol): $12.00
Your markup profit: $13.00 (108% markup!)
   ↓
Platform fee (5% of $25): $1.25
   ↓
Campaign receives: $25.00 - $13.00 - $1.25 = $10.75

YOUR TOTAL PROFIT: $13.00 (markup) + $1.25 (fee) = $14.25
CAMPAIGN GETS: $10.75
TOTAL PAID BY CUSTOMER: $25.00 ✓
```

---

## Product Editor Features

When you edit a WooCommerce product, you now have these new fields in the **Pricing** tab:

### 1. **Product Cost**
- Your cost from the supplier (Yoycol, USPrintFactory, etc.)
- **Auto-fills with the initial product price when you first create a product**
- You can manually edit this field anytime
- Example: If a t-shirt costs you $12, the field will show `12.00` initially

### 2. **Markup Info Display**
- Automatically calculates and shows:
  - Cost (your base cost)
  - Price (current selling price)
  - Markup amount in dollars
  - Markup percentage
  - Profit per sale

**Example Display:**
```
Markup Info
━━━━━━━━━━━━━━━━━━
Cost: $12.00
Price: $25.00
Markup: $13.00 (108.3%)
Your profit per sale: $13.00
```

---

## Workflow for Physical Products

### Step 1: Add Product from Supplier

1. Create product in WooCommerce
2. Enter the initial product price (e.g., $12.00)
3. **Product Cost field automatically copies** the initial price to track your base cost

### Step 2: Set Your Base Price

As admin, you set a base selling price:
- Cost: $12.00
- Your base price: $20.00
- Your markup: $8.00

### Step 3: Fundraiser Adds Their Markup

Fundraiser logs in and edits the product:
- Sees cost: $12.00 (hidden from public)
- Current price: $20.00
- Changes price to: $25.00
- Their markup: $5.00

### Step 4: Sale Completed

When someone buys:
```
Sale price: $25.00
Your cost: $12.00
Your markup profit: $13.00
Platform fee (5%): $1.25
To campaign: $10.75

You earn: $14.25 total
Campaign earns: $10.75
```

---

## Database Tracking

### Product Profits Table

Every product sale is tracked:

```sql
CREATE TABLE fp_fundraiser_product_profits (
    id bigint(20) UNSIGNED AUTO_INCREMENT,
    order_id bigint(20) UNSIGNED,
    product_id bigint(20) UNSIGNED,
    campaign_id bigint(20) UNSIGNED,
    quantity int(11),
    unit_cost decimal(10,2),      -- Your cost per unit
    unit_price decimal(10,2),     -- Sale price per unit
    unit_markup decimal(10,2),    -- Profit per unit
    total_markup decimal(10,2),   -- Total profit for this line item
    supplier varchar(100),         -- Supplier name
    created_at datetime,
    PRIMARY KEY (id)
);
```

---

## Viewing Profit Reports

### Total Markup Profits

```php
require_once FUNDRAISER_PRO_PATH . 'includes/ProductMarkup.php';
$product_markup = new \FundraiserPro\ProductMarkup();

// All time product profits
$total = $product_markup->get_total_markup();
echo "Total product markup: $" . number_format($total, 2);

// This month
$month_profit = $product_markup->get_total_markup(array(
    'start_date' => date('Y-m-01'),
    'end_date' => date('Y-m-t'),
));

// By supplier
$yoycol_profit = $product_markup->get_total_markup(array(
    'supplier' => 'Yoycol',
));
```

### Direct Database Queries

```sql
-- Total markup by product
SELECT
    product_id,
    SUM(total_markup) as profit,
    SUM(quantity) as units_sold,
    AVG(unit_markup) as avg_profit_per_unit
FROM fp_fundraiser_product_profits
GROUP BY product_id
ORDER BY profit DESC;

-- Profit by supplier
SELECT
    supplier,
    SUM(total_markup) as total_profit,
    COUNT(DISTINCT product_id) as product_count,
    SUM(quantity) as total_units
FROM fp_fundraiser_product_profits
GROUP BY supplier;

-- Total revenue breakdown
SELECT
    'Product Markup' as revenue_source,
    SUM(total_markup) as amount
FROM fp_fundraiser_product_profits
UNION ALL
SELECT
    'Platform Fees' as revenue_source,
    SUM(fee_amount) as amount
FROM fp_fundraiser_platform_fees;
```

---

## Order Details View

In WooCommerce admin, when viewing an order, you'll see a **Product Profit Summary** box:

```
Product Profit Summary
━━━━━━━━━━━━━━━━━━━━━━━
Total Product Markup: $13.00
Platform Fee (5%): $1.25
━━━━━━━━━━━━━━━━━━━━━━━
Total Your Profit: $14.25
```

This shows both revenue streams for that order!

---

## API for Calculations

### Calculate Markup Without Recording

```php
$product_markup = new \FundraiserPro\ProductMarkup();

$markup_info = $product_markup->calculate_markup(
    123,    // Product ID
    25.00   // Sale price (optional, uses product price if omitted)
);

print_r($markup_info);
/*
Array (
    [has_cost] => true
    [cost] => 12.00
    [price] => 25.00
    [markup] => 13.00
    [markup_percent] => 108.3
    [supplier] => Yoycol
)
*/
```

---

## Fundraiser Permissions

### What Fundraisers Can Do:

✅ Edit product prices (add their markup)
✅ See the current selling price
❌ See the product cost (hidden from fundraisers)
❌ See your markup (hidden from fundraisers)

### How to Hide Cost from Fundraisers:

The cost field only shows to administrators. Fundraisers with the "Fundraiser" role can edit prices but won't see costs.

To ensure this, the product cost field checks user capability:

```php
if ( current_user_can( 'manage_options' ) ) {
    // Show cost field
}
```

---

## Pricing Strategy Examples

### Strategy 1: Fixed Margins

Set all products at 50% markup:

```
Cost: $10 → Price: $15 (50% markup)
Cost: $20 → Price: $30 (50% markup)
Cost: $50 → Price: $75 (50% markup)
```

### Strategy 2: Tiered Pricing

Lower percentage on higher-priced items:

```
Cost $0-$20: 100% markup
Cost $20-$50: 60% markup
Cost $50+: 40% markup
```

### Strategy 3: Competitive Pricing

Price based on market, regardless of cost:

```
Standard t-shirt: Always $25 (regardless of $8-$12 cost)
Premium hoodie: Always $45 (regardless of $20-$25 cost)
```

---

## Best Practices

### 1. Transparent About Costs

Tell fundraisers:
- "Product cost is built in"
- "Set your price to include your desired profit"
- "Suggested retail: $X for optimal fundraising"

### 2. Minimum Markup Rules

Set minimums to ensure campaign viability:

```php
// Example: Require at least $5 to campaign per item
add_filter('woocommerce_product_get_price', function($price, $product) {
    $cost = get_post_meta($product->get_id(), '_product_cost', true);
    if ($cost) {
        $minimum_campaign_profit = 5.00;
        $your_markup = $price - $cost;
        $platform_fee = $price * 0.05;
        $to_campaign = $price - $your_markup - $platform_fee;

        if ($to_campaign < $minimum_campaign_profit) {
            // Price too low, adjust
        }
    }
    return $price;
}, 10, 2);
```

### 3. Suggested Pricing

Show fundraisers recommended prices:

```
Cost: $12.00
Suggested Price: $25.00
This gives campaign ~$11 per sale
```

### 4. Volume Discounts

Reduce your markup on high-volume orders:

```php
// Example: Lower markup for orders > 50 units
add_filter('fundraiser_pro_product_markup', function($markup, $quantity) {
    if ($quantity > 50) {
        return $markup * 0.8; // 20% discount
    }
    return $markup;
}, 10, 2);
```

---

## Combining with Platform Fees

Your total revenue per transaction:

| Component | Example Amount | Goes To |
|-----------|---------------|---------|
| Customer Pays | $25.00 | - |
| Your Product Cost | -$12.00 | Supplier |
| **Your Markup Profit** | **$13.00** | **You** |
| Platform Fee (5%) | $1.25 | **You** |
| **Total to You** | **$14.25** | **You** |
| To Campaign | $10.75 | Campaign |

**Note:** Campaign receives $10.75, which is less than the $25 donation, but they didn't have to source/manage the products!

---

## Troubleshooting

### Cost Not Showing in Product Editor

1. Check user has admin capability
2. Verify ProductMarkup class is loaded
3. Check WooCommerce hooks are registered

```php
// Debug
add_action('woocommerce_product_options_pricing', function() {
    echo '<!-- ProductMarkup hooks working -->';
});
```

### Markup Not Being Recorded

1. Check database table exists:
```sql
SHOW TABLES LIKE 'fp_fundraiser_product_profits';
```

2. Verify order completion triggers:
```php
add_action('woocommerce_order_status_completed', function($order_id) {
    error_log("Order $order_id completed");
});
```

3. Check product has cost:
```php
$cost = get_post_meta($product_id, '_product_cost', true);
echo "Cost: $cost";
```

---

## Future Enhancements

Potential additions:
- [ ] Bulk cost import from CSV
- [ ] Automatic price suggestions based on market
- [ ] Profit margin alerts (if too low)
- [ ] Cost history tracking
- [ ] Automated repricing rules

---

## Summary

✅ **Product cost tracking active**
✅ **Automatic cost auto-fill from initial price**
✅ **Automatic markup calculation**
✅ **Profit recording on every sale**
✅ **Two revenue streams: markup + platform fee**
✅ **Complete profit reporting**

### Your Revenue Streams:

1. **Product Markup**: Price - Cost = Your profit on physical goods
2. **Platform Fee**: 5% of every transaction

### Example Monthly Revenue:

```
100 t-shirts sold @ $25 each:
- Product markup: $13/shirt × 100 = $1,300
- Platform fees: $1.25/shirt × 100 = $125
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total monthly revenue: $1,425

Plus any digital donations with 5% fee!
```

---

**Implemented:** December 16, 2025
**Status:** ✅ Production Ready
**Workflow:** Simple auto-copy from initial price (no API integration needed)
