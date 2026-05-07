(function($) {
    'use strict';

    function showToast(message, type) {
        var $toast = $('.sep-admin-toast');
        if (!$toast.length) {
            $toast = $('<div class="sep-admin-toast"></div>').appendTo('body');
        }
        $toast.text(message).removeClass('success error show').addClass(type).addClass('show');
        setTimeout(function() { $toast.removeClass('show'); }, 3500);
    }

    function ajaxPost(action, data, callback) {
        data.action = action;
        data.nonce = sepAdmin.nonce;
        $.post(sepAdmin.url, data, function(response) {
            if (response.success) {
                showToast(response.data.message || 'Done!', 'success');
                if (callback) callback(response.data);
            } else {
                showToast(response.data.message || 'Error occurred.', 'error');
            }
        }).fail(function() {
            showToast('Network error. Please try again.', 'error');
        });
    }

    $(document).ready(function() {

        // ── Competition Settings ─────────────────────────
        $('#sepSaveCompSettings').on('click', function() {
            ajaxPost('sep_admin_save_settings', {
                comp_title: $('#sepCompTitle').val(),
                comp_status: $('#sepCompStatus').val(),
                comp_countdown: $('#sepCompCountdown').val()
            }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Add Contestant ───────────────────────────────
        $('#sepAddContestant').on('click', function() {
            var name = $('#sepContestantName').val().trim();
            if (!name) {
                showToast('Please enter a contestant name.', 'error');
                return;
            }
            ajaxPost('sep_admin_save_contestant', {
                name: name,
                bio: $('#sepContestantBio').val(),
                image_url: $('#sepContestantImage').val()
            }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Delete Contestant ────────────────────────────
        $(document).on('click', '.sep-delete-contestant', function() {
            if (!confirm('Delete this contestant and all their votes?')) return;
            var id = $(this).data('id');
            ajaxPost('sep_admin_delete_contestant', { contestant_id: id }, function() {
                $('tr[data-id="' + id + '"]').fadeOut(300, function() { $(this).remove(); });
            });
        });

        // ── Reset Contestant Votes ───────────────────────
        $(document).on('click', '.sep-reset-contestant-votes', function() {
            if (!confirm('Reset votes for this contestant?')) return;
            var id = $(this).data('id');
            ajaxPost('sep_admin_reset_votes', { contestant_id: id }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Reset All Votes ──────────────────────────────
        $('#sepResetAllVotes').on('click', function() {
            if (!confirm('Are you sure you want to reset ALL votes? This cannot be undone.')) return;
            ajaxPost('sep_admin_reset_votes', { contestant_id: 0 }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Add Product ──────────────────────────────────
        $('#sepAddProduct').on('click', function() {
            var name = $('#sepProdName').val().trim();
            if (!name) {
                showToast('Please enter a product name.', 'error');
                return;
            }
            ajaxPost('sep_admin_save_product', {
                name: name,
                brand: $('#sepProdBrand').val(),
                price: $('#sepProdPrice').val(),
                category: $('#sepProdCategory').val(),
                style: $('#sepProdStyle').val(),
                size: $('#sepProdSize').val(),
                stock: $('#sepProdStock').val(),
                description: $('#sepProdDesc').val(),
                image_url: $('#sepProdImage').val(),
                featured: $('#sepProdFeatured').val()
            }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Delete Product ───────────────────────────────
        $(document).on('click', '.sep-delete-product', function() {
            if (!confirm('Delete this product?')) return;
            var id = $(this).data('id');
            ajaxPost('sep_admin_delete_product', { product_id: id }, function() {
                $('tr[data-id="' + id + '"]').fadeOut(300, function() { $(this).remove(); });
            });
        });

        // ── Add Affiliate ────────────────────────────────
        $('#sepAddAffiliate').on('click', function() {
            var name = $('#sepAffName').val().trim();
            if (!name) {
                showToast('Please enter an affiliate name.', 'error');
                return;
            }
            ajaxPost('sep_admin_save_affiliate', {
                name: name,
                email: $('#sepAffEmail').val(),
                phone: $('#sepAffPhone').val(),
                referral_code: $('#sepAffCode').val()
            }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Delete Affiliate ─────────────────────────────
        $(document).on('click', '.sep-delete-affiliate', function() {
            if (!confirm('Delete this affiliate and all their data?')) return;
            var id = $(this).data('id');
            ajaxPost('sep_admin_delete_affiliate', { affiliate_id: id }, function() {
                $('tr[data-id="' + id + '"]').fadeOut(300, function() { $(this).remove(); });
            });
        });

        // ── Approve Withdrawal ───────────────────────────
        $(document).on('click', '.sep-approve-wd', function() {
            if (!confirm('Approve this withdrawal?')) return;
            var id = $(this).data('id');
            ajaxPost('sep_admin_process_withdrawal', {
                withdrawal_id: id,
                wd_action: 'approve'
            }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Reject Withdrawal ────────────────────────────
        $(document).on('click', '.sep-reject-wd', function() {
            var note = prompt('Reason for rejection (optional):');
            if (note === null) return;
            var id = $(this).data('id');
            ajaxPost('sep_admin_process_withdrawal', {
                withdrawal_id: id,
                wd_action: 'reject',
                note: note
            }, function() {
                setTimeout(function() { location.reload(); }, 800);
            });
        });

        // ── Save Settings ────────────────────────────────
        $('#sepSaveSettings').on('click', function() {
            ajaxPost('sep_admin_save_settings', {
                currency: $('#sepSettingsCurrency').val(),
                whatsapp_number: $('#sepSettingsWhatsApp').val(),
                commission: $('#sepSettingsCommission').val(),
                vote_limit: $('#sepSettingsVoteLimit').val()
            }, function() {
                showToast('Settings saved!', 'success');
            });
        });

        // ── WordPress Media Upload ───────────────────────
        $(document).on('click', '.sep-upload-btn', function(e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use Image' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url);
            });
            frame.open();
        });

    });

})(jQuery);
