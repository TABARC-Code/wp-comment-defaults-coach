<?php
/**
 * Plugin Name: WP Comment Defaults Coach
 * Plugin URI: https://github.com/TABARC-Code/wp-comment-defaults-coach
 * Description: Lets me take control of default comment and ping status per post type, and gives me a simple bulk tool for closing comments on older content.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (c) 2025 TABARC-Code
 * Original work by TABARC-Code.
 * You may modify and redistribute this software under the terms of
 * the GNU General Public License version 3 or (at your option) any later version.
 * You must preserve this notice and clearly state any changes you make.
 *
 * My aim with this plugin is to stop WordPress deciding comment and ping behaviour for me.
 * I want clear defaults per post type and a basic bulk control for old content.
 *
 * TODO: add a preview mode that shows how many posts would be affected before running a bulk action.
 * TODO: add a per post type auto close rule instead of a single global age.
 * FIXME: for huge sites, bulk updates should be chunked to avoid timeouts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Comment_Defaults_Coach {

    private $option_name = 'wp_comment_defaults_coach_settings';

    public function __construct() {
        // Settings UI.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Apply defaults to new content.
        add_filter( 'default_comment_status', array( $this, 'filter_default_comment_status' ), 10, 3 );
        add_filter( 'default_ping_status', array( $this, 'filter_default_ping_status' ), 10, 3 );

        // Bulk action handler.
        add_action( 'admin_post_wp_cdc_bulk_close', array( $this, 'handle_bulk_close_request' ) );

        // Plugin list branding.
        add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
    }

    /**
     * Central place for the brand icon URL.
     */
    private function get_brand_icon_url() {
        return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
    }

    /**
     * Default settings.
     */
    private function get_default_settings() {
        return array(
            'post_type_defaults' => array(), // filled lazily
            'auto_close_days'    => 0,       // 0 means disabled
        );
    }

    /**
     * Get settings merged with defaults.
     */
    private function get_settings() {
        $defaults = $this->get_default_settings();
        $stored   = get_option( $this->option_name, array() );

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        $settings = array_merge( $defaults, $stored );

        if ( ! isset( $settings['post_type_defaults'] ) || ! is_array( $settings['post_type_defaults'] ) ) {
            $settings['post_type_defaults'] = array();
        }

        $settings['auto_close_days'] = isset( $settings['auto_close_days'] ) ? (int) $settings['auto_close_days'] : 0;

        return $settings;
    }

    /**
     * List of public post types I care about.
     */
    private function get_target_post_types() {
        $post_types = get_post_types(
            array(
                'public' => true,
            ),
            'objects'
        );

        // I skip attachments. Comments on attachments are rarely a desired default.
        unset( $post_types['attachment'] );

        return $post_types;
    }

    public function add_settings_page() {
        add_options_page(
            __( 'Comment Defaults Coach', 'wp-comment-defaults-coach' ),
            __( 'Comment Defaults', 'wp-comment-defaults-coach' ),
            'manage_options',
            'wp-comment-defaults-coach',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            $this->option_name,
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitise options from settings form.
     */
    public function sanitize_settings( $input ) {
        $settings  = $this->get_settings();
        $post_types = $this->get_target_post_types();

        $sanitised_defaults = array();

        if ( isset( $input['post_type_defaults'] ) && is_array( $input['post_type_defaults'] ) ) {
            foreach ( $post_types as $type => $obj ) {
                $type_key = sanitize_key( $type );
                $type_row = isset( $input['post_type_defaults'][ $type_key ] ) ? $input['post_type_defaults'][ $type_key ] : array();

                $comments = isset( $type_row['comments'] ) && $type_row['comments'] === 'open' ? 'open' : 'closed';
                $pings    = isset( $type_row['pings'] ) && $type_row['pings'] === 'open' ? 'open' : 'closed';

                $sanitised_defaults[ $type_key ] = array(
                    'comments' => $comments,
                    'pings'    => $pings,
                );
            }
        }

        $settings['post_type_defaults'] = $sanitised_defaults;

        $auto_close_days = 0;
        if ( isset( $input['auto_close_days'] ) ) {
            $auto_close_days = (int) $input['auto_close_days'];
            if ( $auto_close_days < 0 ) {
                $auto_close_days = 0;
            }
        }
        $settings['auto_close_days'] = $auto_close_days;

        return $settings;
    }

    /**
     * Settings page output.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-comment-defaults-coach' ) );
        }

        $settings   = $this->get_settings();
        $post_types = $this->get_target_post_types();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Comment Defaults Coach', 'wp-comment-defaults-coach' ); ?></h1>
            <p>
                I use this screen to decide how comments and pings should behave by default across post types,
                and to close comments on older content in a controlled way.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( $this->option_name ); ?>

                <h2><?php esc_html_e( 'Per post type defaults', 'wp-comment-defaults-coach' ); ?></h2>
                <p>
                    For each public post type, choose whether new content should start with comments and pings
                    open or closed. This affects the default state when creating new items.
                </p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Post type', 'wp-comment-defaults-coach' ); ?></th>
                            <th><?php esc_html_e( 'Default comments', 'wp-comment-defaults-coach' ); ?></th>
                            <th><?php esc_html_e( 'Default pings', 'wp-comment-defaults-coach' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $post_types as $type => $obj ) : ?>
                        <?php
                        $type_key = $type;
                        $defaults = isset( $settings['post_type_defaults'][ $type_key ] )
                            ? $settings['post_type_defaults'][ $type_key ]
                            : array( 'comments' => 'open', 'pings' => 'open' );

                        $comment_default = $defaults['comments'];
                        $ping_default    = $defaults['pings'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $obj->labels->singular_name ); ?></strong><br>
                                <code><?php echo esc_html( $type ); ?></code>
                            </td>
                            <td>
                                <select name="<?php echo esc_attr( $this->option_name ); ?>[post_type_defaults][<?php echo esc_attr( $type_key ); ?>][comments]">
                                    <option value="open" <?php selected( $comment_default, 'open' ); ?>>
                                        <?php esc_html_e( 'Open by default', 'wp-comment-defaults-coach' ); ?>
                                    </option>
                                    <option value="closed" <?php selected( $comment_default, 'closed' ); ?>>
                                        <?php esc_html_e( 'Closed by default', 'wp-comment-defaults-coach' ); ?>
                                    </option>
                                </select>
                            </td>
                            <td>
                                <select name="<?php echo esc_attr( $this->option_name ); ?>[post_type_defaults][<?php echo esc_attr( $type_key ); ?>][pings]">
                                    <option value="open" <?php selected( $ping_default, 'open' ); ?>>
                                        <?php esc_html_e( 'Open by default', 'wp-comment-defaults-coach' ); ?>
                                    </option>
                                    <option value="closed" <?php selected( $ping_default, 'closed' ); ?>>
                                        <?php esc_html_e( 'Closed by default', 'wp-comment-defaults-coach' ); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:2em;"><?php esc_html_e( 'Automatic closing of old content', 'wp-comment-defaults-coach' ); ?></h2>
                <p>
                    If you want comments to close automatically on older posts, set an age in days here.
                    This does not retroactively update existing posts. The bulk tool below handles that part.
                </p>

                <p>
                    <label>
                        <?php esc_html_e( 'Close comments on posts older than', 'wp-comment-defaults-coach' ); ?>
                        <input type="number"
                               min="0"
                               step="1"
                               name="<?php echo esc_attr( $this->option_name ); ?>[auto_close_days]"
                               value="<?php echo esc_attr( $settings['auto_close_days'] ); ?>">
                        <?php esc_html_e( 'days. Use 0 to disable.', 'wp-comment-defaults-coach' ); ?>
                    </label>
                </p>

                <?php submit_button( __( 'Save settings', 'wp-comment-defaults-coach' ) ); ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Bulk close comments on existing content', 'wp-comment-defaults-coach' ); ?></h2>
            <p>
                This is where I bring older content in line with my current policy.
                It only ever closes comments. It does not open anything.
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wp_cdc_bulk_close', 'wp_cdc_bulk_close_nonce' ); ?>
                <input type="hidden" name="action" value="wp_cdc_bulk_close">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp_cdc_post_type"><?php esc_html_e( 'Post type', 'wp-comment-defaults-coach' ); ?></label>
                        </th>
                        <td>
                            <select name="post_type" id="wp_cdc_post_type">
                                <?php foreach ( $post_types as $type => $obj ) : ?>
                                    <option value="<?php echo esc_attr( $type ); ?>">
                                        <?php echo esc_html( $obj->labels->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'This bulk action only affects the selected post type.', 'wp-comment-defaults-coach' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp_cdc_days"><?php esc_html_e( 'Close comments on items older than', 'wp-comment-defaults-coach' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="wp_cdc_days" name="days" min="0" step="1" value="<?php echo esc_attr( max( 0, $settings['auto_close_days'] ) ); ?>">
                            <span>
                                <?php esc_html_e( 'days.', 'wp-comment-defaults-coach' ); ?>
                            </span>
                            <p class="description">
                                <?php esc_html_e( 'Use 0 to close comments on all existing items of this type, regardless of age.', 'wp-comment-defaults-coach' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Run bulk close', 'wp-comment-defaults-coach' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Apply default comment status when creating a new post.
     */
    public function filter_default_comment_status( $status, $post_type, $comment_type ) {
        // I only care about standard comments.
        if ( 'comment' !== $comment_type ) {
            return $status;
        }

        $settings   = $this->get_settings();
        $post_types = $this->get_target_post_types();

        if ( ! isset( $post_types[ $post_type ] ) ) {
            return $status;
        }

        if ( isset( $settings['post_type_defaults'][ $post_type ]['comments'] ) ) {
            return $settings['post_type_defaults'][ $post_type ]['comments'];
        }

        return $status;
    }

    /**
     * Apply default ping status when creating a new post.
     */
    public function filter_default_ping_status( $status, $post_type, $comment_type ) {
        // WordPress treats pings separately so the type check is not as important, but I keep it consistent.
        $settings   = $this->get_settings();
        $post_types = $this->get_target_post_types();

        if ( ! isset( $post_types[ $post_type ] ) ) {
            return $status;
        }

        if ( isset( $settings['post_type_defaults'][ $post_type ]['pings'] ) ) {
            return $settings['post_type_defaults'][ $post_type ]['pings'];
        }

        return $status;
    }

    /**
     * Handle the bulk close form submission.
     *
     * I deliberately keep this simple and explicit. No cron, no background queue yet.
     */
    public function handle_bulk_close_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to run this action.', 'wp-comment-defaults-coach' ) );
        }

        if ( ! isset( $_POST['wp_cdc_bulk_close_nonce'] ) || ! wp_verify_nonce( $_POST['wp_cdc_bulk_close_nonce'], 'wp_cdc_bulk_close' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'wp-comment-defaults-coach' ) );
        }

        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
        $days      = isset( $_POST['days'] ) ? (int) $_POST['days'] : 0;

        $post_types = $this->get_target_post_types();
        if ( ! isset( $post_types[ $post_type ] ) ) {
            wp_redirect( add_query_arg( 'wp_cdc_result', 'invalid_post_type', admin_url( 'options-general.php?page=wp-comment-defaults-coach' ) ) );
            exit;
        }

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'comment_status' => 'open',
        );

        if ( $days > 0 ) {
            $args['date_query'] = array(
                array(
                    'column' => 'post_date',
                    'before' => $days . ' days ago',
                ),
            );
        }

        $query = new WP_Query( $args );

        $updated = 0;

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                // I only close comments. I do not touch pings here.
                $result = wp_update_post(
                    array(
                        'ID'             => $post_id,
                        'comment_status' => 'closed',
                    ),
                    true
                );

                if ( ! is_wp_error( $result ) ) {
                    $updated++;
                }
            }
        }

        wp_redirect(
            add_query_arg(
                array(
                    'page'          => 'wp-comment-defaults-coach',
                    'wp_cdc_result' => 'done',
                    'wp_cdc_count'  => $updated,
                ),
                admin_url( 'options-general.php' )
            )
        );
        exit;
    }

    /**
     * Small branding touch in the plugin list.
     */
    public function inject_plugin_list_icon_css() {
        $icon_url = esc_url( $this->get_brand_icon_url() );
        ?>
        <style>
            .wp-list-table.plugins tr[data-slug="wp-comment-defaults-coach"] .plugin-title strong::before {
                content: '';
                display: inline-block;
                vertical-align: middle;
                width: 18px;
                height: 18px;
                margin-right: 6px;
                background-image: url('<?php echo $icon_url; ?>');
                background-repeat: no-repeat;
                background-size: contain;
            }
        </style>
        <?php
    }
}

new WP_Comment_Defaults_Coach();
