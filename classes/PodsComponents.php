<?php
class PodsComponent {

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {

    }

    /**
     * Add options and set defaults for field type, shows in admin area
     *
     * @return array $options
     *
     * @since 2.0.0
     */
    public function options () {
        $options = array( /*
            'option_name' => array(
                'label' => 'Option Label',
                'depends-on' => array( 'another_option' => 'specific-value' ),
                'default' => 'default-value',
                'type' => 'field_type',
                'data' => array(
                    'value1' => 'Label 1',

                    // Group your options together
                    'Option Group' => array(
                        'gvalue1' => 'Option Label 1',
                        'gvalue2' => 'Option Label 2'
                    ),

                    // below is only if the option_name above is the "{$fieldtype}_format_type"
                    'value2' => array(
                        'label' => 'Label 2',
                        'regex' => '[a-zA-Z]' // Uses JS regex validation for the value saved if this option selected
                    )
                ),

                // below is only for a boolean group
                'group' => array(
                    'option_boolean1' => array(
                        'label' => 'Option boolean 1?',
                        'default' => 1,
                        'type' => 'boolean'
                    ),
                    'option_boolean2' => array(
                        'label' => 'Option boolean 2?',
                        'default' => 0,
                        'type' => 'boolean'
                    )
                )
            ) */
        );

        return $options;
    }
}


class PodsComponents {

    /**
     * Root of Components directory
     *
     * @var string
     *
     * @private
     * @since 2.0.0
     */
    private $components_dir = null;

    /**
     * Available components
     *
     * @var string
     *
     * @private
     * @since 2.0.0
     */
    private $components = array();

    /**
     * Components settings
     *
     * @var string
     *
     * @private
     * @since 2.0.0
     */
    private $settings = array( 'components' => array() );

    /**
     * Setup actions and get options
     *
     * @since 2.0.0
     */
    public function __construct ( $admins = null ) {
        $this->components_dir = apply_filters( 'pods_components_dir', PODS_DIR . 'components/' );

        $settings = get_option( 'pods_component_settings', '' );

        if ( !empty( $settings ) )
            $this->settings = (array) json_decode( $settings, true );

        if ( !isset( $this->settings[ 'components' ] ) )
            $this->settings[ 'components' ] = array();

        // Get components
        add_action( 'after_setup_theme', array( $this, 'get_components' ), 11 );

        // Load in components
        add_action( 'after_setup_theme', array( $this, 'load' ), 12 );
    }

    /**
     * Add menu item
     *
     * @since 2.0.0
     */
    public function menu ( $parent ) {
        foreach ( $this->components as $component => $component_data ) {
            if ( !empty( $component_data[ 'Hide' ] ) )
                continue;

            add_submenu_page(
                $parent,
                strip_tags( $component_data[ 'Name' ] ),
                '- ' . strip_tags( $component_data[ 'MenuName' ] ),
                'read',
                'pods-component-' . $component_data[ 'ID' ],
                array( $this, 'admin_components_handler' )
            );
        }
    }

    /**
     * Load activated components and init component
     *
     * @since 2.0.0
     */
    public function load () {
        foreach ( (array) $this->settings[ 'components' ] as $component => $options ) {
            if ( !isset( $this->components[ $component ] ) && 0 == $options )
                continue;
            elseif ( isset( $this->components[ $component ] ) && file_exists( $this->components_dir . $component ) ) {
                $component_data = $this->components[ $component ];

                include_once $this->components_dir . $component;

                if ( !empty( $component_data[ 'Class' ] ) && class_exists( $component_data[ 'Class' ] ) && !isset( $this->components[ $component ][ 'object' ] ) ) {
                    $this->components[ $component ][ 'object' ] = new $component_data[ 'Class' ];

                    if ( method_exists( $this->components[ $component ][ 'object' ], 'options' ) ) {
                        $this->components[ $component ][ 'options' ] = $this->components[ $component ][ 'object' ]->options();

                        self::options( $component, $this->components[ $component ][ 'options' ] );
                    }

                    if ( method_exists( $this->components[ $component ][ 'object' ], 'handler' ) )
                        $this->components[ $component ][ 'object' ]->handler( $this->settings[ 'components' ][ $component ] );
                }
            }
        }
    }

    /**
     * Get list of components available
     *
     * @since 2.0.0
     */
    public function get_components () {
        $components = get_transient( 'pods_components' );

        if ( !is_array( $components ) || empty( $components ) || ( is_admin() && isset( $_GET[ 'page' ] ) && 'pods-components' == $_GET[ 'page' ] && isset( $_GET[ 'reload_components' ] ) ) ) {
            $component_dir = @opendir( rtrim( $this->components_dir, '/' ) );
            $component_files = array();

            if ( false !== $component_dir ) {
                while ( false !== ( $file = readdir( $component_dir ) ) ) {
                    if ( '.' == substr( $file, 0, 1 ) )
                        continue;
                    elseif ( is_dir( $this->components_dir . $file ) ) {
                        $component_subdir = @opendir( $this->components_dir . $file );

                        if ( $component_subdir ) {
                            while ( false !== ( $subfile = readdir( $component_subdir ) ) ) {
                                if ( '.' == substr( $subfile, 0, 1 ) )
                                    continue;
                                elseif ( '.php' == substr( $subfile, -4 ) )
                                    $component_files[] = $this->components_dir . $file . '/' . $subfile;
                            }

                            closedir( $component_subdir );
                        }
                    }
                    elseif ( '.php' == substr( $file, -4 ) )
                        $component_files[] = $this->components_dir . $file;
                }

                closedir( $component_dir );
            }

            $default_headers = array(
                'ID' => 'ID',
                'Name' => 'Name',
                'MenuName' => 'Menu Name',
                'Description' => 'Description',
                'Version' => 'Version',
                'Class' => 'Class',
                'Hide' => 'Hide'
            );

            $components = array();

            foreach ( $component_files as $component_file ) {
                if ( !is_readable( $component_file ) )
                    continue;

                $component_data = get_file_data( $component_file, $default_headers, 'pods_component' );

                if ( empty( $component_data[ 'Name' ] ) || 'yes' == $component_data[ 'Hide' ] )
                    continue;

                if ( empty( $component_data[ 'MenuName' ] ) )
                    $component_data[ 'MenuName' ] = $component_data[ 'Name' ];

                if ( empty( $component_data[ 'Class' ] ) )
                    $component_data[ 'Class' ] = 'Pods_' . basename( $component_file, '.php' );

                if ( empty( $component_data[ 'ID' ] ) )
                    $component_data[ 'ID' ] = sanitize_title( $component_data[ 'Name' ] );

                $component_data[ 'File' ] = $component_file;

                $components[ $component_data[ 'ID' ] ] = $component_data;
            }

            ksort( $components );

            set_transient( 'pods_components', $components );
        }

        $this->components = $components;

        return $this->components;
    }

    public function options ( $component, $options ) {

        if ( !isset( $this->settings[ 'components' ][ $component ] ) || !is_array( $this->settings[ 'components' ][ $component ] ) )
            $this->settings[ 'components' ][ $component ] = array();

        foreach ( $options as $option => $data ) {
            if ( !isset( $this->settings[ 'components' ][ $component ][ $option ] ) && isset( $data[ 'default' ] ) )
                $this->settings[ 'components' ][ $component ][ $option ] = $data[ 'default' ];
        }
    }

    public function components () {
        return $this->components;
    }
}