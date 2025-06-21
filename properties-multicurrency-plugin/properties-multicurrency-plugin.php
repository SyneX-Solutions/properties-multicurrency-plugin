<?php
/**
 * Plugin Name: Properties Multi-Currency Plugin
 * Description: Adds dynamic multi-currency pricing with admin-controlled rates and frontend switcher.
 * Version: 1.0.0
 * Author: Synex Solutions
 * GitHub Plugin URI: https://github.com/SyneX-Solutions/properties-multicurrency-plugin
 * Primary Branch: main
 */

// -------------------------------
// Admin Settings Page
// -------------------------------

add_action('admin_menu', function () {
    add_options_page(
        'Property Currency Settings',
        'Currency Settings',
        'manage_options',
        'property-currency-settings',
        'render_currency_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('property_currency_group', 'use_live_rates');
    register_setting('property_currency_group', 'rate_usd_aed');
    register_setting('property_currency_group', 'rate_usd_eur');
    register_setting('property_currency_group', 'rate_usd_gbp');
    register_setting('property_currency_group', 'exchange_api_key');
});

function render_currency_settings_page() {
    ?>
    <div class="wrap">
        <h1>Property Currency Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('property_currency_group'); ?>
            <?php do_settings_sections('property_currency_group'); ?>
            <table class="form-table">
                <tr>
                    <th>Use Live Exchange Rates</th>
                    <td><input type="checkbox" name="use_live_rates" value="1" <?php checked(1, get_option('use_live_rates'), true); ?>></td>
                </tr>
                <tr>
                    <th>Exchange API Key</th>
                    <td>
                        <input type="password" name="exchange_api_key" value="<?php echo esc_attr(get_option('exchange_api_key')); ?>" size="50" />
                        <p class="description">Used with exchangerate-api.com (free tier works).</p>
                    </td>
                </tr>
                <tr><th colspan="2"><hr></th></tr>
                <tr>
                    <th>Manual Rate: USD to AED</th>
                    <td><input type="number" step="0.01" name="rate_usd_aed" value="<?php echo esc_attr(get_option('rate_usd_aed', 3.67)); ?>"></td>
                </tr>
                <tr>
                    <th>Manual Rate: USD to EUR</th>
                    <td><input type="number" step="0.01" name="rate_usd_eur" value="<?php echo esc_attr(get_option('rate_usd_eur', 0.91)); ?>"></td>
                </tr>
                <tr>
                    <th>Manual Rate: USD to GBP</th>
                    <td><input type="number" step="0.01" name="rate_usd_gbp" value="<?php echo esc_attr(get_option('rate_usd_gbp', 0.78)); ?>"></td>
                </tr>
            </table>

            <h3>Shortcodes</h3>
            <ul>
                <li><code>[currency_switcher]</code> – Renders the currency switcher</li>
                <li><code>[render_price]</code> – Renders the price based on currency</li>
            </ul>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// -------------------------------
// [render_price] Shortcode
// -------------------------------

add_shortcode('render_price', function($atts = []) {
    global $post;
    $post_id = isset($atts['id']) ? intval($atts['id']) : ($post ? $post->ID : 0);
    if (!$post_id) return '';

    $usd_price = get_post_meta($post_id, 'price', true);
    if (!$usd_price) return '';

    return '<span class="rendered-price" data-post-id="' . esc_attr($post_id) . '" data-usd="' . esc_attr($usd_price) . '">USD ' . number_format($usd_price, 0) . '</span>';
});

// -------------------------------
// AJAX Rate Fetcher
// -------------------------------

add_action('wp_ajax_get_exchange_rates', 'get_exchange_rates_ajax');
add_action('wp_ajax_nopriv_get_exchange_rates', 'get_exchange_rates_ajax');

function get_exchange_rates_ajax() {
    $rates = [];
    $use_live = get_option('use_live_rates');

    if ($use_live && $use_live !== '0') {
        $api_key = get_option('exchange_api_key');
        if ($api_key) {
            $response = wp_remote_get("https://v6.exchangerate-api.com/v6/{$api_key}/latest/USD");
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['conversion_rates'])) {
                    $rates = $body['conversion_rates'];
                }
            }
        }
    }

    if (empty($rates)) {
        $rates = [
            'USD' => 1.00,
            'AED' => (float) get_option('rate_usd_aed', 3.67),
            'EUR' => (float) get_option('rate_usd_eur', 0.91),
            'GBP' => (float) get_option('rate_usd_gbp', 0.78),
        ];
    }

    wp_send_json_success($rates);
}

// -------------------------------
// [currency_switcher] Shortcode
// -------------------------------

add_shortcode('currency_switcher', function() {
    ob_start(); ?>

<style>
.currency-dropdown {
    position: relative;
    display: inline-block;
}
.currency-button {
    background: none;
    border: none;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #000;
}
.currency-list {
    position: absolute;
    top: 100%;
    left: 0;
    background: #fff;
    border: 1px solid #ddd;
    width: 220px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 999;
    display: none;
    border-radius: 6px;
    margin-top: 4px;
    padding: 6px 0;
}
.currency-list.open { display: block; }
.currency-list button {
    display: block;
    width: 100%;
    text-align: left;
    padding: 8px 12px;
    background: none;
    border: none;
    font-size: 14px;
    cursor: pointer;
}
.currency-list button:hover {
    background: #f2f2f2;
}
</style>

<div class="currency-dropdown" id="custom-currency-switcher">
  <button class="currency-button" id="currency-toggle">
    <span>🌐</span>
    <span id="current-currency">USD</span>
    <span>▾</span>
  </button>
  <div class="currency-list" id="currency-options">
    <button data-currency="AED">UAE Dirhams – AED</button>
    <button data-currency="EUR">Euros – EUR</button>
    <button data-currency="GBP">British Pounds – GBP</button>
    <button data-currency="USD">US Dollars – USD</button>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const currencyKey = 'selected_currency';
    const rateKey = 'exchange_rates';

    const toggleBtn = document.getElementById("currency-toggle");
    const list = document.getElementById("currency-options");
    const currentLabel = document.getElementById("current-currency");

    const saved = localStorage.getItem(currencyKey);
    if (saved) currentLabel.textContent = saved;

    function updatePrices(currency, rates) {
        const elements = document.querySelectorAll('.rendered-price');
        elements.forEach(el => {
            const usd = parseFloat(el.dataset.usd);
            const rate = rates[currency] || 1;
            const converted = Math.round(usd * rate);
            el.textContent = currency + ' ' + converted.toLocaleString();
        });
    }

    function fetchRatesAndUpdate(currency) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'get_exchange_rates' })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                localStorage.setItem(rateKey, JSON.stringify(res.data));
                updatePrices(currency, res.data);
                window.dispatchEvent(new Event('currencySwitcherChanged'));
            }
        });
    }

    toggleBtn.addEventListener("click", () => {
        list.classList.toggle("open");
    });

    list.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", () => {
            const selected = btn.dataset.currency;
            localStorage.setItem(currencyKey, selected);
            currentLabel.textContent = selected;
            list.classList.remove("open");
            fetchRatesAndUpdate(selected);
        });
    });

    if (saved) fetchRatesAndUpdate(saved);
});
</script>

<?php return ob_get_clean(); });
