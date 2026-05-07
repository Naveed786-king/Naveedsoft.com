<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEP_Shortcodes {

    public static function init() {
        add_shortcode( 'sep_showcase', array( __CLASS__, 'render_showcase' ) );
        add_shortcode( 'sep_competition', array( __CLASS__, 'render_competition' ) );
        add_shortcode( 'sep_affiliate_dashboard', array( __CLASS__, 'render_affiliate_dashboard' ) );
        add_shortcode( 'sep_product_detail', array( __CLASS__, 'render_product_detail' ) );
        add_shortcode( 'sep_leaderboard', array( __CLASS__, 'render_leaderboard' ) );
    }

    /**
     * [sep_showcase] — Eyewear showcase with carousel + filters + product grid.
     */
    public static function render_showcase( $atts ) {
        $atts = shortcode_atts( array(
            'limit'    => 24,
            'category' => '',
            'featured' => '',
        ), $atts, 'sep_showcase' );

        $products   = SEP_Products::get_products( array(
            'limit'    => intval( $atts['limit'] ),
            'category' => $atts['category'],
            'featured' => $atts['featured'],
        ) );
        $categories = SEP_Products::get_categories();
        $styles     = SEP_Products::get_styles();
        $sizes      = SEP_Products::get_sizes();
        $currency   = get_option( 'sep_currency', 'UGX' );

        ob_start();
        ?>
        <div class="sep-showcase" id="sepShowcase">
            <!-- Hero / Carousel -->
            <div class="sep-hero">
                <div class="sep-hero-inner">
                    <h1 class="sep-hero-title">Spectacular <em>Eyewear</em></h1>
                    <p class="sep-hero-sub">Premium frames. Curated for you.</p>
                </div>
                <div class="sep-carousel" id="sepCarousel">
                    <div class="sep-carousel-track" id="sepCarouselTrack">
                        <?php foreach ( $products as $i => $p ) : ?>
                            <div class="sep-carousel-slide <?php echo 0 === $i ? 'active' : ''; ?>"
                                 data-product-id="<?php echo esc_attr( $p->id ); ?>">
                                <img src="<?php echo esc_url( $p->image_url ); ?>"
                                     alt="<?php echo esc_attr( $p->name ); ?>"
                                     loading="lazy" />
                                <div class="sep-carousel-caption">
                                    <span class="sep-carousel-brand"><?php echo esc_html( $p->brand ); ?></span>
                                    <span class="sep-carousel-name"><?php echo esc_html( $p->name ); ?></span>
                                    <span class="sep-carousel-price"><?php echo esc_html( $currency . ' ' . number_format( $p->price ) ); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="sep-carousel-btn sep-carousel-prev" id="sepCarouselPrev">&#8249;</button>
                    <button class="sep-carousel-btn sep-carousel-next" id="sepCarouselNext">&#8250;</button>
                    <div class="sep-carousel-dots" id="sepCarouselDots"></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="sep-filters" id="sepFilters">
                <div class="sep-filter-row">
                    <input type="search" class="sep-search" id="sepSearch"
                           placeholder="Search eyewear..." />

                    <select class="sep-filter-select" id="sepFilterCategory">
                        <option value="">All Categories</option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select class="sep-filter-select" id="sepFilterStyle">
                        <option value="">All Styles</option>
                        <?php foreach ( $styles as $st ) : ?>
                            <option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $st ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select class="sep-filter-select" id="sepFilterSize">
                        <option value="">All Sizes</option>
                        <?php foreach ( $sizes as $sz ) : ?>
                            <option value="<?php echo esc_attr( $sz ); ?>"><?php echo esc_html( $sz ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select class="sep-filter-select" id="sepFilterPrice">
                        <option value="">Price Range</option>
                        <option value="0-100000">Under 100,000</option>
                        <option value="100000-300000">100K – 300K</option>
                        <option value="300000-500000">300K – 500K</option>
                        <option value="500000-1000000">500K – 1M</option>
                        <option value="1000000-0">Above 1M</option>
                    </select>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="sep-product-grid" id="sepProductGrid">
                <?php foreach ( $products as $p ) : ?>
                    <div class="sep-product-card" data-product-id="<?php echo esc_attr( $p->id ); ?>">
                        <div class="sep-product-img-wrap">
                            <img src="<?php echo esc_url( $p->image_url ); ?>"
                                 alt="<?php echo esc_attr( $p->name ); ?>" loading="lazy" />
                            <?php if ( $p->featured ) : ?>
                                <span class="sep-badge sep-badge-featured">Featured</span>
                            <?php endif; ?>
                        </div>
                        <div class="sep-product-info">
                            <span class="sep-product-brand"><?php echo esc_html( $p->brand ); ?></span>
                            <h3 class="sep-product-name"><?php echo esc_html( $p->name ); ?></h3>
                            <span class="sep-product-price"><?php echo esc_html( $currency . ' ' . number_format( $p->price ) ); ?></span>
                        </div>
                        <div class="sep-product-actions">
                            <a href="?sep_product=<?php echo esc_attr( $p->id ); ?>" class="sep-btn sep-btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sep-load-more-wrap" id="sepLoadMoreWrap" style="display:none;">
                <button class="sep-btn sep-btn-outline" id="sepLoadMore">Load More</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [sep_competition] — Competition voting page with contestant grid + leaderboard.
     */
    public static function render_competition( $atts ) {
        $atts = shortcode_atts( array(
            'show_leaderboard' => 'yes',
        ), $atts, 'sep_competition' );

        $contestants = SEP_Competition::get_contestants();
        $total_votes = SEP_Competition::get_total_votes();
        $status      = get_option( 'sep_competition_status', 'open' );
        $title       = get_option( 'sep_competition_title', 'Spectacular Eyewear Competition' );
        $countdown   = get_option( 'sep_competition_countdown', '' );

        ob_start();
        ?>
        <div class="sep-competition" id="sepCompetition"
             data-status="<?php echo esc_attr( $status ); ?>"
             data-countdown="<?php echo esc_attr( $countdown ); ?>">

            <!-- Header -->
            <div class="sep-comp-header">
                <h1 class="sep-comp-title"><?php echo esc_html( $title ); ?></h1>
                <div class="sep-comp-meta">
                    <span class="sep-comp-status sep-comp-status-<?php echo esc_attr( $status ); ?>">
                        <span class="sep-status-dot"></span>
                        <?php echo 'open' === $status ? esc_html__( 'Voting Open', 'spectacular' ) : esc_html__( 'Voting Closed', 'spectacular' ); ?>
                    </span>
                    <span class="sep-comp-total"><?php echo esc_html( number_format( $total_votes ) ); ?> total votes</span>
                </div>
                <?php if ( $countdown ) : ?>
                    <div class="sep-comp-countdown" id="sepCountdown" data-target="<?php echo esc_attr( $countdown ); ?>">
                        <div class="sep-cd-unit"><span class="sep-cd-num" id="sepCdDays">00</span><span class="sep-cd-label">Days</span></div>
                        <div class="sep-cd-unit"><span class="sep-cd-num" id="sepCdHours">00</span><span class="sep-cd-label">Hours</span></div>
                        <div class="sep-cd-unit"><span class="sep-cd-num" id="sepCdMins">00</span><span class="sep-cd-label">Minutes</span></div>
                        <div class="sep-cd-unit"><span class="sep-cd-num" id="sepCdSecs">00</span><span class="sep-cd-label">Seconds</span></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contestant Grid -->
            <div class="sep-contestant-grid" id="sepContestantGrid">
                <?php foreach ( $contestants as $rank => $c ) :
                    $pct = $total_votes > 0 ? round( ( $c->votes / $total_votes ) * 100, 1 ) : 0;
                    ?>
                    <div class="sep-contestant-card" data-contestant-id="<?php echo esc_attr( $c->id ); ?>">
                        <div class="sep-contestant-rank">#<?php echo esc_html( $rank + 1 ); ?></div>
                        <div class="sep-contestant-img-wrap">
                            <?php if ( $c->image_url ) : ?>
                                <img src="<?php echo esc_url( $c->image_url ); ?>"
                                     alt="<?php echo esc_attr( $c->name ); ?>" loading="lazy" />
                            <?php else : ?>
                                <div class="sep-contestant-placeholder">
                                    <?php echo esc_html( strtoupper( substr( $c->name, 0, 2 ) ) ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sep-contestant-info">
                            <h3 class="sep-contestant-name"><?php echo esc_html( $c->name ); ?></h3>
                            <?php if ( $c->bio ) : ?>
                                <p class="sep-contestant-bio"><?php echo esc_html( wp_trim_words( $c->bio, 15 ) ); ?></p>
                            <?php endif; ?>
                            <div class="sep-contestant-votes">
                                <span class="sep-vote-count"><?php echo esc_html( number_format( $c->votes ) ); ?></span> votes
                                <span class="sep-vote-pct">(<?php echo esc_html( $pct ); ?>%)</span>
                            </div>
                            <div class="sep-vote-bar">
                                <div class="sep-vote-bar-fill" style="width: <?php echo esc_attr( $pct ); ?>%"></div>
                            </div>
                        </div>
                        <?php if ( 'open' === $status ) : ?>
                            <button class="sep-btn sep-btn-vote sep-vote-btn"
                                    data-contestant-id="<?php echo esc_attr( $c->id ); ?>">
                                Vote
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( 'yes' === $atts['show_leaderboard'] ) : ?>
                <!-- Leaderboard -->
                <div class="sep-leaderboard" id="sepLeaderboard">
                    <h2 class="sep-lb-title">Leaderboard</h2>
                    <div class="sep-lb-list" id="sepLbList">
                        <?php foreach ( $contestants as $rank => $c ) :
                            $pct = $total_votes > 0 ? round( ( $c->votes / $total_votes ) * 100, 1 ) : 0;
                            ?>
                            <div class="sep-lb-row">
                                <span class="sep-lb-rank"><?php echo esc_html( $rank + 1 ); ?></span>
                                <span class="sep-lb-name"><?php echo esc_html( $c->name ); ?></span>
                                <span class="sep-lb-votes"><?php echo esc_html( number_format( $c->votes ) ); ?></span>
                                <div class="sep-lb-bar"><div class="sep-lb-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
                                <span class="sep-lb-pct"><?php echo esc_html( $pct ); ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Vote success toast -->
            <div class="sep-toast" id="sepToast"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [sep_affiliate_dashboard] — Affiliate dashboard for logged-in users.
     */
    public static function render_affiliate_dashboard( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="sep-notice">' . esc_html__( 'Please log in to view your affiliate dashboard.', 'spectacular' ) . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log In', 'spectacular' ) . '</a></div>';
        }

        $affiliate = SEP_Affiliate::get_affiliate_by_user( get_current_user_id() );
        if ( ! $affiliate ) {
            return '<div class="sep-notice">' . esc_html__( 'You are not registered as an affiliate. Contact admin for access.', 'spectacular' ) . '</div>';
        }

        $currency = get_option( 'sep_currency', 'UGX' );
        $ref_link = add_query_arg( 'ref', $affiliate->referral_code, home_url( '/' ) );

        ob_start();
        ?>
        <div class="sep-affiliate-dash" id="sepAffiliateDash">
            <div class="sep-aff-header">
                <h1 class="sep-aff-title">Affiliate Dashboard</h1>
                <span class="sep-aff-welcome">Welcome, <?php echo esc_html( $affiliate->name ); ?></span>
            </div>

            <!-- Stats Cards -->
            <div class="sep-aff-stats">
                <div class="sep-aff-stat">
                    <span class="sep-aff-stat-label">Total Clicks</span>
                    <span class="sep-aff-stat-value" id="sepAffClicks"><?php echo esc_html( number_format( $affiliate->total_clicks ) ); ?></span>
                </div>
                <div class="sep-aff-stat">
                    <span class="sep-aff-stat-label">Sign-ups</span>
                    <span class="sep-aff-stat-value" id="sepAffSignups"><?php echo esc_html( number_format( $affiliate->total_signups ) ); ?></span>
                </div>
                <div class="sep-aff-stat">
                    <span class="sep-aff-stat-label">Total Earnings</span>
                    <span class="sep-aff-stat-value" id="sepAffEarnings"><?php echo esc_html( $currency . ' ' . number_format( $affiliate->total_earnings ) ); ?></span>
                </div>
                <div class="sep-aff-stat">
                    <span class="sep-aff-stat-label">Available Balance</span>
                    <span class="sep-aff-stat-value sep-aff-balance" id="sepAffBalance"><?php echo esc_html( $currency . ' ' . number_format( $affiliate->pending_balance ) ); ?></span>
                </div>
            </div>

            <!-- Referral Link -->
            <div class="sep-aff-ref-box">
                <label class="sep-aff-ref-label">Your Referral Link</label>
                <div class="sep-aff-ref-input-wrap">
                    <input type="text" class="sep-aff-ref-input" id="sepAffRefLink"
                           value="<?php echo esc_url( $ref_link ); ?>" readonly />
                    <button class="sep-btn sep-btn-primary sep-copy-btn" id="sepCopyRefLink">Copy</button>
                </div>
                <p class="sep-aff-ref-code">Code: <strong><?php echo esc_html( $affiliate->referral_code ); ?></strong></p>
            </div>

            <!-- Withdrawal -->
            <div class="sep-aff-withdraw-box" id="sepWithdrawBox">
                <h3>Request Withdrawal</h3>
                <form id="sepWithdrawForm" class="sep-aff-withdraw-form">
                    <div class="sep-aff-field">
                        <label>Amount (<?php echo esc_html( $currency ); ?>)</label>
                        <input type="number" id="sepWithdrawAmount" min="1"
                               max="<?php echo esc_attr( $affiliate->pending_balance ); ?>"
                               placeholder="Enter amount" required />
                    </div>
                    <div class="sep-aff-field">
                        <label>Withdrawal Method</label>
                        <select id="sepWithdrawMethod">
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    <div class="sep-aff-field">
                        <label>Account Details</label>
                        <input type="text" id="sepWithdrawAccount"
                               placeholder="Phone number or account number" required />
                    </div>
                    <button type="submit" class="sep-btn sep-btn-primary">Submit Withdrawal</button>
                </form>
            </div>

            <!-- Recent Activity -->
            <div class="sep-aff-activity" id="sepAffActivity">
                <h3>Recent Activity</h3>
                <div class="sep-aff-activity-list" id="sepAffActivityList">
                    <p class="sep-aff-loading">Loading activity...</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [sep_product_detail] — Single product detail page.
     */
    public static function render_product_detail( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'sep_product_detail' );

        $product_id = $atts['id'] ? absint( $atts['id'] ) : ( isset( $_GET['sep_product'] ) ? absint( $_GET['sep_product'] ) : 0 );

        if ( ! $product_id ) {
            return '<div class="sep-notice">' . esc_html__( 'No product selected.', 'spectacular' ) . '</div>';
        }

        $product = SEP_Products::get_product( $product_id );
        if ( ! $product || 'active' !== $product->status ) {
            return '<div class="sep-notice">' . esc_html__( 'Product not found.', 'spectacular' ) . '</div>';
        }

        $currency  = get_option( 'sep_currency', 'UGX' );
        $gallery   = json_decode( $product->gallery, true );
        $variants  = json_decode( $product->color_variants, true );
        $wa_number = get_option( 'sep_whatsapp_number', '256700193921' );

        if ( ! is_array( $gallery ) ) {
            $gallery = array();
        }
        if ( ! is_array( $variants ) ) {
            $variants = array();
        }

        ob_start();
        ?>
        <div class="sep-product-detail" id="sepProductDetail" data-product-id="<?php echo esc_attr( $product->id ); ?>">
            <div class="sep-pd-layout">
                <!-- Image Gallery -->
                <div class="sep-pd-gallery">
                    <div class="sep-pd-main-img-wrap">
                        <img src="<?php echo esc_url( $product->image_url ); ?>"
                             alt="<?php echo esc_attr( $product->name ); ?>"
                             class="sep-pd-main-img" id="sepPdMainImg" />
                    </div>
                    <?php if ( ! empty( $gallery ) ) : ?>
                        <div class="sep-pd-thumbs">
                            <div class="sep-pd-thumb active" data-img="<?php echo esc_url( $product->image_url ); ?>">
                                <img src="<?php echo esc_url( $product->image_url ); ?>" alt="Main" />
                            </div>
                            <?php foreach ( $gallery as $img ) : ?>
                                <div class="sep-pd-thumb" data-img="<?php echo esc_url( $img ); ?>">
                                    <img src="<?php echo esc_url( $img ); ?>" alt="Gallery" />
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="sep-pd-info">
                    <span class="sep-pd-brand"><?php echo esc_html( $product->brand ); ?></span>
                    <h1 class="sep-pd-name"><?php echo esc_html( $product->name ); ?></h1>
                    <span class="sep-pd-price"><?php echo esc_html( $currency . ' ' . number_format( $product->price ) ); ?></span>

                    <?php if ( $product->description ) : ?>
                        <div class="sep-pd-desc"><?php echo wp_kses_post( $product->description ); ?></div>
                    <?php endif; ?>

                    <div class="sep-pd-meta">
                        <?php if ( $product->category ) : ?>
                            <span class="sep-pd-meta-item">Category: <strong><?php echo esc_html( $product->category ); ?></strong></span>
                        <?php endif; ?>
                        <?php if ( $product->style ) : ?>
                            <span class="sep-pd-meta-item">Style: <strong><?php echo esc_html( $product->style ); ?></strong></span>
                        <?php endif; ?>
                        <?php if ( $product->size ) : ?>
                            <span class="sep-pd-meta-item">Size: <strong><?php echo esc_html( $product->size ); ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $variants ) ) : ?>
                        <div class="sep-pd-variants">
                            <label class="sep-pd-variants-label">Color Options:</label>
                            <div class="sep-pd-variant-swatches" id="sepPdVariants">
                                <?php foreach ( $variants as $i => $v ) : ?>
                                    <button class="sep-pd-swatch <?php echo 0 === $i ? 'active' : ''; ?>"
                                            data-variant="<?php echo esc_attr( $i ); ?>"
                                            data-imgs="<?php echo esc_attr( wp_json_encode( isset( $v['imgs'] ) ? $v['imgs'] : array() ) ); ?>"
                                            style="background-color: <?php echo esc_attr( isset( $v['hex'] ) ? $v['hex'] : '#ccc' ); ?>"
                                            title="<?php echo esc_attr( isset( $v['label'] ) ? $v['label'] : '' ); ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="sep-pd-actions">
                        <a href="https://wa.me/<?php echo esc_attr( $wa_number ); ?>?text=<?php echo esc_attr( rawurlencode( 'Hi Spectacular! I am interested in ' . $product->brand . ' ' . $product->name . ' (' . $currency . ' ' . number_format( $product->price ) . '). Can you help me order?' ) ); ?>"
                           class="sep-btn sep-btn-whatsapp" target="_blank" rel="noopener">
                            Order via WhatsApp
                        </a>
                        <button class="sep-btn sep-btn-outline sep-btn-lens-flow" data-product-id="<?php echo esc_attr( $product->id ); ?>">
                            Select Lenses
                        </button>
                    </div>

                    <?php if ( $product->stock > 0 ) : ?>
                        <p class="sep-pd-stock sep-pd-stock-in">In Stock (<?php echo esc_html( $product->stock ); ?> available)</p>
                    <?php else : ?>
                        <p class="sep-pd-stock sep-pd-stock-out">Contact us for availability</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [sep_leaderboard] — Standalone leaderboard widget.
     */
    public static function render_leaderboard( $atts ) {
        $atts = shortcode_atts( array(
            'limit' => 10,
        ), $atts, 'sep_leaderboard' );

        $contestants = SEP_Competition::get_contestants( array( 'limit' => intval( $atts['limit'] ) ) );
        $total_votes = SEP_Competition::get_total_votes();

        ob_start();
        ?>
        <div class="sep-leaderboard-widget">
            <h3 class="sep-lb-widget-title">Competition Leaderboard</h3>
            <?php foreach ( $contestants as $rank => $c ) :
                $pct = $total_votes > 0 ? round( ( $c->votes / $total_votes ) * 100, 1 ) : 0;
                ?>
                <div class="sep-lb-row">
                    <span class="sep-lb-rank"><?php echo esc_html( $rank + 1 ); ?></span>
                    <span class="sep-lb-name"><?php echo esc_html( $c->name ); ?></span>
                    <span class="sep-lb-votes"><?php echo esc_html( number_format( $c->votes ) ); ?></span>
                    <div class="sep-lb-bar"><div class="sep-lb-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
                    <span class="sep-lb-pct"><?php echo esc_html( $pct ); ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
