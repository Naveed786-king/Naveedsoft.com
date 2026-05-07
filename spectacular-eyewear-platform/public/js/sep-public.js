(function($) {
    'use strict';

    /* ===== Toast ===== */
    function showToast(message, type) {
        var $toast = $('#sepToast');
        if (!$toast.length) {
            $toast = $('<div class="sep-toast" id="sepToast"></div>').appendTo('body');
        }
        $toast.text(message).removeClass('success error show').addClass(type);
        setTimeout(function() { $toast.addClass('show'); }, 10);
        setTimeout(function() { $toast.removeClass('show'); }, 3500);
    }

    /* ===== Carousel ===== */
    function initCarousel() {
        var $track = $('#sepCarouselTrack');
        if (!$track.length) return;

        var $slides = $track.find('.sep-carousel-slide');
        var total = $slides.length;
        if (total === 0) return;

        var current = 0;
        var $dots = $('#sepCarouselDots');

        // Build dots
        for (var i = 0; i < Math.min(total, 8); i++) {
            var cls = i === 0 ? 'sep-carousel-dot active' : 'sep-carousel-dot';
            $dots.append('<span class="' + cls + '" data-idx="' + i + '"></span>');
        }

        function goTo(idx) {
            if (idx < 0) idx = total - 1;
            if (idx >= total) idx = 0;
            current = idx;
            $track.css('transform', 'translateX(-' + (current * 100) + '%)');
            $dots.find('.sep-carousel-dot').removeClass('active');
            $dots.find('[data-idx="' + current + '"]').addClass('active');
        }

        $('#sepCarouselNext').on('click', function() { goTo(current + 1); });
        $('#sepCarouselPrev').on('click', function() { goTo(current - 1); });
        $dots.on('click', '.sep-carousel-dot', function() {
            goTo(parseInt($(this).data('idx'), 10));
        });

        // Auto-advance
        var autoTimer = setInterval(function() { goTo(current + 1); }, 5000);
        $track.closest('.sep-carousel').on('mouseenter', function() { clearInterval(autoTimer); });
        $track.closest('.sep-carousel').on('mouseleave', function() {
            autoTimer = setInterval(function() { goTo(current + 1); }, 5000);
        });

        // Click slide to view product
        $slides.on('click', function() {
            var pid = $(this).data('product-id');
            if (pid) {
                window.location.href = '?sep_product=' + pid;
            }
        });
    }

    /* ===== Product Filters (AJAX) ===== */
    function initFilters() {
        var $grid = $('#sepProductGrid');
        if (!$grid.length) return;

        var debounceTimer;

        function loadProducts() {
            var priceRange = $('#sepFilterPrice').val();
            var minPrice = 0;
            var maxPrice = 0;
            if (priceRange) {
                var parts = priceRange.split('-');
                minPrice = parseInt(parts[0], 10) || 0;
                maxPrice = parseInt(parts[1], 10) || 0;
            }

            $.post(sepAjax.url, {
                action: 'sep_get_products',
                nonce: sepAjax.nonce,
                search: $('#sepSearch').val(),
                category: $('#sepFilterCategory').val(),
                style: $('#sepFilterStyle').val(),
                size: $('#sepFilterSize').val(),
                min_price: minPrice,
                max_price: maxPrice
            }, function(response) {
                if (!response.success) return;
                var products = response.data.products;
                var html = '';
                for (var i = 0; i < products.length; i++) {
                    var p = products[i];
                    html += buildProductCard(p);
                }
                if (!html) {
                    html = '<div class="sep-notice" style="grid-column:1/-1;">No products found.</div>';
                }
                $grid.html(html);
            });
        }

        function buildProductCard(p) {
            var currency = 'UGX';
            var price = currency + ' ' + numberFormat(p.price);
            var card = '<div class="sep-product-card" data-product-id="' + p.id + '">';
            card += '<div class="sep-product-img-wrap">';
            if (p.image_url) {
                card += '<img src="' + escHtml(p.image_url) + '" alt="' + escHtml(p.name) + '" loading="lazy" />';
            }
            if (p.featured) {
                card += '<span class="sep-badge sep-badge-featured">Featured</span>';
            }
            card += '</div>';
            card += '<div class="sep-product-info">';
            card += '<span class="sep-product-brand">' + escHtml(p.brand) + '</span>';
            card += '<h3 class="sep-product-name">' + escHtml(p.name) + '</h3>';
            card += '<span class="sep-product-price">' + escHtml(price) + '</span>';
            card += '</div>';
            card += '<div class="sep-product-actions">';
            card += '<a href="?sep_product=' + p.id + '" class="sep-btn sep-btn-primary">View Details</a>';
            card += '</div></div>';
            return card;
        }

        $('#sepSearch').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadProducts, 400);
        });

        $('#sepFilterCategory, #sepFilterStyle, #sepFilterSize, #sepFilterPrice').on('change', loadProducts);
    }

    /* ===== Voting ===== */
    function initVoting() {
        $(document).on('click', '.sep-vote-btn', function() {
            var $btn = $(this);
            var contestantId = $btn.data('contestant-id');
            if ($btn.prop('disabled')) return;

            $btn.prop('disabled', true).text('Voting...');

            $.post(sepAjax.url, {
                action: 'sep_cast_vote',
                nonce: sepAjax.nonce,
                contestant_id: contestantId
            }, function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    // Update vote count on the card
                    var $card = $btn.closest('.sep-contestant-card');
                    $card.find('.sep-vote-count').text(numberFormat(response.data.votes));
                    $btn.text('Voted!').prop('disabled', true);

                    // Refresh leaderboard if on page
                    refreshLeaderboard();
                } else {
                    showToast(response.data.message, 'error');
                    $btn.prop('disabled', false).text('Vote');
                }
            }).fail(function() {
                showToast('Network error. Please try again.', 'error');
                $btn.prop('disabled', false).text('Vote');
            });
        });
    }

    function refreshLeaderboard() {
        var $lb = $('#sepLbList');
        if (!$lb.length) return;

        $.post(sepAjax.url, {
            action: 'sep_get_leaderboard',
            nonce: sepAjax.nonce
        }, function(response) {
            if (!response.success) return;
            var data = response.data;
            var html = '';
            for (var i = 0; i < data.contestants.length; i++) {
                var c = data.contestants[i];
                html += '<div class="sep-lb-row">';
                html += '<span class="sep-lb-rank">' + (i + 1) + '</span>';
                html += '<span class="sep-lb-name">' + escHtml(c.name) + '</span>';
                html += '<span class="sep-lb-votes">' + numberFormat(c.votes) + '</span>';
                html += '<div class="sep-lb-bar"><div class="sep-lb-bar-fill" style="width:' + c.percent + '%"></div></div>';
                html += '<span class="sep-lb-pct">' + c.percent + '%</span>';
                html += '</div>';
            }
            $lb.html(html);
        });
    }

    /* ===== Countdown Timer ===== */
    function initCountdown() {
        var $countdown = $('#sepCountdown');
        if (!$countdown.length) return;

        var target = new Date($countdown.data('target')).getTime();
        if (isNaN(target)) return;

        function updateCountdown() {
            var now = new Date().getTime();
            var diff = target - now;

            if (diff <= 0) {
                $('#sepCdDays').text('00');
                $('#sepCdHours').text('00');
                $('#sepCdMins').text('00');
                $('#sepCdSecs').text('00');
                return;
            }

            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var secs = Math.floor((diff % (1000 * 60)) / 1000);

            $('#sepCdDays').text(pad(days));
            $('#sepCdHours').text(pad(hours));
            $('#sepCdMins').text(pad(mins));
            $('#sepCdSecs').text(pad(secs));
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    /* ===== Affiliate Dashboard ===== */
    function initAffiliateDash() {
        var $dash = $('#sepAffiliateDash');
        if (!$dash.length) return;

        // Copy referral link
        $('#sepCopyRefLink').on('click', function() {
            var $input = $('#sepAffRefLink');
            $input.select();
            try {
                document.execCommand('copy');
                showToast('Link copied!', 'success');
            } catch (e) {
                // Fallback
                if (navigator.clipboard) {
                    navigator.clipboard.writeText($input.val());
                    showToast('Link copied!', 'success');
                }
            }
        });

        // Withdrawal form
        $('#sepWithdrawForm').on('submit', function(e) {
            e.preventDefault();
            var amount = parseFloat($('#sepWithdrawAmount').val());
            var method = $('#sepWithdrawMethod').val();
            var account = $('#sepWithdrawAccount').val().trim();

            if (!amount || amount <= 0) {
                showToast('Please enter a valid amount.', 'error');
                return;
            }
            if (!account) {
                showToast('Please enter account details.', 'error');
                return;
            }

            $.post(sepAjax.url, {
                action: 'sep_request_withdrawal',
                nonce: sepAjax.nonce,
                amount: amount,
                method: method,
                account_info: account
            }, function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    $('#sepWithdrawAmount').val('');
                    $('#sepWithdrawAccount').val('');
                } else {
                    showToast(response.data.message, 'error');
                }
            });
        });

        // Load activity
        loadAffiliateActivity();
    }

    function loadAffiliateActivity() {
        var $list = $('#sepAffActivityList');
        if (!$list.length) return;

        $.post(sepAjax.url, {
            action: 'sep_get_affiliate_activity',
            nonce: sepAjax.nonce
        }, function(response) {
            if (!response.success) {
                $list.html('<p class="sep-aff-loading">Could not load activity.</p>');
                return;
            }

            var data = response.data;
            var html = '';

            if (data.conversions && data.conversions.length) {
                html += '<h4 style="margin:0 0 8px;font-size:14px;color:var(--sep-ink-dim);">Conversions</h4>';
                for (var i = 0; i < data.conversions.length; i++) {
                    var c = data.conversions[i];
                    html += '<div class="sep-lb-row">';
                    html += '<span class="sep-lb-rank" style="color:var(--sep-success);">+</span>';
                    html += '<span class="sep-lb-name">' + escHtml(c.description || c.type) + '</span>';
                    html += '<span class="sep-lb-votes">' + numberFormat(c.amount) + '</span>';
                    html += '<span class="sep-lb-pct">' + escHtml(c.created_at) + '</span>';
                    html += '</div>';
                }
            }

            if (data.withdrawals && data.withdrawals.length) {
                html += '<h4 style="margin:16px 0 8px;font-size:14px;color:var(--sep-ink-dim);">Withdrawals</h4>';
                for (var j = 0; j < data.withdrawals.length; j++) {
                    var w = data.withdrawals[j];
                    html += '<div class="sep-lb-row">';
                    html += '<span class="sep-lb-rank" style="color:var(--sep-warn);">W</span>';
                    html += '<span class="sep-lb-name">' + escHtml(w.method) + ' — ' + escHtml(w.account_info) + '</span>';
                    html += '<span class="sep-lb-votes">' + numberFormat(w.amount) + '</span>';
                    html += '<span class="sep-lb-pct" style="color:' + statusColor(w.status) + ';">' + escHtml(w.status) + '</span>';
                    html += '</div>';
                }
            }

            if (!html) {
                html = '<p class="sep-aff-loading">No activity yet.</p>';
            }

            $list.html(html);
        });
    }

    /* ===== Product Detail ===== */
    function initProductDetail() {
        var $detail = $('#sepProductDetail');
        if (!$detail.length) return;

        // Thumbnail clicks
        $(document).on('click', '.sep-pd-thumb', function() {
            var imgUrl = $(this).data('img');
            $('#sepPdMainImg').attr('src', imgUrl);
            $('.sep-pd-thumb').removeClass('active');
            $(this).addClass('active');
        });

        // Color variant swatches
        $(document).on('click', '.sep-pd-swatch', function() {
            var $swatch = $(this);
            $('.sep-pd-swatch').removeClass('active');
            $swatch.addClass('active');

            var imgs = $swatch.data('imgs');
            if (imgs && imgs.length) {
                $('#sepPdMainImg').attr('src', imgs[0]);
                // Rebuild thumbs
                var $thumbs = $('.sep-pd-thumbs');
                if ($thumbs.length) {
                    var thumbHtml = '';
                    for (var i = 0; i < imgs.length; i++) {
                        var ac = i === 0 ? 'sep-pd-thumb active' : 'sep-pd-thumb';
                        thumbHtml += '<div class="' + ac + '" data-img="' + escHtml(imgs[i]) + '"><img src="' + escHtml(imgs[i]) + '" alt="Variant" /></div>';
                    }
                    $thumbs.html(thumbHtml);
                }
            }
        });
    }

    /* ===== Utilities ===== */
    function numberFormat(num) {
        return parseInt(num, 10).toLocaleString();
    }

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function statusColor(status) {
        var colors = {
            pending: 'var(--sep-warn)',
            approved: 'var(--sep-success)',
            rejected: 'var(--sep-danger)'
        };
        return colors[status] || 'var(--sep-ink-dim)';
    }

    /* ===== Init ===== */
    $(document).ready(function() {
        initCarousel();
        initFilters();
        initVoting();
        initCountdown();
        initAffiliateDash();
        initProductDetail();
    });

})(jQuery);
