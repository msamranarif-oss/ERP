<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to add business rule validation constraints
     */
    public function up(): void
    {
        // Add check constraints with raw DB statements safely
        $this->addCheckConstraint('sales', 'chk_sales_sub_pos', 'subtotal >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_disc_pos', 'discount_amount >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_tax_pos', 'tax_amount >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_ship_pos', 'shipping_amount >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_tot_pos', 'total >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_paid_pos', 'paid_amount >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_chg_pos', 'change_amount >= 0');
        $this->addCheckConstraint('sales', 'chk_sales_bal_pos', 'balance_due >= 0');
        
        $this->addCheckConstraint('sales', 'chk_sales_disc_sub', 'discount_amount <= subtotal');
        $this->addCheckConstraint('sales', 'chk_sales_paid_tot', 'paid_amount <= total');
        
        $this->addCheckConstraint('sale_items', 'chk_si_qty_pos', 'quantity > 0');
        $this->addCheckConstraint('sale_items', 'chk_si_up_pos', 'unit_price >= 0');
        $this->addCheckConstraint('sale_items', 'chk_si_disc_pos', 'discount >= 0');
        $this->addCheckConstraint('sale_items', 'chk_si_tax_pos', 'tax >= 0');
        $this->addCheckConstraint('sale_items', 'chk_si_taxrt_rng', 'tax_rate >= 0 AND tax_rate <= 100');
        $this->addCheckConstraint('sale_items', 'chk_si_tot_pos', 'total >= 0');
        $this->addCheckConstraint('sale_items', 'chk_si_cost_pos', 'cost_price >= 0');

        if (Schema::hasColumn('products', 'cost_price')) $this->addCheckConstraint('products', 'chk_prod_cost_pos', 'cost_price >= 0');
        if (Schema::hasColumn('products', 'selling_price')) $this->addCheckConstraint('products', 'chk_prod_sell_pos', 'selling_price >= 0');
        if (Schema::hasColumn('products', 'min_price')) $this->addCheckConstraint('products', 'chk_prod_min_pos', 'min_price >= 0');
        if (Schema::hasColumn('products', 'wholesale_price')) $this->addCheckConstraint('products', 'chk_prod_whl_pos', 'wholesale_price >= 0');
        if (Schema::hasColumn('products', 'max_price')) $this->addCheckConstraint('products', 'chk_prod_max_pos', 'max_price >= 0');
        if (Schema::hasColumn('products', 'reorder_level')) $this->addCheckConstraint('products', 'chk_prod_rl_pos', 'reorder_level >= 0');
        if (Schema::hasColumn('products', 'reorder_quantity')) $this->addCheckConstraint('products', 'chk_prod_rq_pos', 'reorder_quantity >= 0');
        if (Schema::hasColumn('products', 'tax_rate')) $this->addCheckConstraint('products', 'chk_prod_taxrt_rng', 'tax_rate >= 0 AND tax_rate <= 100');
        if (Schema::hasColumn('products', 'weight')) $this->addCheckConstraint('products', 'chk_prod_wt_pos', 'weight >= 0');
        if (Schema::hasColumn('products', 'length')) $this->addCheckConstraint('products', 'chk_prod_len_pos', 'length >= 0');
        if (Schema::hasColumn('products', 'width')) $this->addCheckConstraint('products', 'chk_prod_wid_pos', 'width >= 0');
        if (Schema::hasColumn('products', 'height')) $this->addCheckConstraint('products', 'chk_prod_ht_pos', 'height >= 0');

        $this->addCheckConstraint('customers', 'chk_cust_cl_pos', 'credit_limit >= 0');
        $this->addCheckConstraint('customers', 'chk_cust_bal_pos', 'balance >= 0');
        $this->addCheckConstraint('customers', 'chk_cust_pts_pos', 'points >= 0');

        $this->addCheckConstraint('stock_levels', 'chk_sl_qty_pos', 'quantity >= 0');
        if (Schema::hasColumn('stock_levels', 'reserved_quantity')) {
            $this->addCheckConstraint('stock_levels', 'chk_sl_rqty_pos', 'reserved_quantity >= 0');
            $this->addCheckConstraint('stock_levels', 'chk_sl_rqty_qty', 'reserved_quantity <= quantity');
        }
        if (Schema::hasColumn('stock_levels', 'min_stock_level')) {
            $this->addCheckConstraint('stock_levels', 'chk_sl_msl_pos', 'min_stock_level >= 0');
        }

        $this->addCheckConstraint('payment_methods', 'chk_pm_so_pos', 'sort_order >= 0');

        if (Schema::hasTable('register_sessions')) {
            $this->addCheckConstraint('register_sessions', 'chk_rs_ob_pos', 'opening_balance >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_cs_pos', 'cash_sales >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_cds_pos', 'card_sales >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_os_pos', 'other_sales >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_ref_pos', 'refunds >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_ci_pos', 'cash_in >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_co_pos', 'cash_out >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_eb_pos', 'expected_balance >= 0');
            $this->addCheckConstraint('register_sessions', 'chk_rs_cb_pos', 'closing_balance >= 0');
        }

        // Add application-level validation triggers for complex business rules
        $this->createBusinessValidationTriggers();
    }

    private function addCheckConstraint(string $table, string $name, string $expression): void
    {
        if (DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            try {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            } catch (\Exception $e) {
                // Ignore errors if check constraints are not fully supported or already exist
            }
        }
    }

    /**
     * Create database triggers for complex business validation
     */
    private function createBusinessValidationTriggers(): void
    {
        // Trigger to validate sale item totals
        DB::unprepared("
            CREATE TRIGGER validate_sale_item_total 
            BEFORE INSERT ON sale_items
            FOR EACH ROW
            BEGIN
                DECLARE calculated_total DECIMAL(15,2);
                SET calculated_total = NEW.quantity * NEW.unit_price - NEW.discount + NEW.tax;
                IF ABS(calculated_total - NEW.total) > 0.01 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sale item total does not match calculated value';
                END IF;
            END
        ");

        // Trigger to validate sale totals
        DB::unprepared("
            CREATE TRIGGER validate_sale_total 
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                DECLARE calculated_total DECIMAL(15,2);
                DECLARE item_total DECIMAL(15,2) DEFAULT 0;
                
                SELECT COALESCE(SUM(total), 0) INTO item_total 
                FROM sale_items 
                WHERE sale_id = NEW.id;
                
                SET calculated_total = item_total + NEW.tax_amount + NEW.shipping_amount - NEW.discount_amount;
                
                IF ABS(calculated_total - NEW.total) > 0.01 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sale total does not match calculated value';
                END IF;
            END
        ");

        // Trigger to prevent negative stock
        DB::unprepared("
            CREATE TRIGGER prevent_negative_stock 
            BEFORE UPDATE ON stock_levels
            FOR EACH ROW
            BEGIN
                IF NEW.quantity < 0 AND (SELECT allow_negative_stock FROM products WHERE id = NEW.product_id) = 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot reduce stock below zero for this product';
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared("DROP TRIGGER IF EXISTS validate_sale_item_total");
        DB::unprepared("DROP TRIGGER IF EXISTS validate_sale_total");
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_negative_stock");
    }
};