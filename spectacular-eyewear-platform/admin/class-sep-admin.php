<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
        add_action( 'wp_ajax_sep_admin_save_product', array( __CLASS__, 'ajax_save_product' ) );
        add_action( 'wp_ajax_sep_admin_delete_product', array( __CLASS__, 'ajax_delete_product' ) );
        add_action( 'wp_ajax_sep_admin_save_contestant', array( __CLASS__, 'ajax_save_contestant' ) );
        add_action( 'wp_ajax_sep_admin_delete_contestant', array( __CLASS__, 'ajax_delete_contestant' ) );
        add_action( 'wp_ajax_sep_admin_reset_votes', array( __CLASS__, 'ajax_reset_votes' ) );
        add_action( 'wp_ajax_sep_admin_save_affiliate', array( __CLASS__, 'ajax_save_affiliate' ) );
        add_action( 'wp_ajax_sep_admin_delete_affiliate', array( __CLASS__, 'ajax_delete_affiliate' ) );
        add_action( 'wp_ajax_sep_admin_process_withdrawal', array( __CLASS__, 'ajax_process_withdrawal' ) );
        add_action( 'wp_ajax_sep_admin_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_sep_admin_upload_image', array( __CLASS__, 'ajax_upload_image' ) );
    }

    public static function register_menus() {
        add_menu_page(
            __( 'Spectacular', 'spectacular' ),
            __( 'Spectacular', 'spectacular' ),
            'manage_options',
            'sep-dashboard',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-visibility',
            30
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Dashboard', 'spectacular' ),
            __( 'Dashboard', 'spectacular' ),
            'manage_options',
            'sep-dashboard',
            array( __CLASS__, 'page_dashboard' )
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Competition', 'spectacular' ),
            __( 'Competition', 'spectacular' ),
            'manage_options',
            'sep-competition',
            array( __CLASS__, 'page_competition' )
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Affiliates', 'spectacular' ),
            __( 'Affiliates', 'spectacular' ),
            'manage_options',
            'sep-affiliates',
            array( __CLASS__, 'page_affiliates' )
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Withdrawals', 'spectacular' ),
            __( 'Withdrawals', 'spectacular' ),
            'manage_options',
            'sep-withdrawals',
            array( __CLASS__, 'page_withdrawals' )
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Inventory', 'spectacular' ),
            __( 'Inventory', 'spectacular' ),
            'manage_options',
            'sep-inventory',
            array( __CLASS__, 'page_inventory' )
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Reports', 'spectacular' ),
            __( 'Reports', 'spectacular' ),
            'manage_options',
            'sep-reports',
            array( __CLASS__, 'page_reports' )
        );

        add_submenu_page(
            'sep-dashboard',
            __( 'Settings', 'spectacular' ),
            __( 'Settings', 'spectacular' ),
            'manage_options',
            'sep-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'sep-' ) === false ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'sep-admin',
            SEP_PLUGIN_URL . 'admin/css/sep-admin.css',
            array(),
            SEP_VERSION
        );
        wp_enqueue_script(
            'sep-admin',
            SEP_PLUGIN_URL . 'admin/js/sep-admin.js',
            array( 'jquery' ),
            SEP_VERSION,
            true
        );
        wp_localize_script( 'sep-admin', 'sepAdmin', array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'sep_admin_nonce' ),
        ) );
    }

    public static function handle_admin_actions() {
        // Handle CSV exports, bulk actions, etc.
        if ( ! isset( $_GET['sep_action'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_GET['sep_action'] ) );

        if ( 'export_affiliates' === $action ) {
            check_admin_referer( 'sep_export_affiliates' );
            self::export_affiliates_csv();
        }

        if ( 'export_activity' === $action ) {
            check_admin_referer( 'sep_export_activity' );
            self::export_activity_csv();
        }
    }

    // ─── Dashboard ──────────────────────────────────────────────

    public static function page_dashboard() {
        $total_products    = SEP_Products::get_product_count( 'active' );
        $total_contestants = SEP_Competition::get_contestant_count();
        $total_votes       = SEP_Competition::get_total_votes();
        $total_affiliates  = SEP_Affiliate::get_affiliate_count();
        $total_earnings    = SEP_Affiliate::get_total_earnings();
        $pending_wd        = SEP_Affiliate::get_pending_withdrawals_count();
        $pending_wd_total  = SEP_Affiliate::get_pending_withdrawals_total();
        $currency          = get_option( 'sep_currency', 'UGX' );

        global $wpdb;
        $recent_activity = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sep_activity_log ORDER BY created_at DESC LIMIT 15"
        );

        $recent_votes = $wpdb->get_results(
            "SELECT v.*, c.name as contestant_name
             FROM {$wpdb->prefix}sep_votes v
             LEFT JOIN {$wpdb->prefix}sep_contestants c ON v.contestant_id = c.id
             ORDER BY v.voted_at DESC LIMIT 10"
        );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Spectacular Dashboard</h1>

            <div class="sep-stats-grid">
                <div class="sep-stat-card">
                    <div class="sep-stat-icon dashicons dashicons-cart"></div>
                    <div class="sep-stat-content">
                        <span class="sep-stat-value"><?php echo esc_html( number_format( $total_products ) ); ?></span>
                        <span class="sep-stat-label">Products</span>
                    </div>
                </div>
                <div class="sep-stat-card">
                    <div class="sep-stat-icon dashicons dashicons-groups"></div>
                    <div class="sep-stat-content">
                        <span class="sep-stat-value"><?php echo esc_html( number_format( $total_contestants ) ); ?></span>
                        <span class="sep-stat-label">Contestants</span>
                    </div>
                </div>
                <div class="sep-stat-card">
                    <div class="sep-stat-icon dashicons dashicons-heart"></div>
                    <div class="sep-stat-content">
                        <span class="sep-stat-value"><?php echo esc_html( number_format( $total_votes ) ); ?></span>
                        <span class="sep-stat-label">Total Votes</span>
                    </div>
                </div>
                <div class="sep-stat-card">
                    <div class="sep-stat-icon dashicons dashicons-money-alt"></div>
                    <div class="sep-stat-content">
                        <span class="sep-stat-value"><?php echo esc_html( $currency . ' ' . number_format( $total_earnings ) ); ?></span>
                        <span class="sep-stat-label">Affiliate Earnings</span>
                    </div>
                </div>
                <div class="sep-stat-card">
                    <div class="sep-stat-icon dashicons dashicons-networking"></div>
                    <div class="sep-stat-content">
                        <span class="sep-stat-value"><?php echo esc_html( number_format( $total_affiliates ) ); ?></span>
                        <span class="sep-stat-label">Affiliates</span>
                    </div>
                </div>
                <div class="sep-stat-card sep-stat-card-warn">
                    <div class="sep-stat-icon dashicons dashicons-warning"></div>
                    <div class="sep-stat-content">
                        <span class="sep-stat-value"><?php echo esc_html( $pending_wd ); ?></span>
                        <span class="sep-stat-label">Pending Withdrawals (<?php echo esc_html( $currency . ' ' . number_format( $pending_wd_total ) ); ?>)</span>
                    </div>
                </div>
            </div>

            <div class="sep-dashboard-grid">
                <!-- Recent Activity -->
                <div class="sep-card">
                    <div class="sep-card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div class="sep-card-body">
                        <?php if ( empty( $recent_activity ) ) : ?>
                            <p class="sep-empty">No activity yet.</p>
                        <?php else : ?>
                            <ul class="sep-activity-list">
                                <?php foreach ( $recent_activity as $act ) : ?>
                                    <li class="sep-activity-item">
                                        <span class="sep-activity-type sep-type-<?php echo esc_attr( $act->type ); ?>"><?php echo esc_html( $act->type ); ?></span>
                                        <span class="sep-activity-msg"><?php echo esc_html( $act->message ); ?></span>
                                        <span class="sep-activity-time"><?php echo esc_html( human_time_diff( strtotime( $act->created_at ), current_time( 'timestamp' ) ) ); ?> ago</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Votes -->
                <div class="sep-card">
                    <div class="sep-card-header">
                        <h2>Recent Votes</h2>
                    </div>
                    <div class="sep-card-body">
                        <?php if ( empty( $recent_votes ) ) : ?>
                            <p class="sep-empty">No votes yet.</p>
                        <?php else : ?>
                            <table class="sep-table">
                                <thead>
                                    <tr>
                                        <th>Contestant</th>
                                        <th>Voter IP</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recent_votes as $v ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $v->contestant_name ); ?></td>
                                            <td><code><?php echo esc_html( $v->voter_ip ); ?></code></td>
                                            <td><?php echo esc_html( human_time_diff( strtotime( $v->voted_at ), current_time( 'timestamp' ) ) ); ?> ago</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Competition ────────────────────────────────────────────

    public static function page_competition() {
        $contestants = SEP_Competition::get_contestants( array( 'status' => '', 'limit' => 100 ) );
        $total_votes = SEP_Competition::get_total_votes();
        $status      = get_option( 'sep_competition_status', 'open' );
        $title       = get_option( 'sep_competition_title', 'Spectacular Eyewear Competition' );
        $countdown   = get_option( 'sep_competition_countdown', '' );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Competition Control</h1>

            <!-- Competition Settings -->
            <div class="sep-card sep-mb-20">
                <div class="sep-card-header">
                    <h2>Competition Settings</h2>
                </div>
                <div class="sep-card-body">
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Competition Title</label>
                            <input type="text" id="sepCompTitle" class="sep-input" value="<?php echo esc_attr( $title ); ?>" />
                        </div>
                        <div class="sep-form-group">
                            <label>Voting Status</label>
                            <select id="sepCompStatus" class="sep-select">
                                <option value="open" <?php selected( $status, 'open' ); ?>>Open</option>
                                <option value="closed" <?php selected( $status, 'closed' ); ?>>Closed</option>
                            </select>
                        </div>
                        <div class="sep-form-group">
                            <label>Countdown End (optional)</label>
                            <input type="datetime-local" id="sepCompCountdown" class="sep-input" value="<?php echo esc_attr( $countdown ); ?>" />
                        </div>
                    </div>
                    <div class="sep-form-actions">
                        <button class="button button-primary" id="sepSaveCompSettings">Save Settings</button>
                        <button class="button sep-btn-danger" id="sepResetAllVotes">Reset All Votes</button>
                    </div>
                </div>
            </div>

            <!-- Add Contestant -->
            <div class="sep-card sep-mb-20">
                <div class="sep-card-header">
                    <h2>Add Contestant</h2>
                </div>
                <div class="sep-card-body">
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Name</label>
                            <input type="text" id="sepContestantName" class="sep-input" placeholder="Contestant name" />
                        </div>
                        <div class="sep-form-group">
                            <label>Bio</label>
                            <input type="text" id="sepContestantBio" class="sep-input" placeholder="Short bio (optional)" />
                        </div>
                        <div class="sep-form-group">
                            <label>Image URL</label>
                            <div class="sep-input-with-btn">
                                <input type="text" id="sepContestantImage" class="sep-input" placeholder="Image URL" />
                                <button class="button sep-upload-btn" data-target="sepContestantImage">Upload</button>
                            </div>
                        </div>
                    </div>
                    <button class="button button-primary" id="sepAddContestant">Add Contestant</button>
                </div>
            </div>

            <!-- Contestants List -->
            <div class="sep-card">
                <div class="sep-card-header">
                    <h2>Contestants (<?php echo esc_html( count( $contestants ) ); ?>)</h2>
                    <span class="sep-card-meta">Total Votes: <?php echo esc_html( number_format( $total_votes ) ); ?></span>
                </div>
                <div class="sep-card-body">
                    <?php if ( empty( $contestants ) ) : ?>
                        <p class="sep-empty">No contestants yet. Add one above.</p>
                    <?php else : ?>
                        <table class="sep-table" id="sepContestantsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Votes</th>
                                    <th>%</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $contestants as $rank => $c ) :
                                    $pct = $total_votes > 0 ? round( ( $c->votes / $total_votes ) * 100, 1 ) : 0;
                                    ?>
                                    <tr data-id="<?php echo esc_attr( $c->id ); ?>">
                                        <td><?php echo esc_html( $rank + 1 ); ?></td>
                                        <td>
                                            <?php if ( $c->image_url ) : ?>
                                                <img src="<?php echo esc_url( $c->image_url ); ?>" class="sep-thumb" alt="" />
                                            <?php else : ?>
                                                <span class="sep-no-img">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo esc_html( $c->name ); ?></strong></td>
                                        <td><?php echo esc_html( number_format( $c->votes ) ); ?></td>
                                        <td><?php echo esc_html( $pct ); ?>%</td>
                                        <td><span class="sep-status sep-status-<?php echo esc_attr( $c->status ); ?>"><?php echo esc_html( $c->status ); ?></span></td>
                                        <td>
                                            <button class="button button-small sep-reset-contestant-votes" data-id="<?php echo esc_attr( $c->id ); ?>">Reset Votes</button>
                                            <button class="button button-small sep-btn-danger sep-delete-contestant" data-id="<?php echo esc_attr( $c->id ); ?>">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Affiliates ─────────────────────────────────────────────

    public static function page_affiliates() {
        $affiliates = SEP_Affiliate::get_all_affiliates( array( 'limit' => 100 ) );
        $currency   = get_option( 'sep_currency', 'UGX' );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Affiliates</h1>

            <!-- Add Affiliate -->
            <div class="sep-card sep-mb-20">
                <div class="sep-card-header">
                    <h2>Add Affiliate</h2>
                </div>
                <div class="sep-card-body">
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Name</label>
                            <input type="text" id="sepAffName" class="sep-input" placeholder="Full name" />
                        </div>
                        <div class="sep-form-group">
                            <label>Email</label>
                            <input type="email" id="sepAffEmail" class="sep-input" placeholder="Email address" />
                        </div>
                        <div class="sep-form-group">
                            <label>Phone</label>
                            <input type="text" id="sepAffPhone" class="sep-input" placeholder="Phone number" />
                        </div>
                        <div class="sep-form-group">
                            <label>Referral Code (auto-generated if empty)</label>
                            <input type="text" id="sepAffCode" class="sep-input" placeholder="e.g. ABC123" />
                        </div>
                    </div>
                    <button class="button button-primary" id="sepAddAffiliate">Add Affiliate</button>
                </div>
            </div>

            <!-- Affiliates Table -->
            <div class="sep-card">
                <div class="sep-card-header">
                    <h2>All Affiliates (<?php echo esc_html( count( $affiliates ) ); ?>)</h2>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sep-affiliates&sep_action=export_affiliates' ), 'sep_export_affiliates' ) ); ?>" class="button">Export CSV</a>
                </div>
                <div class="sep-card-body">
                    <?php if ( empty( $affiliates ) ) : ?>
                        <p class="sep-empty">No affiliates yet.</p>
                    <?php else : ?>
                        <table class="sep-table" id="sepAffiliatesTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Code</th>
                                    <th>Clicks</th>
                                    <th>Sign-ups</th>
                                    <th>Earnings</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $affiliates as $a ) : ?>
                                    <tr data-id="<?php echo esc_attr( $a->id ); ?>">
                                        <td><strong><?php echo esc_html( $a->name ); ?></strong></td>
                                        <td><?php echo esc_html( $a->email ); ?></td>
                                        <td><code><?php echo esc_html( $a->referral_code ); ?></code></td>
                                        <td><?php echo esc_html( number_format( $a->total_clicks ) ); ?></td>
                                        <td><?php echo esc_html( number_format( $a->total_signups ) ); ?></td>
                                        <td><?php echo esc_html( $currency . ' ' . number_format( $a->total_earnings ) ); ?></td>
                                        <td><?php echo esc_html( $currency . ' ' . number_format( $a->pending_balance ) ); ?></td>
                                        <td><span class="sep-status sep-status-<?php echo esc_attr( $a->status ); ?>"><?php echo esc_html( $a->status ); ?></span></td>
                                        <td>
                                            <button class="button button-small sep-btn-danger sep-delete-affiliate" data-id="<?php echo esc_attr( $a->id ); ?>">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Withdrawals ────────────────────────────────────────────

    public static function page_withdrawals() {
        $pending  = SEP_Affiliate::get_withdrawals( array( 'status' => 'pending' ) );
        $history  = SEP_Affiliate::get_withdrawals( array( 'limit' => 50 ) );
        $currency = get_option( 'sep_currency', 'UGX' );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Withdrawal Requests</h1>

            <!-- Pending -->
            <div class="sep-card sep-mb-20">
                <div class="sep-card-header">
                    <h2>Pending Requests (<?php echo esc_html( count( $pending ) ); ?>)</h2>
                </div>
                <div class="sep-card-body">
                    <?php if ( empty( $pending ) ) : ?>
                        <p class="sep-empty">No pending withdrawal requests.</p>
                    <?php else : ?>
                        <table class="sep-table" id="sepPendingWithdrawals">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Affiliate</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Account</th>
                                    <th>Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $pending as $w ) : ?>
                                    <tr data-id="<?php echo esc_attr( $w->id ); ?>">
                                        <td>#<?php echo esc_html( $w->id ); ?></td>
                                        <td><?php echo esc_html( $w->affiliate_name ); ?></td>
                                        <td><strong><?php echo esc_html( $currency . ' ' . number_format( $w->amount ) ); ?></strong></td>
                                        <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $w->method ) ) ); ?></td>
                                        <td><?php echo esc_html( $w->account_info ); ?></td>
                                        <td><?php echo esc_html( human_time_diff( strtotime( $w->requested_at ), current_time( 'timestamp' ) ) ); ?> ago</td>
                                        <td>
                                            <button class="button button-primary button-small sep-approve-wd" data-id="<?php echo esc_attr( $w->id ); ?>">Approve</button>
                                            <button class="button button-small sep-btn-danger sep-reject-wd" data-id="<?php echo esc_attr( $w->id ); ?>">Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- History -->
            <div class="sep-card">
                <div class="sep-card-header">
                    <h2>All Withdrawals</h2>
                </div>
                <div class="sep-card-body">
                    <?php if ( empty( $history ) ) : ?>
                        <p class="sep-empty">No withdrawal history.</p>
                    <?php else : ?>
                        <table class="sep-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Affiliate</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                    <th>Processed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $history as $w ) : ?>
                                    <tr>
                                        <td>#<?php echo esc_html( $w->id ); ?></td>
                                        <td><?php echo esc_html( $w->affiliate_name ); ?></td>
                                        <td><?php echo esc_html( $currency . ' ' . number_format( $w->amount ) ); ?></td>
                                        <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $w->method ) ) ); ?></td>
                                        <td><span class="sep-status sep-status-<?php echo esc_attr( $w->status ); ?>"><?php echo esc_html( $w->status ); ?></span></td>
                                        <td><?php echo esc_html( $w->requested_at ); ?></td>
                                        <td><?php echo $w->processed_at ? esc_html( $w->processed_at ) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Inventory ──────────────────────────────────────────────

    public static function page_inventory() {
        $products = SEP_Products::get_products( array( 'status' => '', 'limit' => 200 ) );
        $currency = get_option( 'sep_currency', 'UGX' );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Product Inventory</h1>

            <!-- Add Product -->
            <div class="sep-card sep-mb-20">
                <div class="sep-card-header">
                    <h2>Add Product</h2>
                </div>
                <div class="sep-card-body">
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Product Name</label>
                            <input type="text" id="sepProdName" class="sep-input" placeholder="Product name" />
                        </div>
                        <div class="sep-form-group">
                            <label>Brand</label>
                            <input type="text" id="sepProdBrand" class="sep-input" placeholder="Brand name" />
                        </div>
                        <div class="sep-form-group">
                            <label>Price (<?php echo esc_html( $currency ); ?>)</label>
                            <input type="number" id="sepProdPrice" class="sep-input" placeholder="0" />
                        </div>
                    </div>
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Category</label>
                            <input type="text" id="sepProdCategory" class="sep-input" placeholder="e.g. Sunglasses" />
                        </div>
                        <div class="sep-form-group">
                            <label>Style</label>
                            <input type="text" id="sepProdStyle" class="sep-input" placeholder="e.g. Round" />
                        </div>
                        <div class="sep-form-group">
                            <label>Size</label>
                            <input type="text" id="sepProdSize" class="sep-input" placeholder="e.g. Medium" />
                        </div>
                        <div class="sep-form-group">
                            <label>Stock</label>
                            <input type="number" id="sepProdStock" class="sep-input" placeholder="0" />
                        </div>
                    </div>
                    <div class="sep-form-row">
                        <div class="sep-form-group sep-form-group-wide">
                            <label>Description</label>
                            <textarea id="sepProdDesc" class="sep-input sep-textarea" placeholder="Product description"></textarea>
                        </div>
                    </div>
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Main Image URL</label>
                            <div class="sep-input-with-btn">
                                <input type="text" id="sepProdImage" class="sep-input" placeholder="Image URL" />
                                <button class="button sep-upload-btn" data-target="sepProdImage">Upload</button>
                            </div>
                        </div>
                        <div class="sep-form-group">
                            <label>Featured</label>
                            <select id="sepProdFeatured" class="sep-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                    </div>
                    <button class="button button-primary" id="sepAddProduct">Add Product</button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="sep-card">
                <div class="sep-card-header">
                    <h2>All Products (<?php echo esc_html( count( $products ) ); ?>)</h2>
                </div>
                <div class="sep-card-body">
                    <?php if ( empty( $products ) ) : ?>
                        <p class="sep-empty">No products yet.</p>
                    <?php else : ?>
                        <table class="sep-table" id="sepProductsTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Brand</th>
                                    <th>Price</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Featured</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $products as $p ) : ?>
                                    <tr data-id="<?php echo esc_attr( $p->id ); ?>">
                                        <td>
                                            <?php if ( $p->image_url ) : ?>
                                                <img src="<?php echo esc_url( $p->image_url ); ?>" class="sep-thumb" alt="" />
                                            <?php else : ?>
                                                <span class="sep-no-img">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo esc_html( $p->name ); ?></strong></td>
                                        <td><?php echo esc_html( $p->brand ); ?></td>
                                        <td><?php echo esc_html( $currency . ' ' . number_format( $p->price ) ); ?></td>
                                        <td><?php echo esc_html( $p->category ); ?></td>
                                        <td><?php echo esc_html( $p->stock ); ?></td>
                                        <td><?php echo $p->featured ? '⭐' : '—'; ?></td>
                                        <td><span class="sep-status sep-status-<?php echo esc_attr( $p->status ); ?>"><?php echo esc_html( $p->status ); ?></span></td>
                                        <td>
                                            <button class="button button-small sep-btn-danger sep-delete-product" data-id="<?php echo esc_attr( $p->id ); ?>">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Reports ────────────────────────────────────────────────

    public static function page_reports() {
        global $wpdb;
        $currency = get_option( 'sep_currency', 'UGX' );

        // Monthly vote stats
        $monthly_votes = $wpdb->get_results(
            "SELECT DATE_FORMAT(voted_at, '%Y-%m') as month, COUNT(*) as total
             FROM {$wpdb->prefix}sep_votes
             GROUP BY month ORDER BY month DESC LIMIT 12"
        );

        // Monthly earnings
        $monthly_earnings = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total
             FROM {$wpdb->prefix}sep_affiliate_conversions
             GROUP BY month ORDER BY month DESC LIMIT 12"
        );

        // Top affiliates
        $top_affiliates = $wpdb->get_results(
            "SELECT name, total_earnings, total_clicks, total_signups
             FROM {$wpdb->prefix}sep_affiliates
             ORDER BY total_earnings DESC LIMIT 10"
        );

        // Recent activity log
        $activity_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sep_activity_log" );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Reports</h1>

            <div class="sep-report-grid">
                <!-- Monthly Votes -->
                <div class="sep-card">
                    <div class="sep-card-header">
                        <h2>Monthly Votes</h2>
                    </div>
                    <div class="sep-card-body">
                        <?php if ( empty( $monthly_votes ) ) : ?>
                            <p class="sep-empty">No vote data yet.</p>
                        <?php else : ?>
                            <table class="sep-table">
                                <thead><tr><th>Month</th><th>Votes</th></tr></thead>
                                <tbody>
                                    <?php foreach ( $monthly_votes as $mv ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $mv->month ); ?></td>
                                            <td><?php echo esc_html( number_format( $mv->total ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Earnings -->
                <div class="sep-card">
                    <div class="sep-card-header">
                        <h2>Monthly Affiliate Earnings</h2>
                    </div>
                    <div class="sep-card-body">
                        <?php if ( empty( $monthly_earnings ) ) : ?>
                            <p class="sep-empty">No earnings data yet.</p>
                        <?php else : ?>
                            <table class="sep-table">
                                <thead><tr><th>Month</th><th>Earnings</th></tr></thead>
                                <tbody>
                                    <?php foreach ( $monthly_earnings as $me ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $me->month ); ?></td>
                                            <td><?php echo esc_html( $currency . ' ' . number_format( $me->total ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Affiliates -->
                <div class="sep-card">
                    <div class="sep-card-header">
                        <h2>Top Affiliates</h2>
                    </div>
                    <div class="sep-card-body">
                        <?php if ( empty( $top_affiliates ) ) : ?>
                            <p class="sep-empty">No affiliates yet.</p>
                        <?php else : ?>
                            <table class="sep-table">
                                <thead><tr><th>Name</th><th>Earnings</th><th>Clicks</th><th>Sign-ups</th></tr></thead>
                                <tbody>
                                    <?php foreach ( $top_affiliates as $ta ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $ta->name ); ?></td>
                                            <td><?php echo esc_html( $currency . ' ' . number_format( $ta->total_earnings ) ); ?></td>
                                            <td><?php echo esc_html( number_format( $ta->total_clicks ) ); ?></td>
                                            <td><?php echo esc_html( number_format( $ta->total_signups ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="sep-card sep-mt-20">
                <div class="sep-card-header">
                    <h2>Activity Log (<?php echo esc_html( number_format( $activity_count ) ); ?> entries)</h2>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sep-reports&sep_action=export_activity' ), 'sep_export_activity' ) ); ?>" class="button">Export CSV</a>
                </div>
                <div class="sep-card-body">
                    <?php
                    $logs = $wpdb->get_results(
                        "SELECT * FROM {$wpdb->prefix}sep_activity_log ORDER BY created_at DESC LIMIT 50"
                    );
                    ?>
                    <?php if ( empty( $logs ) ) : ?>
                        <p class="sep-empty">No activity logged.</p>
                    <?php else : ?>
                        <table class="sep-table">
                            <thead><tr><th>Type</th><th>Message</th><th>Time</th></tr></thead>
                            <tbody>
                                <?php foreach ( $logs as $log ) : ?>
                                    <tr>
                                        <td><span class="sep-activity-type sep-type-<?php echo esc_attr( $log->type ); ?>"><?php echo esc_html( $log->type ); ?></span></td>
                                        <td><?php echo esc_html( $log->message ); ?></td>
                                        <td><?php echo esc_html( $log->created_at ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── Settings ───────────────────────────────────────────────

    public static function page_settings() {
        $currency       = get_option( 'sep_currency', 'UGX' );
        $wa_number      = get_option( 'sep_whatsapp_number', '256700193921' );
        $commission     = get_option( 'sep_affiliate_commission', 10 );
        $vote_limit     = get_option( 'sep_vote_limit_per_day', 1 );
        ?>
        <div class="wrap sep-wrap">
            <h1 class="sep-page-title">Settings</h1>

            <div class="sep-card">
                <div class="sep-card-header">
                    <h2>General Settings</h2>
                </div>
                <div class="sep-card-body">
                    <div class="sep-form-row">
                        <div class="sep-form-group">
                            <label>Currency</label>
                            <input type="text" id="sepSettingsCurrency" class="sep-input" value="<?php echo esc_attr( $currency ); ?>" />
                        </div>
                        <div class="sep-form-group">
                            <label>WhatsApp Number</label>
                            <input type="text" id="sepSettingsWhatsApp" class="sep-input" value="<?php echo esc_attr( $wa_number ); ?>" />
                        </div>
                        <div class="sep-form-group">
                            <label>Affiliate Commission (%)</label>
                            <input type="number" id="sepSettingsCommission" class="sep-input" value="<?php echo esc_attr( $commission ); ?>" />
                        </div>
                        <div class="sep-form-group">
                            <label>Votes per User per Day</label>
                            <input type="number" id="sepSettingsVoteLimit" class="sep-input" value="<?php echo esc_attr( $vote_limit ); ?>" min="1" />
                        </div>
                    </div>
                    <button class="button button-primary" id="sepSaveSettings">Save Settings</button>
                </div>
            </div>

            <!-- Shortcode Reference -->
            <div class="sep-card sep-mt-20">
                <div class="sep-card-header">
                    <h2>Shortcode Reference</h2>
                </div>
                <div class="sep-card-body">
                    <table class="sep-table">
                        <thead><tr><th>Shortcode</th><th>Description</th><th>Usage</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><code>[sep_showcase]</code></td>
                                <td>Eyewear showcase with carousel, filters, and product grid</td>
                                <td>Add to any page</td>
                            </tr>
                            <tr>
                                <td><code>[sep_competition]</code></td>
                                <td>Competition voting page with contestant grid and leaderboard</td>
                                <td>Add to competition page</td>
                            </tr>
                            <tr>
                                <td><code>[sep_affiliate_dashboard]</code></td>
                                <td>Affiliate dashboard with stats, referral link, and withdrawal form</td>
                                <td>Add to affiliate page (requires login)</td>
                            </tr>
                            <tr>
                                <td><code>[sep_product_detail]</code></td>
                                <td>Single product detail view</td>
                                <td><code>[sep_product_detail id="123"]</code> or auto via ?sep_product=123</td>
                            </tr>
                            <tr>
                                <td><code>[sep_leaderboard]</code></td>
                                <td>Standalone leaderboard widget</td>
                                <td><code>[sep_leaderboard limit="10"]</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── AJAX Handlers ──────────────────────────────────────────

    public static function ajax_save_product() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $data = array(
            'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'brand'       => isset( $_POST['brand'] ) ? sanitize_text_field( wp_unslash( $_POST['brand'] ) ) : '',
            'price'       => isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0,
            'category'    => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
            'style'       => isset( $_POST['style'] ) ? sanitize_text_field( wp_unslash( $_POST['style'] ) ) : '',
            'size'        => isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '',
            'stock'       => isset( $_POST['stock'] ) ? absint( $_POST['stock'] ) : 0,
            'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
            'image_url'   => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
            'featured'    => isset( $_POST['featured'] ) ? absint( $_POST['featured'] ) : 0,
        );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => 'Product name is required.' ) );
        }

        if ( $product_id ) {
            SEP_Products::update_product( $product_id, $data );
            wp_send_json_success( array( 'message' => 'Product updated.', 'id' => $product_id ) );
        } else {
            $id = SEP_Products::add_product( $data );
            if ( $id ) {
                wp_send_json_success( array( 'message' => 'Product added.', 'id' => $id ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add product.' ) );
            }
        }
    }

    public static function ajax_delete_product() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid product.' ) );
        }

        SEP_Products::delete_product( $id );
        wp_send_json_success( array( 'message' => 'Product deleted.' ) );
    }

    public static function ajax_save_contestant() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = array(
            'name'      => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'bio'       => isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '',
            'image_url' => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
        );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => 'Contestant name is required.' ) );
        }

        $contestant_id = isset( $_POST['contestant_id'] ) ? absint( $_POST['contestant_id'] ) : 0;
        if ( $contestant_id ) {
            SEP_Competition::update_contestant( $contestant_id, $data );
            wp_send_json_success( array( 'message' => 'Contestant updated.' ) );
        } else {
            $id = SEP_Competition::add_contestant( $data );
            if ( $id ) {
                wp_send_json_success( array( 'message' => 'Contestant added.', 'id' => $id ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add contestant.' ) );
            }
        }
    }

    public static function ajax_delete_contestant() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $id = isset( $_POST['contestant_id'] ) ? absint( $_POST['contestant_id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid contestant.' ) );
        }

        SEP_Competition::delete_contestant( $id );
        wp_send_json_success( array( 'message' => 'Contestant deleted.' ) );
    }

    public static function ajax_reset_votes() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $contestant_id = isset( $_POST['contestant_id'] ) ? absint( $_POST['contestant_id'] ) : 0;
        SEP_Competition::reset_votes( $contestant_id );
        wp_send_json_success( array( 'message' => 'Votes reset.' ) );
    }

    public static function ajax_save_affiliate() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $data = array(
            'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
            'phone'         => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
            'referral_code' => isset( $_POST['referral_code'] ) ? sanitize_text_field( wp_unslash( $_POST['referral_code'] ) ) : '',
        );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => 'Affiliate name is required.' ) );
        }

        $id = SEP_Affiliate::add_affiliate( $data );
        if ( $id ) {
            wp_send_json_success( array( 'message' => 'Affiliate added.', 'id' => $id ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to add affiliate.' ) );
        }
    }

    public static function ajax_delete_affiliate() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $id = isset( $_POST['affiliate_id'] ) ? absint( $_POST['affiliate_id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid affiliate.' ) );
        }

        SEP_Affiliate::delete_affiliate( $id );
        wp_send_json_success( array( 'message' => 'Affiliate deleted.' ) );
    }

    public static function ajax_process_withdrawal() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $id     = isset( $_POST['withdrawal_id'] ) ? absint( $_POST['withdrawal_id'] ) : 0;
        $action = isset( $_POST['wd_action'] ) ? sanitize_text_field( wp_unslash( $_POST['wd_action'] ) ) : '';
        $note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

        if ( ! $id || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        $result = SEP_Affiliate::process_withdrawal( $id, $action, $note );
        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Withdrawal ' . $action . 'd.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to process withdrawal.' ) );
        }
    }

    public static function ajax_save_settings() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $settings = array(
            'sep_currency'              => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'UGX',
            'sep_whatsapp_number'       => isset( $_POST['whatsapp_number'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_number'] ) ) : '',
            'sep_affiliate_commission'  => isset( $_POST['commission'] ) ? absint( $_POST['commission'] ) : 10,
            'sep_vote_limit_per_day'    => isset( $_POST['vote_limit'] ) ? absint( $_POST['vote_limit'] ) : 1,
            'sep_competition_status'    => isset( $_POST['comp_status'] ) ? sanitize_text_field( wp_unslash( $_POST['comp_status'] ) ) : 'open',
            'sep_competition_title'     => isset( $_POST['comp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['comp_title'] ) ) : '',
            'sep_competition_countdown' => isset( $_POST['comp_countdown'] ) ? sanitize_text_field( wp_unslash( $_POST['comp_countdown'] ) ) : '',
        );

        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }

        SEP_Database::log_activity( 'settings', 'Settings updated' );
        wp_send_json_success( array( 'message' => 'Settings saved.' ) );
    }

    public static function ajax_upload_image() {
        check_ajax_referer( 'sep_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        if ( empty( $_FILES['image'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'image', 0 );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        $url = wp_get_attachment_url( $attachment_id );
        wp_send_json_success( array( 'url' => $url, 'id' => $attachment_id ) );
    }

    // ─── CSV Exports ────────────────────────────────────────────

    private static function export_affiliates_csv() {
        $affiliates = SEP_Affiliate::get_all_affiliates( array( 'limit' => 10000 ) );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="affiliates-' . gmdate( 'Y-m-d' ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Name', 'Email', 'Phone', 'Code', 'Clicks', 'Sign-ups', 'Earnings', 'Balance', 'Status', 'Created' ) );

        foreach ( $affiliates as $a ) {
            fputcsv( $output, array(
                $a->id, $a->name, $a->email, $a->phone, $a->referral_code,
                $a->total_clicks, $a->total_signups, $a->total_earnings,
                $a->pending_balance, $a->status, $a->created_at,
            ) );
        }

        fclose( $output );
        exit;
    }

    private static function export_activity_csv() {
        global $wpdb;
        $logs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sep_activity_log ORDER BY created_at DESC LIMIT 10000"
        );

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="activity-log-' . gmdate( 'Y-m-d' ) . '.csv"' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Type', 'Message', 'Created' ) );

        foreach ( $logs as $log ) {
            fputcsv( $output, array( $log->id, $log->type, $log->message, $log->created_at ) );
        }

        fclose( $output );
        exit;
    }
}
