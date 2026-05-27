# Export WooCommerce - Test Cases

## Test Case Categories

### 1. Product Export Tests

#### TC-PROD-001: Export Simple Product
**Priority**: High
**Preconditions**: 
- FA has at least one simple product in stock_master
- Product has price in prices table (sales_type_id = 1)
- WooCommerce connection configured

**Steps**:
1. Navigate to Banking and General Ledger > Export WOO
2. Click "Create Table" to initialize woo table
3. Click "All Products Export"
4. Verify product appears in WooCommerce

**Expected Result**: 
- Product created in WooCommerce
- woo_id populated in woo table
- woo_last_update timestamp set

**Test Data**: Simple product with SKU "TEST-SIMPLE-001"

---

#### TC-PROD-002: Export Variable Product
**Priority**: High
**Preconditions**:
- FA has variable product with variations in woo_prod_variable_master
- Variations defined in woo_prod_variable_sku_* tables

**Steps**:
1. Export variable product from FA
2. Verify parent product created in WooCommerce
3. Verify variations created under parent

**Expected Result**:
- Variable product with variations in WooCommerce
- Each variation has correct SKU, price, attributes

**Test Data**: Variable product "TSHIRT" with sizes S, M, L

---

#### TC-PROD-003: Update Existing Product
**Priority**: High
**Preconditions**:
- Product already exported (has woo_id)
- Product modified in FA (price change, description update)

**Steps**:
1. Modify product price in FA
2. Trigger update export
3. Verify changes reflected in WooCommerce

**Expected Result**:
- PUT request sent to WooCommerce
- Product updated (not duplicated)
- woo_last_update timestamp updated

---

#### TC-PROD-004: Export Product with Special Price
**Priority**: Medium
**Preconditions**:
- Product has entry in specials table
- Sale dates defined

**Steps**:
1. Add special price for product in FA
2. Export product
3. Verify sale price and dates in WooCommerce

**Expected Result**:
- regular_price = FA price
- sale_price = FA special price
- date_on_sale_from/to set correctly

---

#### TC-PROD-005: QOH Synchronization
**Priority**: High
**Preconditions**:
- ksf_qoh module installed OR QOH table populated
- Product has stock on hand

**Steps**:
1. Update QOH in FA
2. Run QOH update export
3. Verify inventory in WooCommerce

**Expected Result**:
- instock field updated in WooCommerce
- Manages stock correctly in WC

---

### 2. Category Tests

#### TC-CAT-001: Export Categories
**Priority**: High
**Preconditions**:
- FA has product categories in stock_category
- Category mapping exists in woo_categories_xref

**Steps**:
1. Export categories
2. Verify categories created in WooCommerce
3. Verify products assigned to correct categories

**Expected Result**:
- Categories visible in WooCommerce
- woo_category_id populated in woo table

---

#### TC-CAT-002: Category Mapping
**Priority**: Medium
**Preconditions**:
- FA category ID 5 maps to WC category ID 12 in woo_categories_xref

**Steps**:
1. Export product in FA category 5
2. Verify product appears in WC category 12

**Expected Result**:
- Correct category mapping applied
- No orphaned products

---

### 3. Order Import Tests

#### TC-ORD-001: Import WooCommerce Order
**Priority**: Medium
**Preconditions**:
- Order exists in WooCommerce
- Customer data complete

**Steps**:
1. Trigger order import
2. Verify order created in FA
3. Verify customer created/updated
4. Verify line items, taxes, shipping

**Expected Result**:
- Sales order/invoice in FA
- Correct totals and taxes
- Order status tracked

---

#### TC-ORD-002: Order with Coupons
**Priority**: Low
**Preconditions**:
- WooCommerce order has coupon applied

**Steps**:
1. Import order with coupon
2. Verify coupon data captured
3. Verify discount applied correctly

**Expected Result**:
- Coupon line item in FA order
- Correct discount amount

---

### 4. Customer Tests

#### TC-CUST-001: Export Customer to WooCommerce
**Priority**: Medium
**Preconditions**:
- Customer exists in FA

**Steps**:
1. Export customer
2. Verify customer in WooCommerce
3. Verify billing/shipping addresses

**Expected Result**:
- Customer created in WooCommerce
- Addresses correctly mapped

---

#### TC-CUST-002: Import Customer from WooCommerce
**Priority**: Medium
**Preconditions**:
- Customer exists in WooCommerce only

**Steps**:
1. Import customer
2. Verify customer in FA
3. Verify addresses

**Expected Result**:
- Customer created in FA
- Data matches WooCommerce

---

### 5. API Integration Tests

#### TC-API-001: REST API Connection
**Priority**: High
**Preconditions**:
- Valid consumer key and secret
- WooCommerce URL accessible

**Steps**:
1. Configure API credentials
2. Test connection
3. Verify authentication

**Expected Result**:
- Successful API response
- No authentication errors

---

#### TC-API-002: API Error Handling
**Priority**: Medium
**Preconditions**:
- Invalid credentials OR WooCommerce offline

**Steps**:
1. Attempt API call with bad credentials
2. Verify error handling
3. Check error logging

**Expected Result**:
- Graceful error message
- Error logged
- No crash/white screen

---

#### TC-API-003: Rate Limiting
**Priority**: Low
**Preconditions**:
- Large batch of products to export

**Steps**:
1. Export 100+ products
2. Monitor API calls
3. Verify no rate limit errors

**Expected Result**:
- Batch processes successfully
- Delay between calls if needed

---

### 6. FrontAccounting Integration Tests

#### TC-FA-001: Extension Installation
**Priority**: High
**Preconditions**:
- FA admin access

**Steps**:
1. Go to Setup > Install/Activate Extensions
2. Activate EXPORT_WOO
3. Set access for role
4. Verify menu appears

**Expected Result**:
- Module activated
- Menu item visible
- Access control working

---

#### TC-FA-002: Hook Integration
**Priority**: Low
**Preconditions**:
- Module activated

**Steps**:
1. Create sales order in FA
2. Verify hook is called (db_postwrite)
3. Check if QOH update triggered

**Expected Result**:
- Hook executes without error
- Appropriate action taken

---

### 7. Regression Tests

#### TC-REG-001: Duplicate Prevention
**Steps**:
1. Export product
2. Export again without changes
3. Verify no duplicate in WooCommerce

**Expected Result**:
- Update (PUT) instead of Create (POST)
- Single product in WooCommerce

---

#### TC-REG-002: Missing Data Handling
**Preconditions**:
- Product missing price or QOH

**Steps**:
1. Export product with missing data
2. Verify handling

**Expected Result**:
- Product still exports with defaults
- Warning logged
- Missing data report generated

---

## Test Environment Setup

### Test Data Required
1. Simple products (5-10 items)
2. Variable products (2-3 with variations)
3. Categories (3-5)
4. Customers (5-10)
5. Test orders (3-5)
6. Specials/sale prices
7. QOH data

### Test WooCommerce Instance
- Separate test store (not production)
- REST API enabled
- SSL configured (or SSL verify disabled for devel)

### Test FrontAccounting Instance
- Clean FA installation
- Test company/database
- Module dependencies installed

## Automated Testing (Future)
- PHPUnit tests for data transformation
- Mock WooCommerce API for unit tests
- Integration test suite
