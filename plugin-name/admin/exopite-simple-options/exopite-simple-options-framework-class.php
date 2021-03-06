<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/*
 * Available fields:
 * - card
 * - content
 * - notice
 * - text
 * - textarea
 * - select
 * - checkbox
 * - radio
 * - date
 * - switcher
 * - range
 * - image_select
 * - tap_list
 * - number
 * - color_picker
 * - botton_bar
 * - media upload drag and drop
 * - ACE field
 * - video mp4/oembed
 * ToDo:
 * - font awesome include
 * - for icon in section -> dashicons or fontAwesome
 */

/*
 * Standard args for all field:
 * - type
 * - id
 * - title
 *   - description
 * - class
 * - attributes
 * - before
 * - after
 */
if ( ! class_exists( 'Exopite_Simple_Options_Framework' ) ) :

    class Exopite_Simple_Options_Framework {

        /**
         *
         * dirname
         * @access public
         * @var string
         *
         */
        public $dirname = '';

        /**
         *
         * unique
         * @access public
         * @var string
         *
         */
        public $unique = '';

        /**
         *
         * notice
         * @access public
         * @var boolean
         *
         */
        public $notice = false;

        /**
         *
         * settings
         * @access public
         * @var array
         *
         */
        public $config = array();

        /**
         *
         * options
         * @access public
         * @var array
         *
         */
        public $fields = array();

        public $version = '1.0';

        /**
         *
         * options store
         * @access public
         * @var array
         *
         */
        public $db_options = array();

        public function __construct( $config, $fields ) {

            // If we are not in admin area exit.
            if ( ! is_admin() ) {
                return;
            }

            $this->version = '20180102';

            // Filter for override
            $this->config  = apply_filters( 'exopite-simple-options-framework-config', $config );
            $this->fields   = apply_filters( 'exopite-simple-options-framework-options', $fields );

            $this->dirname = wp_normalize_path( dirname( __FILE__ ) );
            $this->unique = $this->config['id'];

            // Load options only if menu
            // on metabox, page id is not yet available
            if ( $this->config['type'] == 'menu' ) {

                $this->db_options = get_option( $this->unique );

            }

            $this->load_classes();

            Exopite_Simple_Options_Framework_Upload::add_hooks();

            //scripts and styles
            add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts_styles' ) );

            switch ( $this->config['type'] ) {
                case 'menu':
                    add_action( 'admin_init', array( $this, 'register_setting' ) );
                    add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

                    if ( ! empty( $this->config['plugin_basename'] ) ) {
                        add_filter( 'plugin_action_links_' . $this->config['plugin_basename'], array( $this, 'plugin_action_links' ) );
                    }

                    break;

                case 'metabox':
                    /**
                     * Add metabox and register custom fields
                     *
                     * @link https://code.tutsplus.com/articles/rock-solid-wordpress-30-themes-using-custom-post-types--net-12093
                     */
                    add_action( 'admin_init', array( $this, 'add_meta_box' ) );
                    add_action( 'save_post', array( $this, 'save' ) );
                    break;
            }

            // add_action( 'wp_ajax_exopite_test', array( $this, 'exopite_test' ) );

        }

        /*
         * Load classes
         */
        public function load_classes() {

            require_once 'fields-class.php';
            require_once 'upload-class.php';

        }

        /**
         * Get url from path
         * works only for local urls
         * @param  string $path the path
         * @return string       the generated url
         */
        public function get_url( $path = '' ) {

            $url = str_replace(
                wp_normalize_path( untrailingslashit( ABSPATH ) ),
                site_url(),
                $path
            );

            return $url;
        }

        public function locate_template( $type ) {

            /*
             * Ideas:
             * - May extend this with override.
             */

            $path = join( DIRECTORY_SEPARATOR, array( $this->dirname, 'fields', $type . '.php' ) );

            return $path;

        }

        /*
         * Register "settings" for plugin option page in plugins list
         */
        public function plugin_action_links( $links ) {

            /*
             *  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
             */
            $settings_link = array(
                '<a href="' . admin_url( 'plugins.php?page=' . $this->unique ) . '">' . __( 'Settings', '' ) . '</a>',
            );

            return array_merge(  $settings_link, $links );

        }

        /* Create a meta box for our custom fields */
        public function add_meta_box() {

            $default = array(

              'title'      => '',
              'post_types' => array( 'post' ),
              'context'    => 'advanced',
              'priority'   => 'default',
              'capability' => 'edit_posts',
              'tabbed'     => true,

            );

            $this->config = wp_parse_args( $this->config, $default );

            add_meta_box(
                $this->unique,
                $this->config['title'],
                array($this, 'display_page' ),
                $this->config['post_types'],
                $this->config['context'],
                $this->config['priority']
            );

        }

        /*
         * Register settings for plugin option page with a callback to save
         */
        public function register_setting() {

            register_setting( $this->unique, $this->unique, array( $this, 'save' ) );

        }

        /*
         * Register plugin option page
         */
        public function add_admin_menu() {

            $default = array(
                'title'         => 'Options',
                'capability'    => 'manage_options',
                'tabbed'        => true,

            );

            if ( empty( $this->config['submenu'] ) ) {

                $default['icon'] = '';
                $default['position'] = 100;
                $default['menu'] = 'Plugin menu';

                $this->config = wp_parse_args( $this->config, $default );

                $menu = add_menu_page(
                    $this->config['title'],
                    $this->config['menu'],
                    $this->config['capability'],
                    $this->unique, //slug
                    array( $this, 'display_page' ),
                    $this->config['icon'],
                    $this->config['position']
                );

            } else {

                $this->config = wp_parse_args( $this->config, $default );

                $submenu = add_submenu_page(
                    $this->config['menu'],
                    $this->config['title'],
                    $this->config['title'],
                    $this->config['capability'],
                    $this->unique, // slug
                    array( $this, 'display_page' )
                );

            }

        }

        /*
         * Load scripts and styles
         */
        public function load_scripts_styles( $hook ) {

            /*
             * Ideas:
             * - split JS to (metabox and menu) and menu -> not all scripts are required for metabox
             * - proper versioning based on file timestamp?
             */

            if ( is_admin() ) { // for Admin Dashboard Only

                $page_post_hooks = array( 'post-new.php', 'post.php'  );

                $post_type = ( isset( $this->config['post_types'] ) ) ? $this->config['post_types'] : array();

                global $post;

                // Embed the Script on our Plugin's Option Page Only, or if metabox, on the requested post types only
                if ( ( isset($_GET['page']) && $_GET['page'] == $this->unique ) ||
                     ( in_array( $hook, $page_post_hooks ) && in_array( $post->post_type, $post_type ) )
                    ) {

                    // Add jQuery form scripts for menu options AJAX save
                    wp_enqueue_script( 'jquery-form' );

                    wp_register_style( 'jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
                    wp_enqueue_style( 'jquery-ui' );

                    // Add the date picker script
                    wp_enqueue_script( 'jquery-ui-datepicker' );

                    wp_enqueue_script( 'jquery-ui-sortable' );

                    $url = $this->get_url( $this->dirname );
                    $base = trailingslashit( join( '/', array( $url, 'assets' ) ) );

                    wp_enqueue_style( 'exopite-simple-options-framework', $base . 'styles.css', array(), $this->version, 'all' );

                    wp_enqueue_script( 'jquery-interdependencies', $base . 'jquery.interdependencies.min.js', array( 'jquery', 'jquery-ui-datepicker', 'wp-color-picker' ), $this->version, true );

                    /*
                     * Load classes and enqueue class scripts
                     * with this, only enqueue scripts if class/field is used
                     */
                    $this->include_enqueue_field_classes();

                    wp_enqueue_script( 'exopite-simple-options-framework-js', $base . 'scripts.min.js', array( 'jquery', 'jquery-ui-datepicker', 'wp-color-picker', 'jquery-interdependencies' ), $this->version, true );

                }
            }

        }

        /*
         * Save options or metabox to meta
         */
        public function save( $fields ) {

            if ( ! current_user_can( $this->config['capability'] ) ) return;

            $menu = ( $this->config['type'] == 'menu' );

            if ( ! $menu ) global $post;

            $valid = array();

            foreach ( $this->fields as $section ) {

                foreach ( $section['fields'] as $field ) {

                    switch ( $this->config['type'] ) {
                        case 'menu':
                            $value = $fields[$field['id']];
                            break;

                        case 'metabox':
                            $value = ( isset( $_POST[$this->unique][$field['id']] ) ) ? $_POST[$this->unique][$field['id']] : '';
                            break;
                    }

                    // sanitize
                    if( ! empty( $field['sanitize'] ) ) {

                        $sanitize = $field['sanitize'];

                        if( function_exists( $sanitize ) ) {

                            $value_sanitize = isset( $value ) ? $value : '';
                            $valid[$field['id']] = call_user_func( $sanitize, $value_sanitize );

                        }

                    } else {

                        $valid[$field['id']] = $this->sanitize( $field, $value );

                    }

                }

            }

            switch ( $this->config['type'] ) {
                case 'menu':
                    return $valid;
                    break;

                case 'metabox':
                    update_post_meta( $post->ID, $this->unique, $valid );
                    // update_post_meta( $post->ID, $this->unique, json_encode( $valid ) );
                    break;
            }

        }

        /*
         * Validate and sanitize values
         */
        public function sanitize( $field, $value ) {

            switch ( $field['type'] ) {

                case 'panel':
                    // no break
                case 'notice':
                    // no break
                case 'image_select':
                    // no break
                case 'select':
                    // no break
                case 'tap_list':
                    // HTML and array are allowed
                    break;

                case 'textarea':
                    $value = sanitize_text_field( $value );
                    break;

                case 'ace_editor':
                    // $value = base64_encode( $value );
                    break;

                case 'switcher':
                    // no break
                case 'checkbox':
                    $value = ( isset( $value ) && $value === 'yes' ) ? 'yes' : 'no';
                    break;

                case 'range':
                    // no break
                case 'numeric':
                    if ( isset( $field['min'] ) && $value < $field['min'] ) {
                        $value = $field['min'];
                    }
                    if ( isset( $field['max'] ) && $value > $field['max'] ) {
                        $value = $field['max'];
                    }
                    $value = ( isset( $value ) && ! empty( $value ) && is_numeric( $value ) ) ? $value : 0;
                    break;

                default:
                    $value = ( isset( $value ) && ! empty( $value ) ) ? sanitize_text_field( $value ) : '';
                    break;
            }

            return $value;

        }

        /*
         * Loop fileds based on field from user
         */
        public function loop_fields( $callbacks ) {

            foreach ( $this->fields as $section ) {

                // before
                if ( $callbacks['before'] ) call_user_func( array( $this, $callbacks['before'] ), $section );

                foreach ( $section['fields'] as $field ) {

                    // main
                    if ( $callbacks['main'] ) call_user_func( array( $this, $callbacks['main'] ), $field );

                }

                // after
                if ( $callbacks['after'] ) call_user_func( array( $this, $callbacks['after'] ) );
            }

        }

        /*
         * Loop and add callback to include and enqueue
         */
        public function include_enqueue_field_classes() {

            $callbacks = array(
                'before' => false,
                'main'   => 'include_enqueue_field_class',
                'after'  => false
            );

            $this->loop_fields( $callbacks );

        }

        /*
         * Include field classes
         * and enqueue they scripts
         */
        public function include_enqueue_field_class( $field ) {

            $class = 'Exopite_Simple_Options_Framework_Field_' . $field['type'];

            if ( !  class_exists( $class ) ) {

                $field_filename = $this->locate_template( $field['type'] );

                if ( file_exists( $field_filename ) ) {

                    require_once join( DIRECTORY_SEPARATOR, array( $this->dirname, 'fields', $field['type'] . '.php' ) );

                }

            }

            if( class_exists( $class ) ) {


                if( class_exists( $class ) && method_exists( $class, 'enqueue' ) ) {

                    $class::enqueue( plugin_dir_url( __FILE__ ), plugin_dir_path( __FILE__ ) );

                }

            }

        }

        /**
         * Generate files
         * @param  array $field field args
         * @return string       generated HTML for the field
         */
        public function add_field( $field ) {

            $output     = '';
            $class      = 'Exopite_Simple_Options_Framework_Field_' . $field['type'];
            $depend     = '';
            $wrap_class = ( ! empty( $field['wrap_class'] ) ) ? ' ' . $field['wrap_class'] : '';
            $hidden     = ( $field['type'] == 'hidden' ) ? ' hidden' : '';

            if ( ! empty( $field['dependency'] ) ) {
                $hidden  = ' hidden';
                $depend .= ' data-controller="' . $field['dependency'][0] . '"';
                $depend .= ' data-condition="' . $field['dependency'][1] . '"';
                $depend .= ' data-value="' . $field['dependency'][2] . '"';
            }

            $output .= '<div class="exopite-sof-field exopite-sof-field-'. $field['type'] . $wrap_class . $hidden . '"'. $depend .'>';

            if( ! empty( $field['title'] ) ) {

                $output .= '<h4 class="exopite-sof-title">';

                $output .= $field['title'];

                if ( ! empty( $field['description'] ) ) {
                    $output .= '<p class="exopite-sof-description">'. $field['description'] .'</p>';
                }

                $output .= '</h4>'; // exopite-sof-title
                $output .= '<div class="exopite-sof-fieldset">';
            }

            if( class_exists( $class ) ) {

                switch ( $this->config['type'] ) {
                    case 'menu':
                        $value = ( isset( $field['id'] ) && isset( $this->db_options[$field['id']] ) ) ? $this->db_options[$field['id']] : '';
                        break;

                    case 'metabox':
                        $value = ( isset( $field['id'] ) && isset( $this->db_options[$field['id']] ) ) ? $this->db_options[$field['id']] : '';
                        break;
                }

                ob_start();
                $element = new $class( $field, $value, $this->unique, $this->config['type'] );
                $element->output();
                $output .= ob_get_clean();

            } else {

                $output .= '<div class="danger unknown">';
                $output .= __( 'ERROR:', 'exopite-simple-options' ) . ' ';
                $output .= __( 'This field class is not available!', 'exopite-simple-options' );
                $output .= ' <i>(' . $field['type'] . ')</i>';
                $output .= '</div>';

            }

            if( ! empty( $field['title'] ) ) $output .= '</div>'; // exopite-sof-fieldset

            $output .= '<div class="clearfix"></div>';

            $output .= '</div>'; // exopite-sof-field

            echo $output;

        }

        /*
         * Display form and header for options page
         * for metabox no need to do this.
         */
        public function display_options_page_header() {


            echo '<form method="post" action="options.php" enctype="multipart/form-data" name="' . $this->config['id'] . '" class="exopite-sof-form-js ' . $this->config['id'] . '-form" data-save="' . __( 'Saving...', 'exopite-simple-options' ) . '" data-saved="' . __( 'Saved Successfully.', 'exopite-simple-options' ) . '">';

            settings_fields( $this->unique );
            do_settings_sections( $this->unique );

            echo '<header class="exopite-sof-header exopite-sof-header-js">';
            echo '<h1>' . $this->config['title'] . '</h1>';

            echo '<fieldset><span class="exopite-sof-ajax-message"></span>';
            submit_button( __( 'Save Settings', 'exopite-simple-options' ), 'primary ' . 'exopite-sof-submit-button-js', $this->unique . '-save', false, array() );
            echo '</fieldset>';
            echo '</header>';

        }

        /*
         * Display form and footer for options page
         * for metabox no need to do this.
         */
        public function display_options_page_footer() {

            echo '<footer class="exopite-sof-footer-js exopite-sof-footer">';

            echo '<fieldset><span class="exopite-sof-ajax-message"></span>';
            submit_button( __( 'Save Settings', 'exopite-simple-options' ), 'primary ' . 'exopite-sof-submit-button-js', '', false, array() );
            echo '</fieldset>';

            echo '</footer>';

            echo '</form>';

        }

        /*
         * Display section header, only first is visible on start
         */
        public function display_options_section_header( $section ) {

            $visibility = ' hide';
            if ( $section === reset( $this->fields ) ) $visibility = '';

            echo '<div class="exopite-sof-section exopite-sof-section-' . $section['name'] . $visibility . '">';

            if ( isset( $section['title'] ) && ! empty( $section['title'] ) ) {

                echo '<h2 class="exopite-sof-section-header"><span class="dashicons-before dashicons-email"></span>' . $section['title'] . '</h2>';


            }

        }

        /*
         * Display section footer
         */
        public function display_options_section_footer() {

            echo '</div>'; // exopite-sof-section

        }

        /**
         * Display form form either options page or metabox
         */
        public function display_page() {

            do_action( 'exopite-simple-options-framework-form-' . $this->config['type'] . '-before' );

            settings_errors();

            echo '<div class="exopite-sof-wrapper exopite-sof-wrapper-' . $this->config['type'] . ' ' . $this->config['id'] . '-options">';

            switch ( $this->config['type'] ) {
                case 'menu':
                    $this->display_options_page_header();
                    break;

                case 'metabox':
                    /*
                     * Get options
                     * Can not get options in __consturct, because there, the_ID is not yet available.
                     */
                    $this->db_options = get_post_meta( get_the_ID(), $this->unique, true );
                    // $this->db_options = json_decode( get_post_meta( get_the_ID(), $this->unique, true ), true );
                    break;
            }

            $sections = 0;

            foreach ( $this->fields as $section ) {
                $sections++;
            }

            $tabbed = ( $sections > 1 && $this->config['tabbed'] ) ? ' exopite-sof-content-nav exopite-sof-content-js' : '';

            /*
             * Generate fields
             */
            // Generate tab navigation
            echo '<div class="exopite-sof-content' . $tabbed . '">';

            if ( ! empty( $tabbed ) ) {

                echo '<div class="exopite-sof-nav"><ul class="exopite-sof-nav-list">';
                foreach ( $this->fields as $section ) {
                    $active = '';
                    if ( $section === reset( $this->fields ) ) $active = ' active';
                    echo '<li  class="exopite-sof-nav-list-item' . $active . '" data-section="' . $section['name'] . '"><span class="dashicons-before ' . $section['icon'] . '"></span>' . $section['title'] . '</li>';
                }
                echo '</ul></div>';

            }

            echo '<div class="exopite-sof-sections">';

            // echo wp_oembed_get('https://www.youtube.com/watch?v=Lq0fUa0vW_E');

            // Generate fields
            $callbacks = array(
                'before' => 'display_options_section_header',
                'main'   => 'add_field',
                'after'  => 'display_options_section_footer'
            );

            $this->loop_fields( $callbacks );

            echo '</div>'; // sections
            echo '</div>'; // content

            if ( $this->config['type'] == 'menu' ) {

                $this->display_options_page_footer();

            }

            echo '</div>';

            do_action( 'exopite-simple-options-framework-form-' . $this->config['type'] . '-after' );

        }

    }

endif;
